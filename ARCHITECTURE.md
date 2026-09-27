# Architecture

## 1. Overview

The API follows hexagonal architecture with DDD and CQRS:

- **Domain:** entities, value objects and business rules, in plain PHP.
- **Application:** use cases, as commands and queries with one handler each.
- **Infrastructure:** Laravel: HTTP, Eloquent, cache, queue.

There is one bounded context, `LogisContext`. `SharedContext` holds what every context reuses: buses, domain events and the response format.

The goal is that business rules don't depend on the framework, so they are tested without a database or HTTP, and each layer changes without touching the others.

## 2. Layers and structure

```
app/
├── SharedContext/
│   ├── Domain/              Event/, Exception/, QueryResponse/
│   └── Infrastructure/      Bus/, BusMiddleware/, Event/, Http/, Persistence/, Providers/
└── LogisContext/
    ├── Domain/
    │   ├── Model/<Aggregate>/        entities, repository interfaces, policies
    │   ├── ValueObject/<Aggregate>/  immutable values that validate themselves
    │   ├── Exception/<Aggregate>/    semantic exceptions
    │   └── Event/<Aggregate>/        domain events
    ├── Application/
    │   ├── Command/<Aggregate>/      commands and their handlers
    │   ├── Query/<Aggregate>/        queries and their handlers
    │   ├── Services/<Aggregate>/     ports and application services
    │   ├── Exception/<Aggregate>/
    │   └── ModelView/<Aggregate>/    response DTOs
    └── Infrastructure/
        ├── Http/                     controllers, FormRequests, middlewares
        ├── Persistence/<Aggregate>/  Eloquent models, mappers, repositories, adapters
        ├── Cache/                    cache decorators
        └── Providers/                LogisServiceProvider
```

Deptrac (`deptrac.yaml`) enforces the dependencies between layers:

| Layer | May depend on |
|---|---|
| Domain | SharedDomain, Carbon |
| Application | Domain, SharedDomain, Carbon |
| Infrastructure | Everything, including Laravel |

Carbon is the only third-party library allowed in Domain and Application.

## 3. Request flow

```
Route → FormRequest → Controller → CommandBus / QueryBus → Handler → Domain / Repository → ModelView → ApiResponse
```

- The **FormRequest** validates the structure of the input and builds the command or query.
- The **controller** has no logic. It dispatches the message and returns the response.
- The **bus** resolves the handler by name: the handler of `Foo` is `FooHandler`.
- Commands go through two middlewares:
  - `TransactionMiddleware`, which wraps the handler in a transaction;
  - `DomainEventMiddleware`, which publishes the collected domain events inside that transaction.
- Queries run without a transaction and never change state.
- **Errors:** each domain exception extends a base class that decides its HTTP status (401, 403, 404, 409, 422 or 504). `ApiExceptionRenderer` maps it. Any other exception is a logged 500 with no details.
- Every response has the format `{ success, data, message, meta }`.

## 4. Mapping to the assessment

| The assessment asks for | Where it is |
|---|---|
| Controllers | `Infrastructure/Http/Controller/` (`AuthController`, `SearchController`, `ViewController`) |
| Form Requests | `Infrastructure/Http/Request/<Aggregate>/`, one per action |
| `SearchService` | `Application/Query/Search/SearchServiceHandler` |
| A repository per searchable entity | `EntitySearcher` port, with one `Eloquent*Searcher` adapter per entity |
| `ViewBuilder` | `Application/Services/CustomView/ViewBuilder` port, with one `Eloquent*ViewBuilder` adapter per view type |
| `ViewRepository` | `Domain/Model/CustomView/CustomViewRepository`, implemented by `EloquentCustomViewRepository` |
| `ViewPolicy` | `Domain/Model/CustomView/ViewPolicy` |
| DTOs for view configurations | Value objects in `Domain/ValueObject/CustomView/` (`ViewDefinition`, `ViewFilters`, `ViewColumns`…) |
| `CustomView` model with JSON casts | `Infrastructure/Persistence/CustomView/CustomViewModel` |
| Resources (API transformers) | `Application/ModelView/<Aggregate>/` |
| Events | `Domain/Event/<Aggregate>/` |
| `DB::transaction()` | `TransactionMiddleware` of the command bus |
| Column and operator whitelist | `Domain/ValueObject/CustomView/ViewFieldCatalog` |
| Cache | `CachedEntitySearcher` and `CachedViewBuilder` decorators |
| Execution timeout | `Infrastructure/CustomView/StatementTimeout` |

