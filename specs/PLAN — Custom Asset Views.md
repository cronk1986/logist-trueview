# PLAN — Custom Asset Views

Spec: `specs/SPEC — Custom Asset Views.md` (Status: approved)
Status: approved

## Overview

This plan builds the seven `/api/v1/views` endpoints on the data model of the core spec:

- the shared 403 and 504 exceptions;
- the field catalog and the validation of view definitions;
- the read side (list and details), then the write side (create, update, delete, set default);
- execution with one Eloquent builder per view type, the indexes, the cache and the timeout.

The work is sliced vertically wherever the architecture allows it:

- list and details go end to end before any write;
- each write action (create, update, delete, set default) is its own slice, from the entity to the endpoint.

Execution is the exception. `ViewBuilders` fails fast unless every `ViewType` has a builder (D7), and `ViewType` already has four cases. So the four builders land before the `execute` endpoint is wired (see "The builders land before the endpoint"). Indexes, cache and timeout come last: they change performance and robustness, not behaviour, and the feature tests already written guard them.

Every task ends with its tests and `make check`, and becomes one commit. The mandatory post-implementation flow from `AGENTS.md` runs at each checkpoint: `make qa`, `code-simplify`, `make test`, `code-review-and-quality`.

## Dependency graph

```
T0 branch feat/custom-views + commit the spec and the plan
 ├─ T1 ForbiddenException (403) + TimeoutException (504, logged) + AGENTS.md
 └─ T2 ViewFieldType, FilterOperator, SortDirection, ViewField, ViewFieldCatalog; ViewFilter holds a FilterOperator
     └─ T3 ViewDefinition, ViewSort, ViewPageSize (catalog validation, date ranges)
         └─ T4 CustomView invariant + mapper CorruptedRecordException + factory + seed views
             └─ T5 ViewPolicy + list/get queries + repository findOwnedByOrPublicIn  ← T1
                 └─ T6 GET /views, GET /views/{id} + OpenAPI                      (read side end to end)
                     ├─ T7 create: entity, repository save/findByOwnerAndTitle, command, GetCustomViewByTitle
                     │   └─ T8 POST /views + OpenAPI
                     │       ├─ T9  PUT /views/{id}              (update, immutable type)
                     │       ├─ T10 DELETE /views/{id}
                     │       └─ T11 POST /views/{id}/set-default (lockForUpdate)
                     └─ T12 ViewBuilder port, ViewBuilders, ViewExecutionCriteria, ExecuteCustomView handler
                         └─ T13 FieldMapping + ViewFilterApplier + EloquentAssetViewBuilder (first page)
                             └─ T14 ViewPaginator: sort, cursorPaginate, cursor validation, q
                                 ├─ T15 tickets builder ┐
                                 └─ T16 contracts + locations builders ┘
                                     └─ T17 POST /views/{id}/execute + OpenAPI  (wires ViewBuilders)
                                         ├─ T18 add_view_indexes migration
                                         ├─ T19 CachedViewBuilder + config/views.php
                                         └─ T20 StatementTimeout → 504
```

## Architecture decisions

These come from the spec and are not decided again here:

- The whitelist is the domain `ViewFieldCatalog` per `ViewType`. Field names never reach SQL: each adapter maps them through a static field map (D2, D7).
- Operators and value shapes are questions for their enums (`ViewFieldType::allows()`, `FilterOperator::expectsList()`…). Dates are whole UTC days turned into half-open ranges (D3).
- `ViewPolicy` is a domain service. An invisible view is a 404, and a visible view changed by a non-owner is a 403 (D4).
- `custom_views.id` stays auto-increment. `CustomView::id()` is `?CustomViewId` until the first `save()`, and `POST` answers through `GetCustomViewByTitle` (D5, D6).
- One default per owner and type, enforced by `SetDefaultCustomViewHandler` with `findDefaultOf()` under `lockForUpdate()` (D5).
- `ExecuteCustomView` is a query, even over `POST` (D6).
- Execution uses `cursorPaginate()` ordered by `(sort field, id)`, with pages of 1 to 1000 rows, and validates the cursor against the sort before querying (D7).
- The cache is a decorator applied with `extend()`, keyed by definition and visibility rule (D8). The timeout uses MySQL's `max_execution_time` (D9). There is no limiter of its own (D10).

