# PLAN — Global Search

Spec: `specs/SPEC — Global Search.md` (Status: approved)
Status: approved

## Overview

This plan builds `GET /api/v1/search`: the visibility of customers, the search value objects, the handler with its searcher registry, one Eloquent searcher per entity, the indexes and the result cache.

The work is sliced vertically. After the foundation (who can see what, and the search vocabulary), the first slice takes **tickets** from the database to the HTTP response. Assets, contracts and locations are then added one per task, each by adding a case to `SearchableEntity`, a searcher, a view and one tag line. This is exactly the extension path the spec promises (D2), so the plan proves it three times. Indexes and cache come last, because they change performance and not behaviour, and the feature tests already written guard them.

Every task ends with its tests and `make check`, and becomes one commit. The mandatory post-implementation flow from `AGENTS.md` runs at each checkpoint: `make qa`, `code-simplify`, `make test`, `code-review-and-quality`.

## Dependency graph

```
T0 branch feat/global-search + commit the spec and the branch convention
 ├─ T1 users.can_view_sub_accounts → User::canViewSubAccounts(), factory state, limited demo user
 │   └─ T2 VisibleCustomers + VisibleCustomersResolver + CustomerRepository::subAccountsOf()
 └─ T3 SearchTerm, SearchLimit, SearchableEntity (only Tickets)
     └─ T4 GlobalSearch query + handler + EntitySearcher port + EntitySearchers registry ← T2
         └─ T5 LikePattern + TicketModel scopes + EloquentTicketSearcher
             └─ T6 SearchRequest + SearchController + route + OpenAPI  (tickets end to end)
                 ├─ T7 assets     ┐
                 ├─ T8 contracts  ├─ each: enum case + scopes + searcher + view + tag + OpenAPI enum
                 └─ T9 locations  ┘
                     ├─ T10 add_search_indexes migration
                     └─ T11 CachedEntitySearcher + config/search.php
```

## Architecture decisions

These come from the spec and are not decided again here:

- The customers come from the authenticated user through `VisibleCustomersResolver`, never from the request (D1).
- `SearchableEntity` is the whitelist. `EntitySearcher` is an Application port, with one Eloquent adapter per entity in `Persistence/<Aggregate>/`. `EntitySearchers` indexes the tagged searchers and fails fast if the enum and the searchers don't match one to one (D2).
- The search is `LIKE '%term%'` joined with `OR`, inside `customer_id IN (…)`, with `LikePattern::contains()` escaping `\`, `%` and `_`, ordered by `id DESC`. It runs a `COUNT` plus a `SELECT … LIMIT`, and the `SELECT` only when `total > 0` (D3).
- The searched columns live in `scopeMatching()`, and the customer filter in `scopeVisibleTo()`. Scope calls are magic, so their arguments stay positional (D4, code style).
- Every filtered column gets its own single-column index. No composite index is added (D5).
- The cache is a decorator applied with `$this->app->extend()`. The key is per entity, visible customers, lower-cased term and limit, and the cache stores plain arrays (D9).

### The enum grows with the searchers

`SearchableEntity` starts in T3 with the single case `Tickets`. The registry requires exactly one searcher per case, so adding all four cases up front would break the container until the last searcher existed. Each later task adds its case together with its searcher. The response's canonical order is the order of the enum cases, so the cases are added in the order `tickets`, `assets`, `contracts`, `locations`.

### Container wiring (end state)

```php
$this->app->tag(abstracts: [
    EloquentTicketSearcher::class,
    EloquentAssetSearcher::class,
    EloquentContractSearcher::class,
    EloquentLocationSearcher::class,
], tags: 'entity_searchers');

$this->app->when(concrete: EntitySearchers::class)
    ->needs(abstract: '$searchers')
    ->giveTagged(tag: 'entity_searchers');