## 5. Authentication

- Sanctum tokens, stored as a hash, with IP, user agent, `last_used_at` and `expires_at`. They expire after `ACCESS_TOKEN_TTL_MINUTES` (60).
- **Rotation:** after every authenticated request, `RotateAccessTokenMiddleware` creates a successor token and returns it in `X-Refreshed-Token`. The previous token expires `ACCESS_TOKEN_GRACE_SECONDS` (10) later (see section 8).
- `refresh` rotates explicitly and returns the new token in the body. `logout` revokes the current token and its predecessor if it is still in its grace period. Neither goes through the rotation middleware.
- The `scheduler` service runs `sanctum:prune-expired` every hour.
- Rate limit: 60 requests per minute per user, or per IP without a token.

## 6. Global search

- `GET /search` searches tickets, assets, contracts and locations. `SearchServiceHandler` asks one `EntitySearcher` per requested entity.
- Each adapter filters by the customers the user can see (`User::visibleCustomers()`): their own customer, plus its sub-accounts when the user has `can_view_sub_accounts`.
- Each adapter returns the newest matches up to `limit` (default 5, maximum 50), the `total` and `has_more`.
- The search term is escaped for `LIKE` and bound as a parameter. The searched columns have indexes.
- Results are cached for 5 minutes per entity, visible customers, term and limit, so users who see the same customers share the cache.

## 7. Custom views

- **Storage:** the columns and filters are JSON (`columns`, `filters`), not PHP-serialised data.
- **Validation:**
  - `ViewFieldCatalog` is the whitelist of each view type: which fields exist, their type (text, choice, identifier, date), which can be sorted, and which operators each type allows;
  - `ViewDefinition` validates columns and filters against the catalog when a view is built;
  - a violation is a 422 that names the field, before any query is built.
- **Execution:**
  - each view type has an Eloquent adapter that maps catalog fields to columns and relations;
  - `ViewFilterApplier` turns each filter into a Query Builder condition with bound values;
  - relations are eager loaded with only the columns they need and returned as `{ id, label }`.
- **Visibility:** a view always shows the rows of the customers of the user who executes it, never the owner's. A public view shows each user their own data.
- **Pagination:** by cursor, from 1 to 1000 rows per page (default 50). The cursor carries the sort it was built for, and a cursor of another sort is rejected.
- **User filters:** each saved filter has a `user_filter` mode that decides what the user who executes the view may change:
  - `restricted`: a subset of the saved values;
  - `open`: replaces the saved filter;
  - `locked`: can't be changed. This is the default.
- **Cache and timeout:** results are cached for 5 minutes. A query that runs longer than `VIEWS_EXECUTION_TIMEOUT_MS` (5000) is stopped by MySQL and answers 504.
- **Authorization:**
  - `ViewPolicy::canSee()`: the owner, or any user of the same customer when the view is public;
  - `ViewPolicy::canManage()`: only the owner;
  - a view the user can't see answers 404, so its existence isn't revealed. A view the user can see but not change answers 403.

## 8. Decisions and trade-offs

**A domain entity and a separate Eloquent model.** Each aggregate has an entity in `Domain/Model/` with its rules and events, and an Eloquent model in `Infrastructure/Persistence/` that only reads and writes the table. `<Aggregate>Mapper` is the only place that converts one into the other.

- Gain:
  - rules are tested with unit tests and in-memory repositories;
  - the domain doesn't depend on Laravel;
  - only the repository saves, so no code calls `->save()` on its own;
  - relations are loaded at once with `with()`, never lazily, so the number of queries is fixed;
  - a row that breaks the rules fails when read (`CorruptedRecordException`).
