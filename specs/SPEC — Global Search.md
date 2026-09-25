# SPEC — Global Search

Status: approved

## Objective

Refactor the legacy global search (`logis-test/endpoints/endpoint-1.php`) into `GET /api/v1/search`, as required by "Endpoint 1: Global Search" in `logis-test/LARAVEL_TECHNICAL_TEST.md`.

The consumer is the API client's global search box: the user types a keyword and gets, per entity, the first matches, how many there are in total, and whether there are more. The server, not the client, decides which customers the user may see. This removes the legacy vulnerability where the browser sent `erpCustomerId` and `subAccountPermission`.

### Requirements from the brief

- Authentication middleware, Query Builder (no raw SQL), validated and sanitised input, and authorization: a user only searches their customer's data.
- A search service, the Repository pattern for each searchable entity, Eloquent models with relationships, and Scout as an option.
- `GET /api/v1/search?q={keyword}&limit={5}&entities={tickets,assets}`.
- Response: per entity `results`, `total` and `has_more`, and `meta` with `query`, `limit_per_entity` and `execution_time_ms`.
- Eager loading, a 5-minute result cache, indexes on the searched fields, and query scopes.

### What the legacy code does

| Entity | Searched columns (`LIKE '%keyword%'`) | Extra filter |
|---|---|---|
| tickets | `subject`, `repair_id`, `customer_incident_number`, `custevent_erp_rma_number` | not inactive, not a draft |
| assets | `custom_seriel_number`, `manufacturer`, `model` | not inactive |
| contracts | `contract_name` | status active, not inactive |
| locations | `title`, `full_address`, `city`, `state`, `postal_code` | — |

It returns the first 4 results of each entity ordered by `id DESC`, plus a "View All" link when there are more.

## API contract

```
GET /api/v1/search?q=zebra&limit=5&entities=tickets,assets
GET /api/v1/search?q=zebra&entities[]=tickets&entities[]=assets
Authorization: Bearer <token>
```

| Parameter | Rules | Default |
|---|---|---|
| `q` | required, string, trimmed. No length limit | — |
| `limit` | integer, 1 to 50, results per entity | 5 |
| `entities` | comma-separated string **or** array; each value one of `tickets`, `assets`, `contracts`, `locations`; duplicates are ignored | all four |

- Both forms of `entities` become the same array before validation (D6). An unknown value returns 422.
- `q` made only of spaces is empty after the trim, so it fails `required` (422).
- The existing `auth:sanctum` and `RotateAccessTokenMiddleware` protect the route, and the global `api` limiter (60 per minute) applies. The response carries `X-Refreshed-Token` like every authenticated route.

**200 OK**

```json
{
  "success": true,
  "data": {
    "tickets": {
      "results": [
        { "id": 812, "repair_id": "R-10231", "incident_number": "INC-5521", "subject": "Zebra printer jams", "status": "Open" }
      ],
      "total": 24,
      "has_more": true
    },
    "assets": {
      "results": [
        { "id": 77, "serial_number": "ZB-00931", "manufacturer": "Zebra", "model": "ZT410", "item_name": "Label printer", "status": "active" }
      ],
      "total": 3,
      "has_more": false
    }
  },
  "message": "Search results",
  "meta": {
    "timestamp": "2026-09-25T12:00:00Z",
    "version": "1.0",
    "query": "zebra",
    "limit_per_entity": 5,
    "execution_time_ms": 45
  }
}
```

- `data` holds only the requested entities, always in the canonical order `tickets`, `assets`, `contracts`, `locations`.
- `has_more` is `total > limit`.
- Result fields:

  | Entity | Fields |
  |---|---|
  | tickets | `id`, `repair_id`, `incident_number`, `subject`, `status` (status name) |
  | assets | `id`, `serial_number`, `manufacturer`, `model`, `item_name`, `status` |
  | contracts | `id`, `name`, `status` |
  | locations | `id`, `title`, `city`, `state`, `postal_code` |

- A user without a customer gets 200 with every requested entity empty (`results: []`, `total: 0`, `has_more: false`).
- Errors: 401 without a valid token, 422 on invalid input, 429 over the rate limit.

## Key design decisions

### D1. Visible customers

