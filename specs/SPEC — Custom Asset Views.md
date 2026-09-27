# SPEC — Custom Asset Views

Status: done

## Objective

Refactor the legacy custom views (`logis-test/endpoints/endpoint-2.php`) into seven endpoints under `/api/v1/views`, as required by "Endpoint 2: Custom Asset Views" in `logis-test/LARAVEL_TECHNICAL_TEST.md`.

A custom view is a saved configuration: which entity it lists (`view_type`), which columns it shows, and which filters it applies. The consumer is the API client's list screens (assets, tickets, contracts, locations). A user saves views, shares them with their customer (`is_public`), picks one default per entity, and executes a view to get a page of rows.

The legacy code stores PHP-serialised arrays, builds SQL by concatenating the stored filters and request values, trusts the customer ids sent by the browser, and has no limit on the rows it returns. This spec removes all four problems:

- configurations are JSON, validated against a whitelist of fields and operators per view type;
- queries are built with the Eloquent query builder, and every value is bound;
- the customers come from the authenticated user, never from the request;
- results are paginated with a cursor, capped, cached and time-limited.

### What already exists

The data model was built by `SPEC — Core Domain Model.md`, and the visibility rules were decided there (D10). This spec builds on:

- `custom_views` (JSON `columns` and `filters`, unique `(user_id, title)`, index `(user_id, view_type)`), `CustomViewModel` with its casts, `CustomViewMapper`, `CustomViewFactory` and two seeded views;
- the entity `CustomView` and the value objects `ViewType`, `ViewColumns`, `ViewFilters` and `ViewFilter`, which validate the **structure** only;
- `CustomViewRepository::findById()`;
- from `SPEC — Global Search.md`: `User::visibleCustomers()`, `VisibleCustomerIds`, the `visibleTo()` and `matching()` scopes, `LikePattern`, `SearchTerm`, and the pattern of a port with one adapter per entity, a registry and a cache decorator.

### Requirements from the brief

| Brief | Where it is covered |
|---|---|
| JSON columns instead of `unserialize()`, `CustomView` model with casts | Already done (core spec) |
| Validation of column and filter configurations | D2, D3 |
| Query Builder for dynamic queries, `ViewBuilder` service | D7 |
| `ViewRepository` for view management | D5 (`CustomViewRepository`) |
| `ViewPolicy` for authorization | D4 |
| DTOs for view configurations | D6 (messages, `ViewExecutionCriteria`, model views) |
| Seven endpoints | API contract |
| Allowed columns and operators, restricted fields, sanitised values | D2, D3 |
| Rate limiting for execution | D10 (global `api` limiter) |
| Result cache | D8 |
| Indexes | D11 |
| At most 1000 records, cursor pagination | D7 |
| Execution timeout | D9 |

## API contract

Every route is protected by `auth:sanctum` and `RotateAccessTokenMiddleware`, and returns `X-Refreshed-Token`, like every authenticated route. Every response uses the standard format `{ success, data, message, meta }`.

| Method | Path | Success | Errors |
|---|---|---|---|
| GET | `/api/v1/views` | 200 | 401, 422, 429 |
| POST | `/api/v1/views` | 201 | 401, 403, 409, 422, 429 |
| GET | `/api/v1/views/{id}` | 200 | 401, 404, 429 |
| PUT | `/api/v1/views/{id}` | 200 | 401, 403, 404, 409, 422, 429 |
| DELETE | `/api/v1/views/{id}` | 200 | 401, 403, 404, 429 |
| POST | `/api/v1/views/{id}/execute` | 200 | 401, 404, 422, 429, 504 |
| POST | `/api/v1/views/{id}/set-default` | 200 | 401, 403, 404, 429 |

- **404** when the view doesn't exist **or** the user can't see it (D4), so the API never reveals that another customer's view exists. `{id}` only matches ids that can exist (`[1-9][0-9]{0,17}`), so `0`, `007` or a number too large for an id are 404 too.
- View titles are unique per owner **whatever their case**, as the column collation compares them (`CustomViewTitle::equals()`).
- **403** when the user can see the view but isn't its owner and tries to change it, or when a user without a customer tries to create a view.
- **409** when the owner already has a view with that title.
- **504** when the execution exceeds the timeout (D9).

### Shapes

**View details** (`GET /views/{id}`, and the responses of `POST`, `PUT` and `set-default`):

```json
{
  "id": 7,
  "title": "Zebra scanners",
  "description": "Every active Zebra device",
  "view_type": "my_assets",
  "columns": ["serial_number", "manufacturer", "model", "location"],
  "filters": [
    { "column": "manufacturer", "operator": "eq", "value": "Zebra" },
    { "column": "created_at", "operator": "gt", "value": "2024-01-01" }
  ],
  "is_default": true,
  "is_public": false,
  "is_owner": true,
  "owner": { "id": 1, "name": "Demo User" }
}
```

- `is_default` is true only when the view is **the requesting user's** default. A public view is never shown as default to another user.
- `is_owner` tells the client whether it can edit, delete or set the view as default.

**List item** (`GET /views`): `id`, `title`, `description`, `view_type`, `is_default`, `is_public`, `is_owner`.

### `GET /api/v1/views`

| Parameter | Rules |
|---|---|
| `type` | optional; one of `my_assets`, `my_tickets`, `my_contracts`, `my_locations` |