### The builders land before the endpoint

`ViewBuilders` throws from its constructor when a `ViewType` case has no builder. `ViewType` already has its four cases (core spec), so:

- T12 builds and tests the Application side with an `InMemoryViewBuilder`, without wiring the container;
- T13 to T16 add one adapter at a time, each tested directly as an integration test and tagged as `view_builders`;
- T17 wires `ViewBuilders` with `giveTagged()` and exposes the endpoint, once all four builders exist.

Until T17, nothing resolves `ViewBuilders` from the container, so the partial tag list breaks nothing.

### Container wiring (end state)

```php
$this->app->tag(abstracts: [
    EloquentAssetViewBuilder::class,
    EloquentTicketViewBuilder::class,
    EloquentContractViewBuilder::class,
    EloquentLocationViewBuilder::class,
], tags: 'view_builders');

$this->app->when(concrete: ViewBuilders::class)
    ->needs(abstract: '$builders')
    ->giveTagged(tag: 'view_builders');
```

T19 adds one `extend()` per builder to wrap it in `CachedViewBuilder`, as `registerEntitySearchers()` does today.

## Tasks

### Phase 0 — Branch

#### Task 0: Create the branch and commit the documents

**Description:** Start the work on a branch and commit the spec and this plan.

**Acceptance criteria:**
- [ ] The branch `feat/custom-views` is created from `main`.
- [ ] `docs(specs): specify the custom asset views endpoints` commits the spec and this plan.

**Verification:** `make check` before the commit. `git log --oneline` shows the commit on the branch.

**Dependencies:** None · **Files:** the spec, this plan · **Scope:** XS

### Phase 1 — Foundation

#### Task 1: Forbidden and timeout exceptions

**Description:** Add the two new base classes of the spec, and map them in `ApiExceptionRenderer` (D4, D9).

**Acceptance criteria:**
- [ ] `SharedContext/Domain/Exception/ForbiddenException` (abstract) is mapped to 403, and is not reported.
- [ ] `SharedContext/Domain/Exception/TimeoutException` (abstract) is mapped to 504, and **is** reported: it isn't in `expectedExceptions()`.
- [ ] The response keeps the standard format, with the exception message.
- [ ] The exception table in `AGENTS.md` lists both classes, and notes that `TimeoutException` is logged.

**Verification:** `ApiExceptionRenderer` tests for both statuses, and a test that the timeout is reported and the forbidden one isn't. `make check`.

**Dependencies:** T0 · **Files:** 2 exceptions, `ApiExceptionRenderer`, `AGENTS.md`, tests · **Scope:** S · **Commit:** `feat(shared): add forbidden and timeout exceptions`

#### Task 2: Field catalog and operators

**Description:** Add the domain vocabulary of the whitelist: field types, operators, sort direction, fields and the catalog of each view type (D2, D3).

**Acceptance criteria:**
- [ ] In `Domain/ValueObject/CustomView/`:
  - `ViewFieldType` (`text`, `choice`, `identifier`, `date`) with `allows(FilterOperator): bool`;
  - `FilterOperator` (11 cases) with `expectsList()`, `expectsRange()` and `expectsDays()`;
  - `SortDirection` (`asc`, `desc`);
  - `ViewField` (name, type, allowed values, sortable);
  - `ViewFieldCatalog::for(ViewType)`, with `has()`, `field()` and `sortable()`, and the four tables of D2. The `choice` values come from `AssetStatus::cases()` and `ContractStatus::cases()`.
