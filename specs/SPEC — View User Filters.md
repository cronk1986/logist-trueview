# SPEC — View User Filters

Status: approved

## Objective

Let the user who executes a view filter its rows at request time, within limits the owner of the view decides for each saved filter. This closes the gap with the legacy endpoint (`logis-test/endpoints/endpoint-2.php`), where the table sends per-column filters with every request (`columns[i][search][value]`) on top of the filters saved in the view.

Today `POST /api/v1/views/{id}/execute` accepts only `limit`, `cursor`, `sort` and `q`. To filter differently, the user has to edit the view or create another one.

### What the legacy code does

- A saved filter is a list of values for a column, always applied as `IN` (`getMyViewSqlQuery()`).
- The table's column filter is added with `AND` on top of the saved one (`selectAssetsbyFilters()`), so it can only narrow within the saved values.
- For a column without a saved filter, it offers every value that exists in the data (`getFilterByAttribute()`, a `GROUP BY` over the view's query). This spec does **not** reproduce that: the values a user can pick come from the view definition, not from the data.

### Success

- The owner decides, per saved filter, whether the executing user can narrow it (`restricted`), replace it (`open`) or not touch it (`locked`).
- `execute` accepts a `filters` list with the same shape and validation as saved filters.
- The existing endpoints, routes and response shapes keep working. The only additions are the `user_filter` field of saved filters and the optional `filters` of `execute`.

## Key design decisions

### D1. The filter mode: `user_filter`

Every saved filter has a mode, `UserFilterMode` (backed enum, `Domain/ValueObject/CustomView/`):

| `user_filter` | Applied by default | What the executing user can send for that field |
|---|---|---|
| `restricted` | The saved filter | A subset of the saved values, with `eq` or `in`. Any other value is 422 |
| `open` | The saved filter | Any valid filter for the field. It **replaces** every saved filter of that field |
| `locked` | The saved filter | Nothing: a user filter on that field is 422, never ignored in silence |

- **Default: `locked`** (least permission). The owner opens a filter explicitly.
- The enum answers its own questions: `allowsUserFilter()` (false for `locked`), `replacesSavedFilters()` (true for `open`) and `requiresDiscreteValues()` (true for `restricted`).

### D2. Rules of a saved definition

Checked by `ViewDefinition` when a view is created or updated, as 422 with a message that names the field:

| Rule | Message |
|---|---|
| All saved filters of one field share the same mode | `The filters on "created_at" must share the same user_filter mode.` |
| `restricted` only with `eq` or `in` | `A restricted filter on "model" must use eq or in.` |
| A `restricted` field has a single saved filter | `"status" can have only one restricted filter.` |

`open` and `locked` allow several filters on one field, typically a date range with `gte` and `lte`.

### D3. What the executing user may filter

A field is filterable at execution when:

- it is a **column of the view**, and has no saved filter or a saved filter that isn't `locked`; or
- it has a **saved filter in `open` or `restricted`** mode, even if the view doesn't show it.

| Requested field | Result |
|---|---|
| Saved filter `locked` | 422 `The filter on "status" is locked by the owner of the view.` |
| Saved filter `restricted`, values inside the saved ones | Replaces the saved filter of that field |
| Saved filter `restricted`, a value outside the saved ones | 422 `The filter on "status" accepts only: active, inactive.` |
| Saved filter `open` | Replaces every saved filter of that field |
| No saved filter, column of the view | Added |
| No saved filter, not a column of the view | 422 `"location" is not a column of this view, so it cannot be filtered.` |

- Each user filter also passes the catalog rules of `SPEC — Custom Asset Views.md` (D2, D3): known field, allowed operator, valid value.
- At most 20 user filters. They are combined with `AND`, with each other and with the saved filters that stay.
- Visibility doesn't change: `visibleTo()` is always applied.

### D4. The effective definition

`ViewDefinition::refinedBy(filters: UserFilters): ViewDefinition` returns a new definition whose filters are the effective ones: the saved filters minus the replaced ones, plus the user filters. It raises the 422s of D3.

`ExecuteCustomViewHandler` puts the refined definition in `ViewExecutionCriteria`. Nothing downstream changes:

- `ViewPaginator` and `ViewFilterApplier` apply the effective filters as today;
- `CachedViewBuilder` already keys by the definition's filters, so different user filters get different cache entries.

### D5. Domain model

- `ViewFilter` stays a pure condition: `column`, `operator`, `value`. User filters are `ViewFilter`s.
- `ViewFilters`, the saved filters of a view, also holds the mode of each field. `fromList()` reads `user_filter` from every item and requires it, and `toArray()` writes it back, so the stored JSON and the API show one mode per filter.
- `UserFilters` (new VO) wraps the list of user filters: at most 20, each built with `ViewFilter::fromArray()`.
- New `InvalidViewFilterException` named constructors for the messages of D2 and D3.

### D6. API

**Saved filters** (`POST /views`, `PUT /views/{id}`, and the `filters` of the details response) gain `user_filter`:

```json
{ "column": "status", "operator": "in", "value": ["active", "inactive"], "user_filter": "restricted" }
```

- Input: optional, one of `restricted`, `open`, `locked`. When absent, the FormRequest fills in `locked` before building the command, so the default lives in the API contract and the domain always receives an explicit mode.
- Output: always present.

**`POST /views/{id}/execute`** gains an optional `filters`:

```json
{
  "limit": 50,
  "sort": { "field": "id", "direction": "desc" },
  "q": "TC",
  "filters": [
    { "column": "status", "operator": "eq", "value": "active" },
    { "column": "model", "operator": "in", "value": ["TC52", "TC57"] }
  ]
}
```

| Field | Rules (FormRequest) |
|---|---|
| `filters` | nullable, array, list, at most 20 |
| `filters.*.column` | required, string |
| `filters.*.operator` | required, one of the operators |
| `filters.*.value` | present |
| `filters.*.user_filter` | prohibited: modes are only set on saved filters |

The details response (`GET /views/{id}`) already returns the saved filters. With `user_filter` in each one, the client knows what to render: the saved values of a `restricted` filter as its options, a free input for `open`, and nothing for `locked`. This replaces the legacy's "possible values" lookup.

### D7. Stored data

- The repository always stores `user_filter` in each filter. Reading a stored filter without it is a corrupted record (a logged 500), like a filter outside the catalog. No default is applied in silence when reading.
- The project isn't deployed. The existing rows are regenerated with `make fresh`, so there's no data migration.
- `CustomViewFactory` and `DemoDataSeeder` write explicit modes. The seed covers the three modes:

  | View | Filters |
  |---|---|
  | "Zebra scanners" (`my_assets`, demo user) | `manufacturer eq Zebra` **locked**; `status in [active, inactive]` **restricted** |
  | "Open tickets" (`my_tickets`, public) | `status eq <Open>` **open** |
  | "Retired devices" (`my_assets`, limited user, public) | `status eq retired` **locked**; `updated_at in_last_days 365` **open** |
  | "Active contracts" (other customer) | Factory default, **locked** |

## Architecture

```
app/LogisContext/
├── Domain/
│   ├── ValueObject/CustomView/   UserFilterMode (new), UserFilters (new),
│   │                             ViewFilters (modes per field), ViewDefinition (D2 rules, refinedBy())
│   └── Exception/CustomView/     InvalidViewFilterException (new named constructors)
├── Application/
│   └── Query/CustomView/         ExecuteCustomView gains filters; its handler refines the definition
└── Infrastructure/
    └── Http/Request/CustomView/  ValidatesViewConfiguration (user_filter, default locked),
                                  ExecuteCustomViewRequest (filters)
database/factories/CustomViewFactory.php, database/seeders/DemoDataSeeder.php
docs/openapi/openapi.yaml         ViewFilter.user_filter, ViewExecutionInput.filters, the 422 cases
```

`CustomViewMapper`, `EloquentCustomViewRepository`, the view builders, `ViewPaginator`, `ViewFilterApplier` and `CachedViewBuilder` don't change beyond the JSON shape they already pass through.

## Code style

```php
public function refinedBy(UserFilters $filters): self
{
    $effective = $this->filters;

    foreach ($filters->byField() as $field => $requested) {
        $effective = $effective->withUserFiltersFor(
            field: $this->filterableField(field: $field),
            requested: $requested,
        );
    }

    return new self(catalog: $this->catalog, columns: $this->columns, filters: $effective);
}
```

The rules of `AGENTS.md` apply: named arguments, `final` classes, no comments, explicit `if` guards, and state questions answered by the enum (`UserFilterMode::allowsUserFilter()`).

## Commands

```
make fresh      # regenerate the development database with explicit modes
make qa         # Pint (read-only), Larastan, Deptrac
make docs       # validate the OpenAPI spec and regenerate docs/openapi.json
make test       # PHPUnit against logis_testing
make check      # qa + docs-lint + test, required before every commit
```

## Testing strategy

**Unit** (`tests/Unit/LogisContext/…`):

- `UserFilterMode`: the three questions for each case.
- `ViewFilters`: the mode is required, round-trips through `toArray()`, and mixed modes on one field are rejected.
- `ViewDefinition`, the D2 rules: `restricted` with `contains` or a date operator; two `restricted` filters on one field; `open` and `locked` ranges accepted.
- `ViewDefinition::refinedBy()`, every row of D3:
  - `locked` is rejected;
  - `restricted` accepts a subset and rejects a value outside it and a non-discrete operator;
  - `open` replaces every saved filter of its field;
  - a visible column without a saved filter is added;
  - a hidden field without a saved filter is rejected;
  - a hidden field with an `open` filter is accepted;
  - the catalog rules still apply;
  - more than 20 user filters are rejected.
- `ExecuteCustomViewHandler`: the builder receives the refined definition, and without `filters` it receives the saved one unchanged.

**Integration:**

- `EloquentCustomViewRepositoryTest`: `user_filter` round-trips, and a stored filter without it raises `CorruptedRecordException`.
- `CachedViewBuilderTest`: different user filters miss the cache.

**Feature:**

- `CreateCustomViewEndpointTest` and `UpdateCustomViewEndpointTest`:
  - `user_filter` defaults to `locked`;
  - the three modes are stored and returned;
  - the D2 violations are 422.
- `ExecuteCustomViewEndpointTest`:
  - `restricted` narrows, and a value outside it is 422;
  - `open` replaces and can widen (e.g. `retired`);
  - `locked` is 422;
  - a hidden field is 422;
  - `filters.*.user_filter` is 422;
  - rows stay limited to the visible customers.
- `DemoDataSeederTest`: the seeded views carry the modes of D7.

The existing tests keep passing, updated to write explicit modes where they store filters.

## Boundaries

- **Always:**
  - derive the customers from the authenticated user;
  - validate user filters with the same catalog as saved filters;
  - update `docs/openapi/openapi.yaml` and run `make docs`;
  - `make check` before each commit;
  - work on `feat/custom-views`.
- **Ask first:**
  - a lookup of possible values from the data (the legacy `GROUP BY`);
  - executing a view without saving it;
  - letting user filters use `OR`.
- **Never:**
  - ignore a user filter in silence;
  - let a user filter widen a `restricted` or touch a `locked` filter;
  - apply a default mode when reading stored data.

## Out of scope

- Possible values computed from the data, and a listing without a saved view.
- Saving the user's filters back into the view. The owner edits the view with `PUT`.

## Success criteria

1. Saved filters carry `user_filter`. It defaults to `locked` on input and is always present on output.
2. The D2 rules reject invalid saved definitions with 422.
3. `execute` applies user filters following D3: `restricted` narrows, `open` replaces, `locked` and hidden fields are rejected.
4. No existing endpoint, route or response field changes, apart from the additions of D6.
5. `make fresh` seeds the three modes, and `docs/openapi/openapi.yaml` documents them. `make check` is green.

## Resolved decisions

- **R1.** One mode per filter: `restricted`, `open` or `locked`. Default `locked`.
- **R2.** `open`: the user filter replaces every saved filter of that field.
- **R3.** `locked`: always applied. A user filter on that field is 422.
- **R4.** Filterable at execution: the view's columns, plus fields with an `open` or `restricted` saved filter.
- **R5.** `restricted` only with `eq` or `in`, and a single saved filter per field.
- **R6.** All saved filters of a field share one mode; otherwise 422 on create or update.
- **R7.** The development data is regenerated with `make fresh`. Stored filters always carry their mode.

## Open questions

None.