- It returns the user's own views plus the public views of the user's customer, ordered by `title` ascending, then `id`. There is no pagination: a user has few views, as in the legacy code.
- `meta.total` holds the number of views.
- A user without a customer gets 200 with an empty list.

### `POST /api/v1/views` and `PUT /api/v1/views/{id}`

```json
{
  "title": "Zebra scanners",
  "description": "Every active Zebra device",
  "view_type": "my_assets",
  "columns": ["serial_number", "manufacturer", "model", "location"],
  "filters": [
    { "column": "manufacturer", "operator": "eq", "value": "Zebra" },
    { "column": "status", "operator": "in", "value": ["active", "inactive"] },
    { "column": "created_at", "operator": "between", "value": ["2024-01-01", "2024-06-30"] }
  ],
  "is_public": false
}
```

| Field | Structural rules (FormRequest) |
|---|---|
| `title` | required, string, at most 255 characters |
| `description` | nullable, string, at most 1000 characters |
| `view_type` | `POST`: required, enum. `PUT`: optional, but if it is sent it must equal the current type |
| `columns` | required, array, at least 1 item; each item a distinct string |
| `filters` | present, array, at most 20 items (an empty array means no filters) |
| `filters.*.column` | required, string |
| `filters.*.operator` | required, one of the operators in D3 |
| `filters.*.value` | present |
| `is_public` | boolean, default `false` |

- `PUT` replaces the whole view: title, description, columns, filters and `is_public`. `view_type` can't change (R4): a different value returns 422.
- The whitelist (which columns and operators exist for the type, and the type of each value) is a domain rule (D2, D3). A violation returns 422 with a message that names the column or filter.
- `is_default` isn't accepted here. The default only changes through `set-default` (D5).
- `POST` returns **201** with the view details.

### `DELETE /api/v1/views/{id}`

Deletes the view (hard delete) and returns `ApiResponse::success` with no data. If it was the owner's default, that type is left without a default.

### `POST /api/v1/views/{id}/set-default`

Makes the view the owner's default for its type. The previous default of that owner and type stops being the default in the same transaction. Setting a view that is already the default succeeds and changes nothing. Returns the view details.

### `POST /api/v1/views/{id}/execute`

```json
{
  "limit": 50,
  "cursor": "eyJpZCI6NzcsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0",
  "sort": { "field": "created_at", "direction": "desc" },
  "q": "zt4"
}
```

| Field | Rules | Default |
|---|---|---|
| `limit` | integer, 1 to 1000 | 50 |
| `cursor` | nullable, string; the `next_cursor` of the previous page | first page |
| `sort.field` | a sortable field of the view type (D2) | `id` |
| `sort.direction` | `asc` or `desc` | `desc` |
| `q` | nullable, string, valid UTF-8, trimmed; blank means no text search | — |

**200 OK**

```json
{
  "success": true,
  "data": {
    "view": { "id": 7, "title": "Zebra scanners", "view_type": "my_assets" },
    "columns": ["serial_number", "manufacturer", "model", "location"],
    "rows": [
      {
        "id": 77,
        "serial_number": "ZB-00931",
        "manufacturer": "Zebra",
        "model": "ZT410",
        "location": { "id": 3, "label": "Main warehouse" }
      }
    ]
  },
  "message": "View results",
  "meta": {
    "timestamp": "2026-09-26T12:00:00Z",
    "version": "1.0",
    "limit": 50,
    "next_cursor": "eyJpZCI6MjcsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0",
    "has_more": true,
    "execution_time_ms": 38
  }
}
```

- Every row carries `id`, plus the view's columns in the view's order.
- A relation field is `{ "id": …, "label": … }`, or `null` when the row has no related record. The id is what filters on that field use (R6).
- Dates are ISO 8601 in UTC (`2024-01-01T10:00:00Z`).
- `meta` comes from `Meta::cursor()`. `next_cursor` is `null` on the last page.
- The rows are always filtered by the customers of **the user who executes** the view, never by the owner's. A user without a customer gets 200 with no rows.
- 422 for a `limit` out of range, an unknown or non-sortable `sort.field`, or a cursor that is malformed or doesn't belong to that sort.

## Key design decisions

### D1. Vocabulary

| Brief | Here |
|---|---|
| `ViewRepository` | `CustomViewRepository` (Domain), `EloquentCustomViewRepository` (Infrastructure) |
| `ViewPolicy` | `ViewPolicy`, a domain service (D4) |
| `ViewBuilder` | `ViewBuilder`, an Application port with one Eloquent adapter per view type (D7) |
| DTOs | Commands and queries, `ViewExecutionCriteria`, and the model views (D6) |
| "Controller → Service → Repository" | `ViewController` → bus → handler → `CustomViewRepository` / `ViewBuilder` |

`ARCHITECTURE.md` records this mapping when it is written.

### D2. The field catalog: the whitelist per view type

What a view can show, filter and sort is part of the domain: it is the vocabulary the user sees. Every view type has a fixed catalog of fields. A field that isn't in the catalog doesn't exist for the API, so internal columns (`customer_id`, `deleted_at`, `created_by`, `issue_description`, foreign keys) can't be requested, filtered or sorted.

Every field has:

- a **name** (the API name, never a table column);
- a **type** (`ViewFieldType`): `text`, `choice`, `identifier` or `date`;
- the **allowed values** for a `choice`;
- whether it is **sortable**.

Every catalog field can be shown and filtered.

**`my_assets`** (soft-deleted assets excluded)

| Field | Type | Sortable | Shows |
|---|---|---|---|
| `id` | identifier | yes | asset id |
| `serial_number` | text | yes | |
| `manufacturer` | text | yes | |
| `model` | text | yes | |
| `item_name` | text | yes | |
| `status` | choice (`active`, `inactive`, `retired`) | yes | |
| `customer` | identifier | no | `{id, label: customer name}` |
| `contract` | identifier | no | `{id, label: contract name}` |
| `location` | identifier | no | `{id, label: location title}` |
| `created_at`, `updated_at` | date | yes | |

**`my_tickets`** (soft-deleted tickets excluded)

| Field | Type | Sortable | Shows |
|---|---|---|---|
| `id` | identifier | yes | |
| `repair_id` | text | yes | |
| `incident_number` | text | yes | |
| `subject` | text | yes | |
| `status` | identifier | no | `{id, label: status name}` |
| `asset` | identifier | no | `{id, label: serial number}` |
| `customer` | identifier | no | `{id, label: customer name}` |
| `created_at`, `updated_at` | date | yes | |

**`my_contracts`** (every status, unlike the global search)

| Field | Type | Sortable | Shows |
|---|---|---|---|
| `id` | identifier | yes | |
| `name` | text | yes | |
| `status` | choice (`active`, `inactive`) | yes | |
| `customer` | identifier | no | `{id, label: customer name}` |
| `created_at`, `updated_at` | date | yes | |

**`my_locations`**

| Field | Type | Sortable | Shows |
|---|---|---|---|
| `id` | identifier | yes | |
| `title` | text | yes | |
| `full_address`, `city`, `state`, `postal_code` | text | no (nullable) | |
| `customer` | identifier | no | `{id, label: customer name}` |
| `created_at`, `updated_at` | date | yes | |

- **Sortable** means a column of the entity's own table that is never null. Cursor pagination can't page over nulls, and sorting by a relation label would need a join. `created_at` and `updated_at` are nullable in the schema, but Eloquent always fills them.
- The allowed values of a `choice` come from the domain enums (`AssetStatus::cases()`, `ContractStatus::cases()`), so a new case is picked up automatically.
- Adding a field is an "ask first" change (Boundaries): it touches the catalog, the adapter's field map and, when the field is filterable, the indexes.

### D3. Operators and values

`FilterOperator` (backed enum): `eq`, `neq`, `in`, `not_in`, `contains`, `gt`, `gte`, `lt`, `lte`, `between`, `in_last_days`.

| Field type | Allowed operators |
|---|---|
| `text` | `eq`, `neq`, `in`, `not_in`, `contains` |
| `choice` | `eq`, `neq`, `in`, `not_in` |
| `identifier` | `eq`, `in`, `not_in` |
| `date` | `gt`, `gte`, `lt`, `lte`, `between`, `in_last_days` |

`ViewFieldType::allows(FilterOperator)` answers the question, and the operator knows the shape of its value (`FilterOperator::expectsList()`, `expectsRange()`, `expectsDays()`), following the rule that questions about a state belong to its enum.

| Operator | Value |
|---|---|
| `eq`, `neq`, `gt`, `gte`, `lt`, `lte`, `contains` | one scalar |
| `in`, `not_in` | a list of 1 to 100 scalars |
| `between` | a list of exactly 2 values, the first not after the second |
| `in_last_days` | an integer from 0 to 365 |

Value per field type:

- `text`: a non-empty string of at most 255 characters;
- `choice`: one of the allowed values;
- `identifier`: an integer ≥ 1;
- `date`: a `Y-m-d` date.

**Date semantics.** A date is a whole UTC day, and it is compared as a half-open range, so the index on the column is used (the legacy code wrapped the column in `DATE()`, which prevents that):

| Filter | SQL |
|---|---|
| `gt d` | `col >= d+1 00:00:00` |
| `gte d` | `col >= d 00:00:00` |
| `lt d` | `col < d 00:00:00` |
| `lte d` | `col < d+1 00:00:00` |
| `between [a, b]` | `col >= a 00:00:00 AND col < b+1 00:00:00` |
| `in_last_days n` | `col >= today-n 00:00:00` (`0` is today). This replaces the legacy "Today / Yesterday / Last 30 / 60 days" |

- Filters are combined with `AND`, as in the legacy code. The same field can appear in several filters (for example `gte` and `lte`).
- `contains` escapes `\`, `%` and `_` with `LikePattern::contains()`.
- Every value is a bound parameter. No value, field name or operator is ever concatenated into SQL: field names are mapped to columns by the adapter's static map (D7), and operators to builder methods by a `match`.

### D4. `ViewPolicy`: authorization in the domain

`Domain/Model/CustomView/ViewPolicy` is a domain service, not a Laravel Gate policy. It keeps the rule framework-agnostic and testable without HTTP. The handlers call it.

```php
final class ViewPolicy
{
    public function canSee(User $user, CustomView $view): bool;