This spec implements the rule left open in D10 of `SPEC — Core Domain Model.md`:

- `users.can_view_sub_accounts`: a new boolean column, NOT NULL, default `false`. It replaces the legacy `subAccountPermission == 3`. The domain `User` gains `canViewSubAccounts(): bool`.
- `VisibleCustomers` (value object, `Domain/ValueObject/Customer/`) is a list of unique `CustomerId`s, which can be empty. It exposes `isEmpty()` and `values(): list<int>`, sorted so that the cache key doesn't depend on order.
- `VisibleCustomersResolver` (domain service, `Domain/Model/Customer/`) decides the list for a `User`:
  - no customer → empty;
  - a customer, without the permission → only that customer;
  - a customer, with the permission → that customer plus its sub-accounts.
- `CustomerRepository` gains `subAccountsOf(CustomerId $id): list<Customer>`, its first consumer. The hierarchy has one level (R6 of the core spec), so sub-accounts of sub-accounts don't exist.
- The views spec reuses this resolver unchanged: executing a view filters by the customers of the user who runs it.

### D2. Searchable entities: an enum plus one searcher per entity

What can be searched is an **entity**, which is part of the API vocabulary. Table names never leave the adapter that owns them.

- `SearchableEntity` (backed enum, `Domain/ValueObject/Search/`): `tickets`, `assets`, `contracts`, `locations`. It is the whitelist for the FormRequest (`Rule::enum`) and for the OpenAPI enum. Its case order is the canonical order of the response.
- `EntitySearcher` (port, `Application/Query/GlobalSearch/`):

  ```php
  interface EntitySearcher
  {
      public function entity(): SearchableEntity;

      public function search(SearchCriteria $criteria): EntitySearchResult;
  }
  ```

- One adapter per entity, in the folder of its aggregate: `Persistence/Ticket/EloquentTicketSearcher`, `Persistence/Asset/EloquentAssetSearcher`, `Persistence/Contract/EloquentContractSearcher` and `Persistence/Location/EloquentLocationSearcher`. Only the adapter knows the table, the columns and the eager loading.
- `EntitySearchers` (`Application/Query/GlobalSearch/`) receives `iterable<EntitySearcher>` and indexes them by `entity()`. Its constructor fails fast with `EntitySearcherNotRegisteredException` (a logged 500) if a case of `SearchableEntity` has no searcher, or has more than one.
- `LogisServiceProvider` tags the four searchers as `entity_searchers` and injects them with `giveTagged()`.
- **Adding a searchable entity** takes a new enum case, a new searcher class and one line in the tag list. The registry test (see Testing strategy) catches a forgotten searcher.
- The existing aggregate repositories get no `search()` method. They load the full aggregate graph (D2 of the core spec), and D4 of the core spec decided that lists are read through query handlers that return views.

**Mapping to the brief.** "Controller → Service → Repository" is `SearchController` → `QueryBus` → `GlobalSearchHandler` (the search service) → `EntitySearcher` (one repository per entity). `ARCHITECTURE.md` records this mapping. There is no `SearchService` class that would only forward calls.

### D3. Search semantics

- **Matching:** `LIKE '%term%'` on every searched column of the entity, joined with `OR`, inside `customer_id IN (<visible customers>)`. This is the legacy "contains" behaviour, which users expect when they type part of a serial number or repair id. The comparison is case-insensitive through the column collation (`utf8mb4_unicode_ci`).

  | Entity | Searched columns | Extra filter |
  |---|---|---|
  | tickets | `subject`, `repair_id`, `incident_number` | not soft-deleted |
  | assets | `serial_number`, `manufacturer`, `model` | not soft-deleted (every `status`) |
  | contracts | `name` | `status = active` |
  | locations | `title`, `full_address`, `city`, `state`, `postal_code` | — |