- [ ] `ViewFilter` holds a `FilterOperator`. An unknown operator raises `InvalidViewFilterException::unknownOperator()` (422).

**Verification:**
- Unit tests for every operator and field-type combination, for the value shapes, and for each catalog (fields, types, sortable flags).
- The existing `ViewFiltersTest` is updated.
- `make check`.

**Dependencies:** T0 · **Files:** 5 value objects, `ViewFilter`, `InvalidViewFilterException`, tests · **Scope:** M · **Commit:** `feat(views): add the field catalog of every view type`

#### Task 3: Validate view definitions

**Description:** Validate columns and filters against the catalog, and add the value objects of execution: sort and page size (D3, D6).

**Acceptance criteria:**
- [ ] `ViewDefinition::from(type:, columns:, filters:)` rejects:
  - an unknown column or filter field;
  - an operator the field type doesn't allow;
  - a value of the wrong shape or type: text of 1 to 255 characters, a valid choice, an identifier ≥ 1, a `Y-m-d` date;
  - more than 20 filters, and `in`/`not_in` lists outside 1 to 100;
  - a `between` out of order, and `in_last_days` outside 0 to 365.
- [ ] Each rejection is a semantic `InvalidValueException` (422) whose message names the column or filter.
- [ ] Date filters expose their UTC boundaries as `CarbonImmutable`, following the table in D3. `in_last_days` is computed from `CarbonImmutable::now()->utc()`.
- [ ] `ViewSort::from(catalog, field, direction)` is `id desc` by default, and rejects unknown or non-sortable fields (`InvalidViewSortException`).
- [ ] `ViewPageSize::fromInt()` accepts 1 to 1000, has `DEFAULT = 50`, and throws `InvalidViewPageSizeException` otherwise.

**Verification:** unit tests for every rule in the list, and for the date boundaries (`gt`, `lte`, `between` and `in_last_days 0` around midnight UTC, with `freezeTime()`). `make check`.

**Dependencies:** T2 · **Files:** 3 value objects, 3 exceptions, tests · **Scope:** M · **Commit:** `feat(views): validate view definitions against the catalog`

#### Task 4: A view always fits the catalog

**Description:** Make the catalog an invariant of `CustomView`, and bring the stored data (factory and seed) in line with it (D5, D14).

**Acceptance criteria:**
- [ ] The `CustomView` constructor builds a `ViewDefinition`, so an invalid combination can't exist. `id` becomes `?CustomViewId`.
- [ ] `CustomViewMapper` turns a catalog violation into `CorruptedRecordException` (a logged 500).
- [ ] `CustomViewFactory::ofType()` produces catalog-valid columns and filters for every type.
- [ ] `DemoDataSeeder`:
  - the `my_tickets` view filters `status` by the id of "Open", looked up by name;
  - a public `my_assets` view owned by `limited@logis.test`;
  - a public view of a user of the unrelated customer.
- [ ] `CustomViewMother` and the existing tests are updated.

**Verification:**
- The `CustomViewTest` invariant cases.
- `EloquentCustomViewRepositoryTest`: a row with an unknown column raises `CorruptedRecordException`.
- `DemoDataSeederTest` asserts the new views.
- `make fresh`, then `make check`.

**Dependencies:** T3 · **Files:** `CustomView`, `CustomViewMapper`, `CustomViewFactory`, `DemoDataSeeder`, `CustomViewMother`, tests · **Scope:** M · **Commit:** `feat(views): keep every stored view within the catalog`

### Checkpoint: Foundation
- [ ] `make fresh` seeds only valid views. `make check` is green.
- [ ] Post-implementation flow. Human review before the endpoints.

### Phase 2 — Read side end to end

#### Task 5: Policy, list and details queries

**Description:** Decide who sees a view, and read the views a user can see (D4, D6, D12).