    public function canManage(User $user, CustomView $view): bool;
}
```

- `canSee`: the user owns the view, or the view is public and belongs to the user's own customer. A user without a customer sees nothing. Sub-accounts don't widen visibility: a public view is shared inside its customer only (D10 of the core spec).
- `canManage`: the user owns the view. Only the owner updates, deletes or sets it as default.

| Situation | Exception | Status |
|---|---|---|
| The view doesn't exist, or `canSee` is false | `CustomViewNotFoundException::withId()` | 404 |
| `canSee` is true but `canManage` is false | `CustomViewAccessDeniedException::manage()` | 403 |
| A user without a customer creates a view | `CustomViewAccessDeniedException::ownerWithoutCustomer()` | 403 |

403 needs a new base class, `SharedContext/Domain/Exception/ForbiddenException`, which `ApiExceptionRenderer` maps to 403. It is not logged, like the other domain exceptions. `AGENTS.md` adds it to the table.

### D5. The `CustomView` aggregate gains behaviour

The entity stops being read-only:

| Method | Does |
|---|---|
| `CustomView::create(owner, title, description, type, columns, filters, isPublic)` | A new view, not default, with no id yet. It raises `CustomViewCreated` |
| `update(title, description, columns, filters, isPublic)` | Replaces the editable fields. It raises `CustomViewUpdated` |
| `markAsDefault()` / `unmarkAsDefault()` | Changes `isDefault`. Marking raises `CustomViewSetAsDefault` |
| `delete()` | Raises `CustomViewDeleted`. The repository then removes it |

- **Invariant:** the columns and filters are always valid for the view's type (D2, D3). It is checked in the constructor and in `update()`, so a view that breaks the catalog can't exist. A stored view that no longer fits the catalog raises `CorruptedRecordException` in the mapper: a logged 500.
- `ViewColumns` and `ViewFilters` keep validating the structure. A new value object, `ViewDefinition` (type + columns + filters), validates them against the catalog. `ViewFilter` holds a `FilterOperator` instead of a string.
- **The id of a new view.** `custom_views.id` is auto-increment, so a view has no id until it is inserted. `CustomView::id()` returns `?CustomViewId`: `null` only between `create()` and `save()`. `CustomViewCreated` carries the owner id and the title (unique per owner), not the id. The alternative, a UUID key, would change the schema of the core spec for no other gain.
- **One default per owner and type.** `SetDefaultCustomViewHandler` loads the owner's current default of that type with `CustomViewRepository::findDefaultOf(owner, type)`, unmarks it, saves it, then marks and saves the new one.
- **Concurrent writes of the same owner are not serialised.** One person works in one account at a time, and only the owner changes their views, so two writes of the same owner at the same instant are very unlikely. Title uniqueness doesn't depend on it: the `(user_id, title)` unique index turns a race into a 409. In the rare worst case, two simultaneous `set-default` requests leave two defaults of one type, and the next `set-default` fixes it; a `PUT` racing a `set-default` can lose one of the two changes. A per-owner lock was implemented and then removed as over-engineering (`SPEC — Remove View Write Lock.md`).
- The events have no listeners yet. They follow the convention of `AGENTS.md`, and they are the hook for cache invalidation or audit later.

### D6. Application layer

Grouped by aggregate (`SPEC — Application Layout.md`). A command belongs to the aggregate it changes, and a query to the aggregate it returns.

| Folder | Classes |
|---|---|
| `Command/CustomView/` | `CreateCustomView`, `UpdateCustomView`, `DeleteCustomView`, `SetDefaultCustomView` and their handlers |
| `Query/CustomView/` | `ListCustomViews`, `GetCustomView`, `GetCustomViewByTitle`, `ExecuteCustomView` and their handlers |
| `Services/CustomView/` | `ViewBuilder` (port), `ViewBuilders` (registry), `ViewExecutionCriteria` |
| `Exception/CustomView/` | `ViewBuilderNotRegisteredException` (`LogicException`, logged 500), `ViewExecutionTimedOutException` (`TimeoutException`, 504) |
| `ModelView/CustomView/` | `CustomViewDetailsView`, `CustomViewListItemView`, `ViewPage`, `ViewResultView` |

- Messages are `final readonly` and carry primitives (`userId`, `viewId`, `title`, `columns` as arrays…). The handlers build the value objects, as the existing handlers do.
- **Create returns the new view without breaking CQRS.** Commands return nothing. The controller dispatches `CreateCustomView`, then asks `GetCustomViewByTitle(userId, title)`: the title is unique per owner, so no id has to flow back from the command.
- `ExecuteCustomView` is a query even though the route is `POST` (the brief fixes the method): it changes no state and runs without a transaction.
- `ExecuteCustomViewHandler`:
  1. loads the user and the view; 404 if `canSee` is false;
  2. builds `ViewExecutionCriteria`: the `ViewDefinition`, `ViewSort` (default `id desc`), `ViewPageSize`, the cursor, an optional `SearchTerm`, and `User::visibleCustomers()`;
  3. if the visible customers are empty, returns an empty page without calling the builder;
  4. asks the `ViewBuilder` of the view's type through `ViewBuilders`;
  5. returns `QueryResponse::from(data: ViewResultView…, meta: Meta::cursor(limit: …, nextCursor: …))`.
- `ViewPage` holds `list<array<string, mixed>>` rows and `?string` next cursor. It is plain arrays, so it can be cached safely, like `EntitySearchResult`.
- New domain value objects in `Domain/ValueObject/CustomView/`: `ViewFieldType`, `FilterOperator`, `SortDirection` (enums), `ViewField`, `ViewFieldCatalog`, `ViewDefinition`, `ViewSort`, `ViewPageSize` (1 to 1000, `DEFAULT = 50`). Each invalid value raises a semantic `Invalid<X>Exception` (422).

### D7. `ViewBuilder`: the dynamic query

```php
interface ViewBuilder
{
    public function type(): ViewType;