- **Escaping:** the term is always a bound parameter. Before it is bound, `\`, `%` and `_` are escaped by `LikePattern::contains()` (`SharedContext/Infrastructure/Persistence/`), so `q=%` searches for a literal `%` and never matches everything.
- **Order:** `id DESC`, as in the legacy code.
- **Queries per entity:** one `COUNT(*)` for `total`, and, only when `total > 0`, one `SELECT … LIMIT n` for the results. Tickets eager load `status` with `with()`, one more query. The number of queries is fixed, whatever the number of results.
- **A user with no visible customers:** the handler returns empty results without calling any searcher.
- **Scout** is not used. Its `database` engine also runs `LIKE`, and Meilisearch or Algolia would add infrastructure the brief doesn't need. The `EntitySearcher` port lets an adapter switch to Scout, or to a MySQL `FULLTEXT … WITH PARSER ngram` index, without changing the handler. This is documented as a possible improvement.

### D4. Query scopes

The Eloquent models carry the reusable parts of the search as local scopes (`scopeX`, the Laravel 11 form):

| Model | Scopes |
|---|---|
| `TicketModel`, `AssetModel`, `ContractModel`, `LocationModel` | `visibleTo(Builder $query, VisibleCustomers $customers)`: `whereIn('customer_id', …)` |
| same | `matching(Builder $query, SearchTerm $term)`: the grouped `OR` of `LIKE` on that model's searched columns |
| `ContractModel` | `active(Builder $query)`: `status = active` |

The searched columns live in the model's `matching()` scope. The searcher only combines scopes, counts, orders and maps. The views spec reuses `visibleTo()`, and `matching()` for its free-text search.

### D5. Indexes

**Rule: every column the search filters on has its own single-column index.** No composite indexes are added for search. Existing indexes are kept.

| Table | Already indexed | New single-column indexes |
|---|---|---|
| `tickets` | `customer_id` (FK), `repair_id` (unique), `incident_number` (unique) | `subject`, `deleted_at` |
| `assets` | `customer_id` (composite `(customer_id, status)`), `serial_number` (unique) | `manufacturer`, `model`, `deleted_at` |
| `contracts` | `customer_id` (FK), `name` | `status` |
| `locations` | `customer_id` (FK) | `title`, `full_address`, `city`, `state`, `postal_code` |

- One migration, `add_search_indexes`, with a `down()` that drops them.
- Every indexed column is a `varchar(255)` or shorter: at most 1020 bytes in `utf8mb4`, under the InnoDB limit of 3072 bytes.
- A B-tree index can't seek on `LIKE '%term%'`. The `customer_id` index narrows each query to the visible customers' rows. The indexes on the searched columns follow the project rule, and they serve exact and prefix lookups on those columns elsewhere (for example, the filters of the views spec).

### D6. Input normalisation and validation

`SearchRequest` (`Http/Request/Search/`) turns the input into its internal form in `prepareForValidation()`:

- `entities` as a string → `explode` on `,`, trim each value, drop empty values;
- `entities` as an array → kept as it is;
- in both cases, duplicates are removed. An absent or empty `entities` is not set here, and `toQuery()` falls back to every entity.

Rules: `q` → `required|string`; `limit` → `nullable|integer|between:1,50`; `entities` → `nullable|array`; `entities.*` → `string` and `Rule::enum(SearchableEntity::class)`. `q` is trimmed by Laravel's global `TrimStrings` middleware, and the `SearchTerm` value object trims again, so the invariant doesn't depend on HTTP.

`toQuery()` builds `GlobalSearch` with the user id from `CurrentAccessToken`, like `MeRequest`.

### D7. Value objects and messages

| Class | Place | Invariant |
|---|---|---|
| `SearchTerm` | `Domain/ValueObject/Search/` | trimmed, not empty (`InvalidSearchTermException`, 422) |
| `SearchLimit` | `Domain/ValueObject/Search/` | 1 to 50 (`InvalidSearchLimitException`, 422); `DEFAULT = 5` |
| `SearchableEntity` | `Domain/ValueObject/Search/` | backed enum |
| `VisibleCustomers` | `Domain/ValueObject/Customer/` | unique ids, sorted |

- `Search` is not an aggregate. `Domain/ValueObject/Search/` and `Domain/Exception/Search/` are an agreed exception to the "grouped by aggregate" rule. `AGENTS.md` records it in the same change.
- `GlobalSearch` (query): `final readonly`, with `userId: int`, `term: string`, `limit: int` and `entities: list<string>`. It carries primitives, and the handler builds the value objects, as the existing handlers do.
- `SearchCriteria` (`Application/Query/GlobalSearch/`): `final readonly`, with `SearchTerm`, `SearchLimit` and `VisibleCustomers`.

### D8. Handler and views

`GlobalSearchHandler::handle(GlobalSearch $query): QueryResponse` works in five steps:

1. Load the user with `UserRepository` (`UserNotFoundException` if it's missing).
2. Resolve `VisibleCustomers` with `VisibleCustomersResolver`.
3. Build `SearchCriteria` from the query.
4. For each requested entity, in canonical order, ask its searcher. If the customers are empty, it uses an empty result instead.
5. Return `QueryResponse::from(data: …, meta: Meta::from(values: ['query' => …, 'limit_per_entity' => …]))`.

Views (`final readonly`, a named constructor and `toArray()`):

- `ModelView/Ticket/TicketSearchResultView`, `ModelView/Asset/AssetSearchResultView`, `ModelView/Contract/ContractSearchResultView`, `ModelView/Location/LocationSearchResultView`: the fields in the API contract, built from primitives by the Eloquent searchers.
- `ModelView/Search/EntitySearchResultView`: `results`, `total` and `has_more`, built from an `EntitySearchResult` and the limit.

`EntitySearchResult` (`Application/Query/GlobalSearch/`) holds `list<array<string, scalar|null>>` results and `total`. It is plain arrays so that it can be cached safely.

### D9. Result cache

- `CachedEntitySearcher` (`Infrastructure/Cache/`) decorates each `EntitySearcher`. The provider wraps the four searchers with `$this->app->extend()`. The handler and the searchers know nothing about the cache.
- TTL: `config/search.php` → `cache_ttl_seconds`, from `SEARCH_CACHE_TTL_SECONDS`, default `300` (5 minutes).
- Key: `search:{entity}:` followed by `sha256` of `{visible customer ids, sorted}|{lower-cased term}|{limit}`.
  - The key contains the **visible customers**, not the user. Two users with the same visibility share entries, and a user never receives another customer's data.
  - The term is lower-cased because the collation already treats case as equal.
- What is stored: the plain array form of `EntitySearchResult` (no objects).
- Each entity is cached separately. `entities=tickets` and `entities=tickets,assets` reuse the tickets entry.
- No invalidation: nothing in the API writes to these tables yet. Results can be up to 5 minutes stale, which is accepted.
- The store is the configured one (`CACHE_STORE=database`). No tags are needed.

### D10. Architecture

```
app/LogisContext/
├── Domain/
│   ├── Model/Customer/            VisibleCustomersResolver; CustomerRepository::subAccountsOf()
│   ├── Model/User/                User::canViewSubAccounts()
│   ├── ValueObject/Customer/      VisibleCustomers
│   ├── ValueObject/Search/        SearchableEntity, SearchTerm, SearchLimit
│   └── Exception/Search/          InvalidSearchTermException, InvalidSearchLimitException
├── Application/
│   ├── Query/GlobalSearch/        GlobalSearch, GlobalSearchHandler, EntitySearcher, EntitySearchers,
│   │                              SearchCriteria, EntitySearchResult, EntitySearcherNotRegisteredException
│   └── ModelView/                 Ticket|Asset|Contract|Location/<X>SearchResultView, Search/EntitySearchResultView
└── Infrastructure/
    ├── Http/Controller/           SearchController::search()
    ├── Http/Request/Search/       SearchRequest
    ├── Persistence/<Aggregate>/   Eloquent<X>Searcher; scopes on <X>Model; UserModel/UserMapper gain the flag;
    │                              EloquentCustomerRepository::subAccountsOf()
    ├── Search/                    NewestMatches: the count, order, limit and mapping shared by the searchers
    ├── Cache/                     CachedEntitySearcher
    └── Providers/                 LogisServiceProvider: tags, giveTagged, extend, resolver binding