**Acceptance criteria:**
- [ ] `Domain/Model/CustomView/ViewPolicy` implements `canSee()` and `canManage()` as in D4.
- [ ] `CustomViewNotFoundException::withId()` (404) and `CustomViewAccessDeniedException` (403: `manage()`, `ownerWithoutCustomer()`).
- [ ] `CustomViewRepository::findOwnedByOrPublicIn(owner, customer, ?type)` is implemented in:
  - Eloquent: eager loaded, ordered by `title`, then `id`;
  - a new `tests/Unit/…/Domain/Model/CustomView/InMemoryCustomViewRepository`.
- [ ] `Query/CustomView/`:
  - `ListCustomViews` (`userId`, `?type`) returns an empty list for a user without a customer, with `meta.total`;
  - `GetCustomView` (`userId`, `viewId`) throws 404 when the view is missing or can't be seen.
- [ ] `ModelView/CustomView/CustomViewDetailsView` and `CustomViewListItemView`. `is_default` is true only for the requester's own default, and `is_owner` is included.

**Verification:**
- `ViewPolicy` unit tests for the five cases of the spec.
- Handler unit tests with the in-memory repositories.
- An integration test: every view that `findOwnedByOrPublicIn` returns passes `canSee`, and it runs a fixed number of queries.
- View tests. `make check`.

**Dependencies:** T1, T4 · **Files:** policy, 2 exceptions, repository and its in-memory double, 2 queries, 2 handlers, 2 views, tests · **Scope:** M · **Commit:** `feat(views): list and read the views a user can see`

#### Task 6: List and details endpoints

**Description:** Expose `GET /views` and `GET /views/{id}` (API contract, D13).

**Acceptance criteria:**
- [ ] `ViewController::index()` and `show()`, with `ListCustomViewsRequest` (`type` optional enum) and `ShowCustomViewRequest`, each with a `toQuery()`.
- [ ] The routes sit in the `auth:sanctum` + `RotateAccessTokenMiddleware` group. `{id}` is `whereNumber`.
- [ ] `openapi.yaml`:
  - the two operations, with the `CustomViewDetails` and `CustomViewListItem` schemas;
  - responses 200, 401, 404, 422 and 429;
  - bearer security and `X-Refreshed-Token`.

**Verification:**
- `ListCustomViewsEndpointTest` and `ShowCustomViewEndpointTest`:
  - 401;
  - own and public views of the same customer are listed; those of another customer aren't;
  - the `type` filter;
  - a user without a customer gets an empty list;
  - another customer's view is a 404;
  - the response format and `X-Refreshed-Token`.
- `make docs`, then `make check`.

**Dependencies:** T5 · **Files:** controller, 2 requests, `routes/api.php`, `openapi.yaml`, 2 feature tests · **Scope:** M · **Commit:** `feat(views): expose the list and details of views`

### Checkpoint: Read side
- [ ] `demo@logis.test` sees their views and the public view of `limited@logis.test`, and never the unrelated customer's view.
- [ ] `make check` is green. Post-implementation flow. Human review.

### Phase 3 — Write side

#### Task 7: Create a view (domain and application)

**Description:** Create views in the domain and persist them (D5, D6, D12).

**Acceptance criteria:**
- [ ] `CustomView::create(…)` has no id, is not default, and raises `CustomViewCreated` (owner id and title). An owner without a customer raises `CustomViewAccessDeniedException::ownerWithoutCustomer()`.
- [ ] `CustomViewRepository` gains:
  - `save()` (insert or update);
  - `findByOwnerAndTitle()`, implemented in Eloquent and in memory.
- [ ] A violation of the `(user_id, title)` unique index becomes `CustomViewTitleAlreadyUsedException` (409).
- [ ] `Command/CustomView/CreateCustomView` and its handler: they check the title with `findByOwnerAndTitle()` first (409), then save.
- [ ] `Query/CustomView/GetCustomViewByTitle` and its handler.