    public function execute(ViewExecutionCriteria $criteria): ViewPage;
}
```

- One adapter per type, in the folder of its aggregate: `Persistence/Asset/EloquentAssetViewBuilder`, `Persistence/Ticket/EloquentTicketViewBuilder`, `Persistence/Contract/EloquentContractViewBuilder`, `Persistence/Location/EloquentLocationViewBuilder`.
- Each adapter declares a **static field map**: catalog field → column, or relation + label column. It is the only place that knows the table.
- `ViewBuilders` indexes the adapters by `type()` and fails fast with `ViewBuilderNotRegisteredException` if a `ViewType` case has none, or has two. `LogisServiceProvider` tags them as `view_builders`, like the searchers. A test catches a forgotten builder.
- The mechanics shared by the four adapters live in `Infrastructure/CustomView/`:
  - `ViewFilterApplier` turns a `ViewFilter` into `where`, `whereNot`, `whereIn`, `whereNotIn`, `where(… 'like' …)` or a date range, through a `match` on the operator;
  - `ViewPaginator` applies the sort and `cursorPaginate()`, and maps each model to a row with the field map.
- **The query:**
  1. `Model::query()->visibleTo($criteria->customers)`, plus `SoftDeletes` for assets and tickets;
  2. the filters, then `matching($term)` when there is a search term. `q` searches the same columns as the global search (`SPEC — Global Search.md`, D3), through the same scope;
  3. `select` of `id`, the columns of the requested fields, the foreign keys of the requested relations and the sort column. Nothing else is read;
  4. `with()` of only the requested relations, each with only `id` and its label column (`with(['location:id,title'])`);
  5. `orderBy(sort field, direction)->orderBy('id', direction)` and `cursorPaginate(perPage: limit, cursor: …)`.
- **Fixed number of queries:** one `SELECT … LIMIT n+1`, plus one per requested relation. `cursorPaginate` runs no `COUNT`.
- **Cursor validation:** `ViewCursor` tags every next cursor with its sort (`_sort: "created_at:desc"`) and checks it before building Laravel's `Cursor`. A cursor that can't be decoded, whose parameters aren't exactly the sort column and `id`, or whose sort field or direction differs, raises `InvalidViewCursorException` (422). A cursor from another sort order never reaches MySQL.
- **Enum columns are sorted by their declaration order in MySQL but compared as strings in the cursor.** `AssetStatus` and `ContractStatus` are declared in alphabetical order, so both orders agree; a test fails if a new value breaks that.
- **Maximum 1000 records:** `ViewPageSize` caps each page at 1000. The client pages with the cursor, so no request ever loads more than 1000 rows.

### D8. Result cache

- `CachedViewBuilder` (`Infrastructure/Cache/`) decorates each `ViewBuilder`. The provider wraps the four adapters with `$this->app->extend()`, as it does for the searchers.
- TTL: `config/views.php` → `cache_ttl_seconds`, from `VIEWS_CACHE_TTL_SECONDS`, default `300` (5 minutes).
- Key: `views:v1:{type}:` followed by `sha256` of the canonical JSON of:
  - `{customer id}` and `{sub-accounts included: 0 or 1}`;
  - columns, filters, sort, lower-cased term, limit and cursor.
- **What the key holds:**
  - It contains the **definition**, not the view id. Editing a view changes the key, so no invalidation is needed. Two views with the same definition share entries.
  - It contains the **visibility rule**, not the user (as in search). A user never receives another customer's rows.
- What is stored: the plain array form of `ViewPage`.
- Data changes appear when the entry expires, within 5 minutes. There are no write endpoints for assets, tickets, contracts or locations, so this is accepted (as in search).
- A timeout or an exception is never cached.

### D9. Execution timeout

- `config/views.php` → `execution_timeout_ms`, from `VIEWS_EXECUTION_TIMEOUT_MS`, default `5000`.
- `Infrastructure/CustomView/StatementTimeout` runs the adapter's queries with MySQL's `max_execution_time` set for the session, and restores the previous value in `finally`. `max_execution_time` applies only to `SELECT`, which is exactly what an execution runs.
- MySQL error 3024 ("maximum statement execution time exceeded") becomes `ViewExecutionTimedOutException`.
- The limit applies **per statement**: the page query and each eager load get up to 5 seconds each. With at most three relations per type, an execution is bounded by a few statements of 5 seconds.
- New base class `SharedContext/Domain/Exception/TimeoutException`, which `ApiExceptionRenderer` maps to **504**. Unlike the other domain exceptions, it **is logged**: a timeout is an operational signal, not a client mistake. `AGENTS.md` adds it to the table.

### D10. Rate limiting

The global `api` limiter already applies to every route of the `api` group (`throttleApi()` in `bootstrap/app.php`): 60 requests per minute, per user when authenticated and per IP otherwise. `execute` is covered by it, and this spec adds no limiter of its own (R3). A limiter per endpoint is an "ask first" change.

### D11. Indexes

The search rule applies: every column a view filters or sorts on has an index. New single-column indexes in one migration, `add_view_indexes`, with a `down()` that drops them:

| Table | Already indexed | New |
|---|---|---|
| `assets` | `customer_id`, `serial_number`, `manufacturer`, `model`, `contract_id`, `location_id`, `deleted_at` | `item_name`, `status`, `created_at`, `updated_at` |
| `tickets` | `customer_id`, `repair_id`, `incident_number`, `subject`, `status_id`, `asset_id`, `deleted_at` | `created_at`, `updated_at` |
| `contracts` | `customer_id`, `name`, `status` | `created_at`, `updated_at` |
| `locations` | `customer_id`, `title`, `full_address`, `city`, `state`, `postal_code` | `created_at`, `updated_at` |

InnoDB appends the primary key to every secondary index, so the index on the sort column also serves the `(sort column, id)` order of the cursor.

### D12. Repository

`CustomViewRepository` gains the methods that the handlers need:

```php
interface CustomViewRepository
{
    public function findById(CustomViewId $id): ?CustomView;