app/SharedContext/Infrastructure/Persistence/LikePattern.php
config/search.php
database/migrations/               add_can_view_sub_accounts_to_users_table, add_search_indexes
routes/api.php                     GET v1/search (auth:sanctum + RotateAccessTokenMiddleware)
docs/openapi/openapi.yaml          /search path, parameters, responses
```

Deptrac holds: the port and the registry are in Application and depend only on Domain. The adapters, the cache decorator and `LikePattern` are in Infrastructure.

### D11. Seed data

- `UserFactory` defaults to `can_view_sub_accounts = false`, with a `withSubAccountAccess()` state.
- `DemoDataSeeder`:
  - the demo user `demo@logis.test` gets `can_view_sub_accounts = true`;
  - a second user, `limited@logis.test` (password `password`), belongs to the same parent customer without the permission, so both cases can be tried by hand.

## Code style: an entity searcher

```php
final class EloquentAssetSearcher implements EntitySearcher
{
    public function entity(): SearchableEntity
    {
        return SearchableEntity::Assets;
    }

    public function search(SearchCriteria $criteria): EntitySearchResult
    {
        $query = AssetModel::query()
            ->visibleTo($criteria->customers)
            ->matching($criteria->term);

        $total = $query->clone()->count();

        if ($total === 0) {
            return EntitySearchResult::empty();
        }

        $results = $query
            ->orderByDesc(column: 'id')
            ->limit(value: $criteria->limit->value())
            ->get()
            ->map(callback: fn (AssetModel $asset): array => AssetSearchResultView::from(
                id: $asset->id,
                serialNumber: $asset->serial_number,
                manufacturer: $asset->manufacturer,
                model: $asset->model,
                itemName: $asset->item_name,
                status: $asset->status->value,
            )->toArray())
            ->values()
            ->all();

        return EntitySearchResult::from(results: $results, total: $total);
    }
}
```

```php
/** @param Builder<AssetModel> $query */
public function scopeMatching(Builder $query, SearchTerm $term): void
{
    $pattern = LikePattern::contains(value: $term->value());

    $query->where(column: function (Builder $query) use ($pattern): void {
        $query->where(column: 'serial_number', operator: 'like', value: $pattern)
            ->orWhere(column: 'manufacturer', operator: 'like', value: $pattern)
            ->orWhere(column: 'model', operator: 'like', value: $pattern);
    });
}
```

The style of `AGENTS.md` applies: named arguments everywhere, except on scope calls such as `->visibleTo($customers)`, which are magic methods resolved through `__call` and stay positional; `final` classes; `declare(strict_types=1)`; no comments; explicit `if` guards.

## Commands

```
make migrate   # apply the two new migrations
make fresh     # recreate the development database with the new seed data
make qa        # Pint (read-only), Larastan, Deptrac
make docs      # validate the OpenAPI spec and regenerate docs/openapi.json
make test      # PHPUnit against logis_testing
make check     # qa + docs-lint + test, required before every commit
```

## Testing strategy

**Unit** (`tests/Unit/LogisContext/…`, mirroring `app/`):

- `SearchTerm` (trim, empty and whitespace-only rejected), `SearchLimit` (bounds 1 and 50, 0 and 51 rejected), `VisibleCustomers` (dedupe, sorting, empty).
- `User::canViewSubAccounts()`.
- `VisibleCustomersResolver`: no customer, without the permission, with the permission, and a customer without sub-accounts. It uses a new `InMemoryCustomerRepository` in `tests/Unit/LogisContext/Domain/Model/Customer/`.
- `EntitySearchers`: every `SearchableEntity` case resolves; a missing searcher and a duplicate searcher throw `EntitySearcherNotRegisteredException`.
- `GlobalSearchHandler`, instantiated directly with `InMemoryUserRepository`, `InMemoryCustomerRepository` and `InMemoryEntitySearcher` (`tests/Unit/LogisContext/Application/Query/GlobalSearch/`):
  - only the requested entities, in canonical order;
  - meta values;
  - `has_more`;
  - no searcher called when the user has no customer;
  - `UserNotFoundException`.
- The four `<X>SearchResultView`s and `EntitySearchResultView`.
- `LikePattern::contains()` escapes `\`, `%` and `_`.

**Integration** (`tests/Feature/LogisContext/Infrastructure/…`, `RefreshDatabase`):

- Each `Eloquent<X>Searcher`:
  - matches on every searched column, case-insensitively;
  - never returns rows of customers outside the list;
  - applies the extra filters (soft-deleted tickets and assets, inactive contracts);
  - orders by `id DESC` and respects the limit;
  - `total` counts every match;
  - a literal `%` or `_` in the term doesn't act as a wildcard.
- `CachedEntitySearcher`:
  - a second identical search runs no queries;
  - different visible customers, term, limit or entity give a different entry;
  - a term that differs only in case hits the same entry.
- `EloquentCustomerRepository::subAccountsOf()`.
- Schema: every index in D5 exists (`SchemaTest`).

**Feature** (`tests/Feature/LogisContext/Search/SearchEndpointTest.php`):

- 401 without a token. 422 for:
  - a missing `q`;
  - a blank `q`;
  - `q` sent as an array;
  - `limit` 0, 51 or not a number;
  - an unknown entity, in both formats.
- `entities=tickets,assets` and `entities[]=tickets&entities[]=assets` give the same response. Duplicates are ignored. No `entities` returns all four.
- Authorization:
  - the user's own customer is found;
  - sub-accounts appear only with `can_view_sub_accounts`;
  - an unrelated customer never appears;
  - a user without a customer gets 200 with empty entities.
- Response format: `results`, `total`, `has_more`, `meta.query` (trimmed), `meta.limit_per_entity`, `meta.execution_time_ms`, and `X-Refreshed-Token`.
- The number of queries (`CountsQueries`) is the same with 1 match and with 30 matches per entity.
- `DemoDataSeeder` creates `limited@logis.test` without the permission, and the demo user with it.

The existing tests keep passing.

## Boundaries

- **Always:**
  - derive the customers from the authenticated user, never from the request;
  - bind every value and escape `LIKE` wildcards;
  - one searcher per entity in the aggregate's `Persistence/` folder;
  - update `docs/openapi/openapi.yaml` in the same change and run `make docs`;
  - run `make check` before each commit;
  - one logical change per commit (schema and seed, domain, application, infrastructure and endpoint, docs).
- **Ask first:**
  - adding Scout or any other dependency;
  - composite or FULLTEXT indexes;
  - result fields beyond the API contract;
  - changing the searched columns or the extra filters;
  - cache invalidation.
- **Never:**
  - raw SQL built from input;
  - accept customer ids or permissions from the client;
  - add `search()` to the aggregate repositories;
  - cache by user id alone;
  - touch `logis-test/`.

## Out of scope

- Paginated "View All" pages per entity. `has_more` tells the client that more exist.
- Cache invalidation on writes, because there are no write endpoints for these entities.
- Scout, FULLTEXT or ngram indexes (a documented improvement, D3).
- Highlighting and relevance ranking.
- `ARCHITECTURE.md` as a whole. This spec only requires the Controller → Service → Repository mapping of D2 to be recorded when that document is written.

## Success criteria

1. `GET /api/v1/search` answers as described in the API contract. Both `entities` formats give identical responses.
2. A user never receives data of a customer outside their visible customers (D1). This is proven by feature tests with an unrelated customer and with sub-accounts, with and without the permission.
3. No query is built by concatenating input. `q=%` and `q=_` match only literally.
4. The number of queries of a search is fixed, whatever the number of matches. A repeated search within 5 minutes runs no search queries.
5. The indexes in D5 exist after `make migrate`, and `down()` removes them.
6. Adding a `SearchableEntity` case without a searcher fails the `EntitySearchers` test.
7. `docs/openapi/openapi.yaml` documents the endpoint, `make docs` passes, and `make check` is green.

## Resolved decisions

- **R1.** The permission is the boolean `users.can_view_sub_accounts`, default `false`.
- **R2.** The legacy status filters are kept: only active contracts. Assets and tickets exclude only soft-deleted rows.
- **R3.** The search is `LIKE '%term%'`, filtered by visible customers. There is no FULLTEXT index.
- **R4.** Every filtered column has its own single-column index, and no composite index is added for search (D5).
- **R5.** A user without a customer gets 200 with empty results.
- **R6.** `Domain/ValueObject/Search/` and `Domain/Exception/Search/` are an agreed exception to the per-aggregate layout.
- **R7.** `q` is only trimmed, with no length limit. `limit` is 1 to 50, default 5.
- **R8.** `entities` is accepted as a comma-separated string and as an array, and is always handled as an array internally. An unknown value returns 422.

## Open questions

None.