**Verification:**
- Entity and event unit tests (`DomainEventPublisher::lastEventOfType()`).
- Handler unit tests: success, duplicate title, user without a customer, invalid definition.
- Repository integration tests: an insert maps every column, and the unique index raises the 409.
- `make check`.

**Dependencies:** T5 · **Files:** entity, event, 2 exceptions, repository and double, command, query and their handlers, tests · **Scope:** M · **Commit:** `feat(views): create custom views`

#### Task 8: Create endpoint

**Acceptance criteria:**
- [ ] `CreateCustomViewRequest` has the structural rules of the API contract and `toCommand()`.
- [ ] `ViewController::store()` dispatches the command, asks `GetCustomViewByTitle`, and answers **201** with the details.
- [ ] `openapi.yaml`:
  - the request body, with the filter schema and the operator enum;
  - responses 201, 401, 403, 409, 422 and 429.

**Verification:**
- `CreateCustomViewEndpointTest`:
  - 201 and the stored row;
  - 409 on a duplicate title;
  - 403 for a user without a customer;
  - 422 for each structural rule and for each kind of catalog violation (unknown column, forbidden operator, wrong value type);
  - `is_default` in the body is ignored.
- `make docs`, then `make check`.

**Dependencies:** T7 · **Files:** request, controller, route, `openapi.yaml`, feature test · **Scope:** M · **Commit:** `feat(views): expose view creation`

#### Task 9: Update a view

**Acceptance criteria:**
- [ ] `CustomView::update(…)` replaces title, description, columns, filters and `is_public`, and raises `CustomViewUpdated`.
- [ ] `UpdateCustomViewHandler`:
  - 404 if the view can't be seen, 403 if it can't be managed;
  - 422 (`CustomViewTypeCannotChangeException`) for a different `view_type`;
  - 409 for a title used by another of the owner's views; keeping its own title is allowed.
- [ ] `PUT /views/{id}` answers 200 with the details (command, then `GetCustomView`). `UpdateCustomViewRequest` accepts `view_type` as optional. `openapi.yaml` is updated.

**Verification:** entity, handler and feature tests for every status in the contract. `make docs`, then `make check`.

**Dependencies:** T8 · **Scope:** M · **Commit:** `feat(views): update custom views`

#### Task 10: Delete a view

**Acceptance criteria:**
- [ ] `CustomView::delete()` raises `CustomViewDeleted`. `CustomViewRepository::delete()` removes the row (Eloquent and in memory).
- [ ] `DeleteCustomViewHandler` answers 404 or 403 as in D4.
- [ ] `DELETE /views/{id}` answers `ApiResponse::success`. Deleting the default leaves that type without one.
- [ ] `openapi.yaml` is updated.

**Verification:** unit and feature tests, including a public view of the same customer (403) and one of another customer (404). `make docs`, then `make check`.

**Dependencies:** T8 · **Scope:** S · **Commit:** `feat(views): delete custom views`

#### Task 11: Set a view as default

**Acceptance criteria:**
- [ ] `markAsDefault()` raises `CustomViewSetAsDefault`, and `unmarkAsDefault()` raises nothing.
- [ ] `CustomViewRepository::findDefaultOf(owner, type)` uses `lockForUpdate()` over the `(user_id, view_type)` index.
- [ ] `SetDefaultCustomViewHandler`:
  - 404 or 403 as in D4;
  - unmarks and saves the previous default, then marks and saves the new one;
  - does nothing when the view is already the default.
- [ ] `POST /views/{id}/set-default` answers 200 with the details. `openapi.yaml` is updated.

**Verification:**
- Handler unit tests: replacing, idempotent, another type untouched.
- Integration: the lookup's SQL ends with `for update` (query log), and after two calls only one default remains.
- The feature test.
- `make docs`, then `make check`.

**Dependencies:** T8 · **Scope:** M · **Commit:** `feat(views): set a view as the default of its type`