```

T11 adds one `extend()` per searcher to wrap it in `CachedEntitySearcher`. Tagged services are resolved through `make()`, so the extenders apply to them too.

## Tasks

### Phase 0 — Branch

#### Task 0: Create the branch and commit the documents

**Description:** Start the work on a branch that follows the new branch convention. Commit the pending documents first, each as its own logical change.

**Acceptance criteria:**
- [ ] The branch `feat/global-search` is created from `main`.
- [ ] `docs(agents): add branch naming convention` commits the `AGENTS.md` change.
- [ ] `docs(specs): specify the global search endpoint` commits the spec and this plan.

**Verification:** `make check` before each commit. `git log --oneline` shows both commits on the branch.

**Dependencies:** None

**Files:** `AGENTS.md`, `specs/SPEC — Global Search.md`, `specs/PLAN — Global Search.md`

**Scope:** XS

### Phase 1 — Who can see what

#### Task 1: Sub-account permission on users

**Description:** Add the boolean `users.can_view_sub_accounts` and carry it to the domain `User`, the factory and the demo data (D1, D11).

**Acceptance criteria:**
- [ ] The migration `2026_09_25_100800_add_can_view_sub_accounts_to_users_table` adds `can_view_sub_accounts` (boolean, NOT NULL, default `false`), and its `down()` drops it.
- [ ] `UserModel` casts it to `bool`. `UserMapper` passes it to `User`, which gains `canViewSubAccounts(): bool`.
- [ ] `UserFactory` defaults to `false` and has a `withSubAccountAccess()` state.
- [ ] `DemoDataSeeder` gives `demo@logis.test` the permission, and creates `limited@logis.test` (password `password`) in the same parent customer without it.

**Verification:**
- Unit tests for `User` and `UserMapper`.
- `EloquentUserRepositoryTest` maps the flag.
- The seeder test asserts both users.
- `make fresh`, then `make check`.

**Dependencies:** T0

**Files:** migration, `UserModel`, `UserMapper`, `User`, `UserFactory`, `DemoDataSeeder`, tests. Every change is a one-liner.

**Scope:** M · **Commit:** `feat(search): add the sub-account permission to users`

#### Task 2: Resolve the visible customers of a user

**Description:** Add the value object that lists the customers a user may see, the domain service that decides it, and the repository method it needs (D1).

**Acceptance criteria:**
- [ ] `Domain/ValueObject/Customer/VisibleCustomers` is built from `CustomerId`s. It removes duplicates, keeps the ids sorted, and exposes `isEmpty()` and `values(): list<int>`.
- [ ] `CustomerRepository::subAccountsOf(CustomerId $id): list<Customer>` is implemented in `EloquentCustomerRepository` (eager loading `parent`) and in a new `tests/Unit/LogisContext/Domain/Model/Customer/InMemoryCustomerRepository`.
- [ ] `Domain/Model/Customer/VisibleCustomersResolver::for(User $user): VisibleCustomers` returns:
  - an empty list when the user has no customer;
  - only the user's customer without the permission;
  - the customer plus its sub-accounts with the permission.

**Verification:**
- Unit tests for `VisibleCustomers`, and for the resolver in all four cases (including a customer without sub-accounts).
- An integration test for `subAccountsOf()`: only direct sub-accounts, other customers excluded, and a fixed number of queries.
- `make check`.

**Dependencies:** T1

**Files:** `VisibleCustomers`, `VisibleCustomersResolver`, `CustomerRepository`, `EloquentCustomerRepository`, `InMemoryCustomerRepository`, tests

**Scope:** M · **Commit:** `feat(search): resolve the customers a user can see`

### Checkpoint: Visibility
- [ ] `make check` is green, and `make fresh` seeds both demo users.
- [ ] Post-implementation flow. Human review before continuing.

### Phase 2 — Search vocabulary and application layer

#### Task 3: Search value objects

**Description:** Add the domain vocabulary of the search, and record the agreed folder exception in `AGENTS.md` (D7, R6).

**Acceptance criteria:**
- [ ] `Domain/ValueObject/Search/`:
  - `SearchTerm::fromString()` trims, and rejects an empty result with `InvalidSearchTermException`;
  - `SearchLimit::fromInt()` accepts 1 to 50, rejects other values with `InvalidSearchLimitException`, and has `DEFAULT = 5`;
  - `SearchableEntity` is a backed enum with the single case `Tickets = 'tickets'`.
- [ ] Both exceptions live in `Domain/Exception/Search/`, extend `InvalidValueException` (422) and are built with named constructors.
- [ ] `AGENTS.md` notes that `Search` is a documented exception to the per-aggregate folder rule.

**Verification:** unit tests for both value objects (bounds, trim, whitespace only). `make check`.

**Dependencies:** T0

**Files:** 3 value objects, 2 exceptions, `AGENTS.md`, tests

**Scope:** S · **Commit:** `feat(search): add the search value objects`

#### Task 4: Query, handler and searcher registry

**Description:** Build the whole Application side against the `EntitySearcher` port, tested with in-memory doubles and no database (D2, D7, D8).

**Acceptance criteria:**
- [ ] `Application/Query/GlobalSearch/` contains:
  - `GlobalSearch` (`userId`, `term`, `limit`, `entities: list<string>`);
  - `EntitySearcher`;
  - `SearchCriteria` (`term`, `limit`, `customers`);
  - `EntitySearchResult` (`empty()`, `from(results:, total:)`, `toArray()`, `fromArray()`);
  - `EntitySearchers`, which throws `EntitySearcherNotRegisteredException` from its constructor when a case has no searcher or has two;
  - `GlobalSearchHandler`.
- [ ] The handler loads the user (`UserNotFoundException`) and resolves the visible customers. It asks only the requested entities, in the order of the enum cases. When the list of customers is empty, it doesn't call any searcher and returns empty results. It returns `meta.query` and `meta.limit_per_entity`.
- [ ] `ModelView/Search/EntitySearchResultView` renders `results`, `total` and `has_more` (`total > limit`).

**Verification:**
- The `GlobalSearchHandler` unit test uses `InMemoryUserRepository`, `InMemoryCustomerRepository` and a new `InMemoryEntitySearcher` that records its calls.
- Unit tests for `EntitySearchers` (resolves, missing, duplicate) and for `EntitySearchResultView`.
- `make check`: Deptrac confirms that the Application layer only depends on Domain.

**Dependencies:** T2, T3

**Files:** 7 Application classes, 1 view, 1 exception, tests and doubles. They are small, uniform files, and splitting them would leave a handler with no registry.

**Scope:** M · **Commit:** `feat(search): add the global search query and its searcher registry`

### Phase 3 — Tickets end to end

#### Task 5: Ticket searcher

**Description:** Add the first adapter of the port, with the scopes it combines and the shared `LIKE` escaping (D3, D4).

**Acceptance criteria:**
- [ ] `SharedContext/Infrastructure/Persistence/LikePattern::contains()` escapes `\`, `%` and `_`, and wraps the result in `%…%`.
- [ ] `TicketModel` has `scopeVisibleTo(Builder, VisibleCustomers)` and `scopeMatching(Builder, SearchTerm)`, the latter over `subject`, `repair_id` and `incident_number`.
- [ ] `Persistence/Ticket/EloquentTicketSearcher`:
  - counts first, and returns `EntitySearchResult::empty()` when there are no matches;
  - otherwise eager loads `status`, orders by `id DESC`, applies the limit and maps each row through `ModelView/Ticket/TicketSearchResultView`.
- [ ] `LogisServiceProvider` tags the searcher as `entity_searchers` and injects the tag into `EntitySearchers` with `giveTagged()`.

**Verification:**
- A unit test for `LikePattern` and a view test.
- The integration test `EloquentTicketSearcherTest` checks:
  - a match on each column, case-insensitively;
  - other customers are excluded;
  - soft-deleted tickets are excluded;
  - order and limit;
  - `total`;
  - a literal `%` and `_`;
  - a fixed number of queries.
- `make check`: confirm that Larastan and the `NamedArgumentsRule` accept positional scope calls (see Risks).

**Dependencies:** T4

**Files:** `LikePattern`, `TicketModel`, `EloquentTicketSearcher`, `TicketSearchResultView`, `LogisServiceProvider`, tests

**Scope:** M · **Commit:** `feat(search): search tickets by subject and identifiers`

#### Task 6: The search endpoint

**Description:** Expose the search over HTTP, with its FormRequest, controller, route and OpenAPI documentation. At this point it only searches tickets (D6, API contract).

**Acceptance criteria:**
- [ ] `Http/Request/Search/SearchRequest`:
  - `prepareForValidation()` turns a comma-separated `entities` into an array, trims the values and drops empty ones and duplicates, and keeps the array form as it is;
  - rules as in D6;
  - `toQuery()` builds `GlobalSearch` from `CurrentAccessToken`, falling back to every `SearchableEntity` case and to `SearchLimit::DEFAULT`.
- [ ] `SearchController::search()` asks the query and returns `ApiResponse::fromQueryResponse(response:, message: 'Search results')`.
- [ ] `GET v1/search` sits in the `auth:sanctum` + `RotateAccessTokenMiddleware` group.
- [ ] `docs/openapi/openapi.yaml` documents the path:
  - the `q`, `limit` and `entities` parameters (string or array, enum);
  - the 200 schema and the 401, 422 and 429 responses;
  - bearer security and `X-Refreshed-Token`.

**Verification:**
- `SearchEndpointTest` covers:
  - 401;
  - every 422 case in the spec;
  - both `entities` formats giving an identical response, and duplicates;
  - the response format and meta, and `X-Refreshed-Token`;
  - authorization: own customer, sub-accounts with and without the permission, an unrelated customer, a user without a customer.
- `make docs`, then `make check`. A manual call in Swagger UI with `demo@logis.test`.

**Dependencies:** T5

**Files:** `SearchRequest`, `SearchController`, `routes/api.php`, `openapi.yaml`, `SearchEndpointTest`

**Scope:** M · **Commit:** `feat(search): expose the global search endpoint`

### Checkpoint: Tickets end to end
- [ ] `GET /api/v1/search?q=…` returns tickets for `demo@logis.test`, and fewer for `limited@logis.test`.
- [ ] `make check` is green. Post-implementation flow. Human review before adding entities.

### Phase 4 — The other entities

Tasks 7 to 9 follow the same template. Each one:

- adds its case to `SearchableEntity` (in canonical order);
- adds `scopeVisibleTo` and `scopeMatching` to the model;
- adds the searcher and its `<X>SearchResultView`;
- adds one line to the tag list;
- adds the value to the OpenAPI enum and its result schema;
- extends the feature test.

#### Task 7: Assets

**Acceptance criteria:**
- [ ] `SearchableEntity::Assets`. `AssetModel::scopeMatching()` covers `serial_number`, `manufacturer` and `model`.
- [ ] `EloquentAssetSearcher` returns assets of every `status` and excludes soft-deleted ones. `AssetSearchResultView` has the contract fields.
- [ ] The feature test checks that the response lists `tickets` before `assets`, whatever the order of the request.

**Verification:** `EloquentAssetSearcherTest` (the same cases as tickets, plus every status), the view test, the updated `EntitySearchers` test, `make docs`, `make check`.

**Dependencies:** T6 · **Scope:** M · **Commit:** `feat(search): search assets by serial number, manufacturer and model`

#### Task 8: Contracts

**Acceptance criteria:**
- [ ] `SearchableEntity::Contracts`. `ContractModel` gains `scopeMatching()` over `name`, and `scopeActive()`.
- [ ] `EloquentContractSearcher` returns only active contracts.

**Verification:** `EloquentContractSearcherTest` (including inactive contracts being excluded), the view test, `make docs`, `make check`.

**Dependencies:** T7 · **Scope:** M · **Commit:** `feat(search): search active contracts by name`

#### Task 9: Locations

**Acceptance criteria:**
- [ ] `SearchableEntity::Locations`. `LocationModel::scopeMatching()` covers `title`, `full_address`, `city`, `state` and `postal_code`.
- [ ] `EloquentLocationSearcher` works with no extra filter. Null address fields never match, and never break the query.
- [ ] With no `entities`, the response contains the four entities in canonical order.

**Verification:**
- `EloquentLocationSearcherTest` and the view test.
- The feature test checks:
  - the four entities with no `entities` parameter;
  - the same number of queries with 1 and with 30 matches per entity (`CountsQueries`).
- `make docs`, then `make check`.

**Dependencies:** T8 · **Scope:** M · **Commit:** `feat(search): search locations by title and address`

### Checkpoint: All entities
- [ ] Success criteria 1, 2, 3 and 6 of the spec are met.
- [ ] `make check` is green. Post-implementation flow. Human review.

### Phase 5 — Performance

#### Task 10: Search indexes

**Description:** Add a single-column index for every column the search filters on that doesn't have one yet (D5).

**Acceptance criteria:**
- [ ] The migration `2026_09_25_100900_add_search_indexes` adds:
  - `tickets`: `subject`, `deleted_at`;
  - `assets`: `manufacturer`, `model`, `deleted_at`;
  - `contracts`: `status`;
  - `locations`: `title`, `full_address`, `city`, `state`, `postal_code`.
- [ ] `down()` drops exactly those indexes, and existing indexes are untouched.

**Verification:** `SchemaTest` asserts every new index (`Schema::getIndexes()`). `make migrate`, `make fresh`, then a manual `migrate:rollback --step=1` and `make migrate`. `make check`.

**Dependencies:** T9 (so that the whole list of filtered columns is final) · **Files:** migration, `SchemaTest` · **Scope:** S · **Commit:** `perf(search): index the searched columns`

#### Task 11: Result cache

**Description:** Cache each entity's result for 5 minutes behind a decorator. The handler and the searchers don't change (D9).

**Acceptance criteria:**
- [ ] `config/search.php` defines `cache_ttl_seconds`, read from `SEARCH_CACHE_TTL_SECONDS` with default `300`, and the variable is added to `.env.example`.
- [ ] `Infrastructure/Cache/CachedEntitySearcher`:
  - wraps an `EntitySearcher` and delegates `entity()`;
  - caches `EntitySearchResult::toArray()` under `search:v1:{entity}:` followed by `sha256` of `{sorted customer ids}|{lower-cased term}|{limit}`.
- [ ] `LogisServiceProvider` wraps each of the four searchers with `extend()`, and the TTL is injected from the config.

**Verification:**
- `CachedEntitySearcherTest` checks:
  - a second identical call runs 0 queries;
  - different customers, term, limit or entity miss the cache;
  - a term differing only in case hits it.
- A feature test checks that a repeated request runs no search queries.
- `make check`.

**Dependencies:** T9 · **Files:** `config/search.php`, `.env.example`, `CachedEntitySearcher`, `LogisServiceProvider`, tests · **Scope:** M · **Commit:** `perf(search): cache search results for five minutes`

### Checkpoint: Complete
- [ ] Every success criterion in the spec is met.
- [ ] `make fresh`, `make docs` and `make check` are green.
- [ ] Post-implementation flow. Human review before opening the pull request from `feat/global-search`.

## Risks and mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| `NamedArgumentsRule` or Larastan resolves scope calls (`->visibleTo(...)`) as real methods through Larastan's model extension, and then demands named arguments, which break at runtime | Medium | Check it in T5, with the first scope. If the rule disagrees, fix the rule so that scope calls count as magic (a `chore(qa)` commit), instead of silencing it. |
| `extend()` isn't applied to searchers resolved through `giveTagged()` | Medium | The feature test in T11 goes through the real container and asserts 0 search queries on a repeated request. |
| The `LIKE` escaping depends on MySQL's default escape character `\` | Low | The integration tests run against MySQL with literal `%`, `_` and `\` terms. |
| Case-insensitive matching depends on the `utf8mb4_unicode_ci` collation | Low | `config/database.php` sets it, and the searcher tests assert case-insensitive matches. |
| Tests run with `CACHE_STORE=array`, and production with `database` | Low | The decorator only uses the `Repository` contract, and stores plain arrays that any store can serialise. |
| A searcher is added without its enum case, or the reverse | Low | `EntitySearchers` fails at construction, and its unit test covers both cases. |

## Parallelisation

- T1→T2 and T3 are independent after T0.
- T7, T8 and T9 are independent in code, but each edits the enum, the tag list, `openapi.yaml` and the feature test. They are done in sequence to keep the canonical order and avoid conflicts.
- T10 and T11 are independent of each other after T9.

## Open questions

None.