    public function findByOwnerAndTitle(UserId $owner, CustomViewTitle $title): ?CustomView;

    public function findDefaultOf(UserId $owner, ViewType $type): ?CustomView;

    /** @return list<CustomView> */
    public function findOwnedByOrPublicIn(UserId $owner, CustomerId $customer, ?ViewType $type): array;

    public function save(CustomView $view): void;

    public function delete(CustomViewId $id): void;
}
```

- `findOwnedByOrPublicIn` is the SQL form of `ViewPolicy::canSee`. A test asserts that every view it returns passes `canSee`, so the two can't drift.
- Every method eager loads the full graph with `CustomViewMapper::relations()`, so the number of queries is fixed.
- `save()` inserts or updates. A duplicate title (the `(user_id, title)` unique index) becomes `CustomViewTitleAlreadyUsedException` (`ConflictException`, 409). The handlers check with `findByOwnerAndTitle` first, and the index catches the race.

### D13. HTTP

- `ViewController` with `index`, `store`, `show`, `update`, `destroy`, `execute` and `setDefault`, extending `AbstractController`.
- One FormRequest per action in `Http/Request/CustomView/`:
  - `ListCustomViewsRequest`, `CreateCustomViewRequest`, `ShowCustomViewRequest`, `UpdateCustomViewRequest`, `DeleteCustomViewRequest`, `ExecuteCustomViewRequest`, `SetDefaultCustomViewRequest`;
  - each has `toCommand(id: …)` or `toQuery(id: …)`, with the user id from `CurrentAccessToken`.
- FormRequests validate the **structure** (API contract). The catalog rules are validated once, in the domain.
- `store`:
  - dispatches the command, asks `GetCustomViewByTitle`, and returns `fromQueryResponse(…, status: 201)`;
  - builds that query itself from the command's values, which is allowed: they are values the controller already holds.
- `update` and `setDefault` dispatch the command and then ask `GetCustomView`.

### D14. Seed data

- The seeded views follow the catalog:
  - the `my_tickets` view filters `status` by the **id** of "Open", not by its name (R6).
- New views:
  - a public `my_assets` view owned by `limited@logis.test` (same customer as the demo user), so the demo user sees a shared view that they can't edit;
  - a public view of a user of the unrelated customer, which the demo user never sees.
- `CustomViewFactory` produces catalog-valid columns and filters for each type in `ofType()`.

## Architecture

```
app/SharedContext/Domain/Exception/        ForbiddenException (403), TimeoutException (504, logged)
app/SharedContext/Infrastructure/Http/     ApiExceptionRenderer maps both
app/LogisContext/
├── Domain/
│   ├── Model/CustomView/                  CustomView (behaviour), CustomViewRepository (new methods), ViewPolicy
│   ├── ValueObject/CustomView/            ViewFieldType, FilterOperator, SortDirection, ViewField, ViewFieldCatalog,
│   │                                      ViewDefinition, ViewSort, ViewPageSize; ViewFilter holds a FilterOperator
│   ├── Event/CustomView/                  CustomViewCreated, CustomViewUpdated, CustomViewDeleted, CustomViewSetAsDefault
│   └── Exception/CustomView/              CustomViewNotFoundException, CustomViewAccessDeniedException,
│                                          CustomViewTitleAlreadyUsedException, CustomViewTypeCannotChangeException,
│                                          InvalidViewSortException, InvalidViewPageSizeException, …
├── Application/
│   ├── Command/CustomView/                Create/Update/Delete/SetDefault CustomView + handlers
│   ├── Query/CustomView/                  ListCustomViews, GetCustomView, GetCustomViewByTitle, ExecuteCustomView + handlers
│   ├── Services/CustomView/               ViewBuilder, ViewBuilders, ViewExecutionCriteria
│   ├── Exception/CustomView/              ViewBuilderNotRegisteredException, ViewExecutionTimedOutException
│   └── ModelView/CustomView/              CustomViewDetailsView, CustomViewListItemView, ViewPage, ViewResultView
└── Infrastructure/
    ├── Http/Controller/                   ViewController
    ├── Http/Request/CustomView/           seven FormRequests
    ├── Persistence/<Aggregate>/           Eloquent<X>ViewBuilder (Asset, Ticket, Contract, Location)
    ├── Persistence/CustomView/            EloquentCustomViewRepository (new methods), CustomViewMapper (catalog check)
    ├── CustomView/                        ViewFilterApplier, ViewPaginator, StatementTimeout, InvalidViewCursorException
    ├── Cache/                             CachedViewBuilder
    └── Providers/                         LogisServiceProvider: tag view_builders, extend with the cache