### Checkpoint: CRUD
- [ ] Six of the seven endpoints work in Swagger UI with `demo@logis.test` and `limited@logis.test`.
- [ ] Success criteria 3, 4 and 5 of the spec are met.
- [ ] `make check` is green. Post-implementation flow. Human review before execution.

### Phase 4 — Execution

#### Task 12: Execution in the application layer

**Description:** Build the Application side of execution against the `ViewBuilder` port, with in-memory doubles and no database (D6, D7).

**Acceptance criteria:**
- [ ] `Services/CustomView/`:
  - `ViewBuilder` (`type()`, `execute()`);
  - `ViewBuilders`, which throws `ViewBuilderNotRegisteredException` (`LogicException`) when a type is missing or has two builders;
  - `ViewExecutionCriteria` (`definition`, `sort`, `pageSize`, `?cursor`, `?term`, `customers`).
- [ ] `ModelView/CustomView/ViewPage` (rows, `?nextCursor`, `toArray()`, `fromArray()`) and `ViewResultView` (`view`, `columns`, `rows`).
- [ ] `ExecuteCustomView` and its handler:
  - 404 when the view can't be seen;
  - an empty page without calling the builder for a user without a customer;
  - the **executing** user's `visibleCustomers()` in the criteria;
  - `Meta::cursor()`.

**Verification:**
- Handler unit tests with a new `InMemoryViewBuilder` that records its criteria: the customers are the executing user's, not the owner's; defaults (`id desc`, 50); `q` blank or absent.
- `ViewBuilders` tests (resolves, missing, duplicate).
- `make check`: Deptrac holds.

**Dependencies:** T5 · **Scope:** M · **Commit:** `feat(views): add the view execution query and its builder registry`

#### Task 13: Asset builder and filters

**Description:** Add the shared query mechanics and the first adapter, returning the first page (D3, D7).

**Acceptance criteria:**
- [ ] `Infrastructure/CustomView/`:
  - `FieldMapping` (`column()`, `relation(relation:, foreignKey:, label:)`);
  - `ViewFilterApplier`, which applies every operator through a `match`, binds every value, escapes `contains` with `LikePattern::contains()`, and turns dates into the ranges of D3.
- [ ] `Persistence/Asset/EloquentAssetViewBuilder`:
  - the static field map of D2, with `visibleTo()` and soft deletes;
  - `select` of only `id`, the requested columns and the foreign keys;
  - `with('relation:id,label')` of only the requested relations;
  - rows with `id` first, the columns in the view's order, relations as `{id, label}` or `null`, dates in ISO 8601 UTC.
- [ ] The builder is tagged as `view_builders`. `ViewBuilders` is not wired yet.

**Verification:**
- `EloquentAssetViewBuilderTest` (integration):
  - every operator on every field type;
  - the date boundaries;
  - `contains` with a literal `%` and `_`;
  - another customer's id in a filter returns nothing;
  - soft-deleted assets are excluded;
  - the select list;
  - a fixed number of queries.
- `make check`.

**Dependencies:** T12 · **Scope:** L — split `ViewFilterApplier` into its own commit if the review asks for it · **Commit:** `feat(views): execute asset views with safe dynamic filters`

#### Task 14: Sort, cursor and text search

**Description:** Page with a validated cursor, sort by catalog fields and search text (D7, R8).

**Acceptance criteria:**
- [ ] `Infrastructure/CustomView/ViewPaginator`:
  - `orderBy(sort)->orderBy('id')` in the same direction, and `cursorPaginate()`;
  - `next_cursor` from the paginator.
- [ ] The cursor is decoded with `Cursor::fromEncoded()`. A cursor that can't be decoded, or whose parameters aren't exactly `{sort column, id}`, raises `InvalidViewCursorException` (422) before any query.
- [ ] A search term applies the model's `matching()` scope.