- Cost:
  - three classes per aggregate (entity, model, mapper) and hand-written mapping;
  - Eloquent features (casts, relations, scopes) aren't available in the domain;
  - the whole relation graph is loaded even when part of it isn't used.
- Exception: reads that only display data. View execution and search don't build domain entities. Their adapters query the Eloquent models and turn each row into an array for the response. These rows are never changed, so entity rules have nothing to protect, and building up to 1000 entities per page, each with its relations, would only add cost. The domain still decides the query: `ViewDefinition` validates columns and filters, `ViewPolicy` decides access, and `User::visibleCustomers()` decides which customers' rows are returned.

**A command bus instead of `DB::transaction()`.** Handlers don't open transactions. `TransactionMiddleware` wraps every command, and `DomainEventMiddleware` publishes the domain events inside the same transaction. No handler can forget the transaction, and events are published only if the command succeeds.

**A domain policy instead of a Laravel Policy.** `ViewPolicy` is a plain class that receives domain entities. The handlers apply it through `CustomViewAccess`. It is tested with unit tests, and it protects the use case whether it runs from HTTP, the console or a test. A Laravel Policy would receive Eloquent models and would be tested mostly through endpoints.

**ModelViews instead of `JsonResource`.** Query handlers return `final readonly` DTOs with `toArray()`. The response shape is decided in the application layer, without depending on HTTP.

**Service registries and cache decorators.** The adapters of each port (`EntitySearcher`, `ViewBuilder`) are tagged in the container. `EntitySearchers` and `ViewBuilders` receive them as an `iterable` and index them by entity or view type, so a new entity only needs a new adapter. Before tagging, each adapter is wrapped with a cache decorator (`$app->extend()`). Handlers and Eloquent adapters don't know the cache exists.

**A grace period for rotated tokens.** The assessment asks to rotate the token on every authenticated request. Taken literally, this breaks concurrent requests: a client that sends several requests in parallel with the same token gets only the first one through, and the rest fail with 401. `ACCESS_TOKEN_GRACE_SECONDS` keeps the previous token valid for 10 seconds after rotation. Only one of the parallel requests receives the new token; the rotation is claimed atomically, so a token is never rotated twice.

**Save endpoints return no data.** `POST /views`, `PUT /views/{id}` and `POST /views/{id}/set-default` answer with an empty `data`, like `DELETE`. Commands return nothing, and the client reads the view with `GET`. The cost is that creating a view doesn't return its id.

**A global rate limit.** The assessment asks for rate limiting on view execution. The global limit of 60 requests per minute per user covers it, without a separate limiter for `execute`.

**No jobs.** No feature sends emails or calls external services, so there is nothing to queue. The infrastructure is ready: asynchronous listeners (`AsyncEventListener`) go to the `events` queue, the job is inserted inside the command transaction, and the `worker` service consumes it.

**No Laravel Scout.** Search uses `LIKE` with indexes and a 5-minute cache, which is enough for the data volume. Scout would need another service (Meilisearch, Typesense or Algolia) and would have to keep its index in sync. Its `database` driver also uses `LIKE`, so it would add nothing. Adding Scout is transparent to the application: it would be a new `EntitySearcher` adapter, without changing the handler, the cache or the response. The adapter would still have to filter by the user's customers inside the index.

**No comments.** Code explains itself through names and small methods. The only PHPDoc allowed covers types that Larastan checks (generics, array shapes) and a short *why* where the reason can't be read from the code (`StatementTimeout`, `ViewCursor`, `CalendarDay`). The cost is that some reasons require reading the spec in `specs/`.

**Named arguments.** Every call passes its arguments by name, except where PHP or Laravel don't allow it (variadics, magic methods). A Larastan rule enforces it. It makes calls readable without opening the signature.

## 9. Assumptions

- A user belongs to at most one customer. A user without a customer sees no data and can't create views.
- A user sees the sub-accounts of their customer only with `can_view_sub_accounts`.
- A public view is shared with the users of the owner's customer. Each one sees their own data.
- Only the owner changes, deletes or sets a view as default.