config/views.php                           cache_ttl_seconds, execution_timeout_ms
database/migrations/                       add_view_indexes
routes/api.php                             seven routes under v1/views
docs/openapi/openapi.yaml                  seven operations
AGENTS.md                                  exception table: ForbiddenException 403, TimeoutException 504 (logged)
```

- `InvalidViewCursorException` lives in Infrastructure because only the adapter can decode a cursor. It extends `InvalidValueException`, so it returns 422.
- Deptrac holds: the catalog, the policy and the value objects are in Domain; the port, the registry and the criteria in Application; the query building, the cursor, the timeout and the cache in Infrastructure.

## Code style

A field map and the application of a filter:

```php
final class EloquentAssetViewBuilder implements ViewBuilder
{
    public function __construct(private readonly ViewPaginator $paginator)
    {
    }

    public function type(): ViewType
    {
        return ViewType::MyAssets;
    }

    public function execute(ViewExecutionCriteria $criteria): ViewPage
    {
        $query = AssetModel::query()->visibleTo($criteria->customers);

        return $this->paginator->paginate(query: $query, fields: self::fields(), criteria: $criteria);
    }

    /**
     * @return array<string, FieldMapping>
     */
    private static function fields(): array
    {
        return [
            'id' => FieldMapping::column(column: 'id'),
            'serial_number' => FieldMapping::column(column: 'serial_number'),
            'status' => FieldMapping::column(column: 'status'),
            'location' => FieldMapping::relation(relation: 'location', foreignKey: 'location_id', label: 'title'),
            'created_at' => FieldMapping::column(column: 'created_at'),
        ];
    }
}
```

```php
public function apply(Builder $query, ViewFilter $filter, string $column): void
{
    match ($filter->operator()) {
        FilterOperator::Equals => $query->where(column: $column, operator: '=', value: $filter->value()),
        FilterOperator::In => $query->whereIn(column: $column, values: $filter->values()),
        FilterOperator::Contains => $query->where(column: $column, operator: 'like', value: LikePattern::contains(value: $filter->text())),
        FilterOperator::Between => $query->where(column: $column, operator: '>=', value: $filter->range()->startOfFirstDay())
            ->where(column: $column, operator: '<', value: $filter->range()->startOfDayAfterLast()),
        …
    };
}
```

The style of `AGENTS.md` applies:

- named arguments everywhere, except scope calls such as `->visibleTo($customers)`, which stay positional because they are resolved through `__call`;
- `final` classes and `declare(strict_types=1)`;
- no comments;
- explicit `if` guards;
- state questions in their enum (`ViewFieldType::allows()`, `FilterOperator::expectsList()`).

## Commands

```
make migrate      # apply add_view_indexes
make fresh        # recreate the development database with the new seed views
make qa           # Pint (read-only), Larastan, Deptrac
make docs         # validate the OpenAPI spec and regenerate docs/openapi.json
make test         # PHPUnit against logis_testing
make check        # qa + docs-lint + test, required before every commit
```

## Testing strategy

**Unit** (`tests/Unit/LogisContext/…`, mirroring `app/`):

- `ViewFieldCatalog`: every type has its fields; `choice` values follow the enums.
- `ViewFieldType::allows()` and `FilterOperator`: every allowed and forbidden combination, and the value shapes.
- `ViewDefinition`: unknown column, unknown filter field, operator not allowed for the type, wrong value per type (text length, choice, identifier, `Y-m-d` date), `between` out of order, list sizes (0, 1, 100, 101), `in_last_days` 0 and 365 accepted, -1 and 366 rejected.
- `ViewSort` (unknown and non-sortable fields), `ViewPageSize` (1, 1000, 0, 1001).
- `CustomView`: `create`, `update`, `markAsDefault`, `unmarkAsDefault`, `delete`, and their events (`DomainEventPublisher::lastEventOfType()`); the catalog invariant.
- `ViewPolicy`: owner, public of the same customer, public of another customer, private of another user, user without a customer.
- Every handler, instantiated directly with `InMemoryCustomViewRepository`, `InMemoryUserRepository` and `InMemoryViewBuilder`:
  - 404 and 403 paths;
  - the title conflict;
  - the type that can't change;
  - `set-default` replacing the previous default, and being idempotent;
  - execution: no builder call for a user without a customer, and the executing user's customers (not the owner's) reach the builder.
- `ViewBuilders`: every `ViewType` resolves; a missing or duplicate builder throws.
- The model views.

**Integration** (`tests/Feature/LogisContext/Infrastructure/…`, `RefreshDatabase`):

- Each `Eloquent<X>ViewBuilder`:
  - every operator on every field type, including the date boundaries of D3;
  - never returns rows of customers outside the visible ones, even when a filter asks for another customer's id;
  - excludes soft-deleted assets and tickets;
  - `q` matches like the global search, and `%` or `_` match literally;
  - selects only the requested columns;
  - sorts and pages with the cursor in both directions, without duplicates or gaps;
  - rejects a cursor of another sort order;
  - relations as `{id, label}` or `null`;
  - a fixed number of queries whatever the page size (`CountsQueries`).
- `CachedViewBuilder`:
  - a repeated execution runs no queries;
  - another customer, rule, definition, sort, term, limit or cursor gives another entry;
  - a failure is not cached.
- `StatementTimeout`: a heavy query with a 1 ms limit raises `ViewExecutionTimedOutException`, and the session value is restored afterwards. `SLEEP()` isn't used, because MySQL interrupts it without an error.
- `EloquentCustomViewRepository`:
  - `save` inserts and updates;
  - a duplicate title raises the conflict;
  - `findDefaultOf`;
  - every view from `findOwnedByOrPublicIn` passes `ViewPolicy::canSee`;
  - a stored view outside the catalog raises `CorruptedRecordException`.
- Schema: the indexes of D11 exist.

**Feature** (`tests/Feature/LogisContext/CustomView/*EndpointTest.php`), one test class per endpoint:

- 401 without a token, and the documented 403, 404, 409 and 422 cases.
- Isolation:
  - another customer's view returns 404 on every endpoint;
  - a public view of the same customer can be seen and executed but not changed (403);
  - executing another user's public view returns the executing user's rows.
- The response format of the API contract, `meta` of the cursor, `X-Refreshed-Token`, 201 on create.
- 504 when the execution times out (with the timeout set to 1 ms in the test's configuration).
- `DemoDataSeeder` creates the views of D14.

The existing tests keep passing.

## Boundaries

- **Always:**
  - derive the customers from the authenticated user, never from the request;
  - map field names through the catalog and the static field map, and bind every value;
  - one builder per view type in the aggregate's `Persistence/` folder;
  - update `docs/openapi/openapi.yaml` in the same change and run `make docs`;
  - run `make check` before each commit;
  - one logical change per commit (shared exceptions, domain, application, infrastructure and endpoints, indexes, seed, docs).
- **Ask first:**
  - adding a field to the catalog or an operator;
  - a limiter only for `execute`;
  - saving a sort order in the view (needs a new column);
  - cache invalidation;
  - any new dependency.
- **Never:**
  - raw SQL built from input;
  - accept customer ids or permissions from the client;
  - use a table column name in the API;
  - show another customer's view, or its existence;
  - cache by user id alone;
  - touch `logis-test/`.

## Out of scope

- The legacy `actions_data`. The brief's table has no column for it.
- A sort order saved in the view. The sort is chosen on each execution, default `id desc` (R5).
- `OR` groups between filters. The legacy code combines with `AND` only.
- Exporting results (CSV, Excel).
- Unmarking a default without choosing another.
- Cache invalidation on writes to assets, tickets, contracts or locations, because there are no write endpoints for them.

## Success criteria

1. The seven endpoints answer as described in the API contract, and `docs/openapi/openapi.yaml` documents them.
2. No query is built by concatenating input. A filter or sort on a field outside the catalog, or an operator not allowed for its type, returns 422 and never reaches SQL.
3. A user never sees another customer's view, nor rows of customers outside their visible customers. This is proven by feature tests with an unrelated customer.
4. Only the owner changes, deletes or sets a view as default. Other users who can see it get 403.
5. An owner has at most one default view per type, also under concurrent `set-default` requests.
6. `execute` returns at most 1000 rows per page, pages with a cursor, runs a fixed number of queries, and a repeated execution within 5 minutes runs none.
7. An execution that exceeds 5 seconds returns 504 and is logged.
8. The indexes of D11 exist after `make migrate`, and `down()` removes them.
9. Adding a `ViewType` case without a builder fails the `ViewBuilders` test.
10. `make check` is green.

## Resolved decisions

- **R1.** A view that doesn't exist or can't be seen returns 404; a visible view changed by a non-owner returns 403, through the new `ForbiddenException`.
- **R2.** `execute` pages with a cursor, `limit` from 1 to 1000, default 50.
- **R3.** No limiter of its own: the global `api` limiter (60 per minute) already covers every endpoint, `execute` included.
- **R4.** `view_type` can't change after creation. `PUT` returns 422 for a different type.
- **R5.** The sort is chosen on each execution (`sort.field`, `sort.direction`), default `id desc`. It isn't saved in the view.
- **R6.** Relation fields (`customer`, `contract`, `location`, ticket `status`, `asset`) are filtered by id and shown as `{id, label}`.
- **R7.** Execution timeout of 5 seconds, returned as 504 through the new `TimeoutException`, and logged.
- **R8.** `q` is supported on `execute`, reusing the `matching()` scope of the global search.
- **R9.** The list isn't paginated. At most 20 filters per view and 100 values per `in`/`not_in`. Execution results are cached for 5 minutes, keyed by definition and visibility rule.
- **R10.** `actions_data` is out of scope.

## Open questions

None.