**Verification:**
- Integration tests on the asset builder:
  - pages through 7 rows with `limit` 3 in both directions and several sorts, with no duplicates or gaps, including ties on the sort column;
  - a cursor from another sort order raises 422, and a garbage cursor too;
  - `q` matches serial numbers, manufacturers and models.
- `make check`.

**Dependencies:** T13 · **Scope:** M · **Commit:** `feat(views): page view results with a validated cursor`

#### Task 15: Ticket builder

**Acceptance criteria:**
- [ ] `Persistence/Ticket/EloquentTicketViewBuilder`:
  - the field map of D2;
  - `status` and `asset` as relations with the labels `name` and `serial_number`;
  - soft-deleted tickets excluded;
  - tagged.

**Verification:** `EloquentTicketViewBuilderTest`: filters on every field type, the relations, soft deletes, visibility, `q`, and a fixed number of queries. `make check`.

**Dependencies:** T14 · **Scope:** M · **Commit:** `feat(views): execute ticket views`

#### Task 16: Contract and location builders

**Acceptance criteria:**
- [ ] `EloquentContractViewBuilder` returns contracts of every status.
- [ ] `EloquentLocationViewBuilder` handles null address fields, both in filters and in rows.
- [ ] Both are tagged.

**Verification:** one integration test per builder, with the same cases as tickets. `make check`.

**Dependencies:** T14 · **Scope:** M · **Commit:** `feat(views): execute contract and location views`

#### Task 17: Execute endpoint

**Acceptance criteria:**
- [ ] `LogisServiceProvider` wires `ViewBuilders` with `giveTagged('view_builders')`.
- [ ] `ExecuteCustomViewRequest`: `limit` 1–1000, `cursor`, `sort.field`, `sort.direction`, and `q` as valid UTF-8. It has `toQuery(id:)`.
- [ ] `ViewController::execute()` answers `ApiResponse::fromQueryResponse(…, message: 'View results')`.
- [ ] `openapi.yaml`:
  - the request body;
  - the row schema (a scalar or `{id, label}`) and the cursor meta;
  - responses 200, 401, 404, 422, 429 and 504.

**Verification:**
- `ExecuteCustomViewEndpointTest`:
  - one view of each type;
  - another user's public view returns the executing user's rows;
  - a user with sub-accounts, with and without the permission;
  - `limit` 0 and 1001, an unknown or non-sortable sort field, and a bad cursor give 422;
  - following `next_cursor` to the end;
  - the same number of queries with 1 and with 30 rows (`CountsQueries`).
- `ViewBuilders` resolves from the container.
- `make docs`, then `make check`.

**Dependencies:** T15, T16 · **Scope:** M · **Commit:** `feat(views): expose view execution`

### Checkpoint: All endpoints
- [ ] Success criteria 1, 2, 3 and 9 of the spec are met.
- [ ] `make check` is green. Post-implementation flow. Human review.

### Phase 5 — Performance and robustness

#### Task 18: View indexes

**Acceptance criteria:**
- [ ] Migration `add_view_indexes` with the columns of D11:
  - `assets`: `item_name`, `status`, `created_at`, `updated_at`;
  - `created_at` and `updated_at` on `tickets`, `contracts` and `locations`.
- [ ] `down()` drops exactly those indexes.

**Verification:** `SchemaTest` asserts the new indexes. `make migrate`, then a manual `migrate:rollback --step=1` and `make migrate`. `make check`.

**Dependencies:** T17 · **Scope:** S · **Commit:** `perf(views): index the filtered and sorted columns`

#### Task 19: Result cache

**Acceptance criteria:**
- [ ] `config/views.php` → `cache_ttl_seconds` (`VIEWS_CACHE_TTL_SECONDS`, default `300`), added to `.env.example`.
- [ ] `Infrastructure/Cache/CachedViewBuilder`:
  - the key of D8: `views:v1:{type}:` followed by `sha256` of the canonical JSON;
  - it stores `ViewPage::toArray()`;
  - it never caches an exception.
- [ ] `LogisServiceProvider` wraps the four builders with `extend()`.

**Verification:**
- `CachedViewBuilderTest`:
  - a repeated execution runs 0 queries;
  - a change of customer, rule, definition, sort, term, limit or cursor misses the cache;
  - a term that differs only in case hits it;
  - a failure isn't stored.
- A feature test: a repeated request runs no builder queries.
- `make check`.

**Dependencies:** T17 · **Scope:** M · **Commit:** `perf(views): cache view results for five minutes`

#### Task 20: Execution timeout

**Acceptance criteria:**
- [ ] `config/views.php` → `execution_timeout_ms` (`VIEWS_EXECUTION_TIMEOUT_MS`, default `5000`), added to `.env.example`.
- [ ] `Infrastructure/CustomView/StatementTimeout`:
  - sets `max_execution_time` for the session;
  - runs the builder's queries;
  - restores the previous value in `finally`;
  - turns MySQL error 3024 into `ViewExecutionTimedOutException` (`TimeoutException`).
- [ ] Every builder runs through it. The cache decorator sits outside, so a timeout is never cached.

**Verification:**
- `StatementTimeoutTest`: a heavy cross join over `information_schema` with 1 ms raises the exception, and the session value is restored afterwards.
- The feature test: 504 with the timeout set to 1 ms, and the exception is reported.
- `make check`.

**Dependencies:** T17 · **Scope:** M · **Commit:** `feat(views): stop view executions after five seconds`

### Checkpoint: Complete
- [ ] Every success criterion in the spec is met.
- [ ] `make fresh`, `make docs` and `make check` are green.
- [ ] Post-implementation flow. Human review before opening the pull request from `feat/custom-views`. The spec is marked `done`.

## Risks and mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| `cursorPaginate()` needs the order columns in the select list, and may qualify them (`assets.created_at`), so the cursor parameters differ from the bare names | Medium | `ViewPaginator` always selects the sort column and orders by bare column names. T14 tests the parameter names of the generated cursor. |
| `Cursor::fromEncoded()` returns `null` for garbage, and a mismatched cursor fails deep in Laravel | Medium | An explicit check before querying: a cursor that is sent but decodes to `null`, or has other parameters, raises the 422 (T14). |
| `max_execution_time` behaves differently inside the `RefreshDatabase` transaction, or a 1 ms test is flaky | Medium | A cross join large enough to always exceed 1 ms. `SLEEP()` is not used, because it is interrupted without an error. If it stays flaky, the feature test binds a fake builder that throws the exception. |
| `lockForUpdate()` can't be proven with real concurrency in PHPUnit | Low | Assert `for update` in the query log, and the handler's order of operations. Record it as a limit of the tests. |
| `CustomView::id()` becoming nullable spreads `null` checks | Low | Only `create()` produces a view without an id. Views loaded from the repository always have one. Handlers that need the id read it from views loaded from the repository. |
| `Model::preventLazyLoading()` fails when a row reads a relation that wasn't eager loaded | Low | This is a guard, not a problem: T13 and T15 assert the relations through `with()`. |
| Existing `custom_views` rows in development databases don't fit the catalog | Low | The project is not deployed. `make fresh` recreates the seed, and T4 makes the seed valid. |
| The seeded "Open" status id depends on the seeding order | Low | The seeder looks the id up by name. |

## Parallelisation

- T1 and T2→T3 are independent after T0.
- T9, T10 and T11 are independent after T8, but they touch the same controller, routes and `openapi.yaml`. They are done in sequence.
- T12 depends only on T5, so the execution work (T12–T17) could run in parallel with the write side (T7–T11). Both touch `ViewController`, `routes/api.php` and `openapi.yaml`, so they are done in sequence.
- T15 and T16 are independent after T14. T18, T19 and T20 are independent after T17.

## Open questions

None.
