# PLAN — View User Filters

Spec: `specs/SPEC — View User Filters.md` (Status: approved)
Status: approved

## Overview

This plan lets the user who executes a view filter its rows, within the mode the owner sets on each saved filter: `restricted`, `open` or `locked`.

The work has two vertical slices, each ending at the HTTP layer:

1. **Saved filters carry a mode.** The mode goes from the domain to the stored JSON and the API, with the D2 rules for saved definitions.
2. **`execute` accepts user filters.** `UserFilters` and `ViewDefinition::refinedBy()` produce the effective definition, which reaches the existing builders through the handler.

The first slice has a wide but mechanical footprint. Making the mode required in `ViewFilters` breaks every place that builds saved filters (factory, seed, mothers, several unit and feature tests), so those places are updated in the same task to keep the branch green.

Every task ends with its tests and `make check`, and becomes one commit on `feat/custom-views`. The mandatory post-implementation flow of `AGENTS.md` runs at each checkpoint.

## Dependency graph

```
T0 commit the spec and this plan
 └─ T1 UserFilterMode + modes in ViewFilters + default locked in the FormRequest
    + factory, seed, mothers and tests with explicit modes + OpenAPI ViewFilter.user_filter
     └─ T2 D2 rules of saved definitions (mixed modes, restricted discrete and single) + 422 feature tests
         └─ T3 UserFilters + ViewDefinition::refinedBy() (D3, D4), unit tested
             └─ T4 execute accepts filters: query, handler, request, OpenAPI, feature and cache tests
```

## Architecture decisions

These come from the spec and are not decided again here:

- `UserFilterMode` answers its own questions: `allowsUserFilter()`, `replacesSavedFilters()`, `requiresDiscreteValues()` (D1).
- `ViewFilter` stays a pure condition. `ViewFilters` holds the mode per field; `fromList()` requires `user_filter` in every item, and `toArray()` writes it (D5).
- The default `locked` is filled in by `ValidatesViewConfiguration` before the command is built. The domain and the mapper always require an explicit mode, so a stored filter without one is a `CorruptedRecordException` (D6, D7).
- `ViewDefinition::refinedBy(UserFilters)` returns the effective definition. `ExecuteCustomViewHandler` passes it to the builders. `ViewPaginator`, `ViewFilterApplier` and `CachedViewBuilder` don't change, and the cache key covers the user filters through the definition (D4).
- The development data is regenerated with `make fresh`, with no data migration (D7).

## Tasks

### Phase 0 — Documents

#### Task 0: Commit the spec and the plan

**Acceptance criteria:**
- [ ] `docs(specs): specify user filters on view execution` commits the spec and this plan on `feat/custom-views`.

**Verification:** `make check` before the commit.

**Dependencies:** None · **Scope:** XS

### Phase 1 — Saved filters carry a mode

#### Task 1: The filter mode, end to end

**Description:** Add `user_filter` to saved filters: in the domain, the stored JSON, the request and the response (D1, D5, D6, D7).

**Acceptance criteria:**
- [ ] `Domain/ValueObject/CustomView/UserFilterMode` (`restricted`, `open`, `locked`) with `allowsUserFilter()`, `replacesSavedFilters()` and `requiresDiscreteValues()`.
- [ ] `ViewFilters::fromList()` requires a valid `user_filter` in every item. A missing or unknown mode raises `InvalidViewFilterException` (422). `modeOf(field)` returns the mode of a field, and `toArray()` includes `user_filter` in each filter.
- [ ] `ValidatesViewConfiguration`:
  - `filters.*.user_filter` is `nullable` and must be one of the enum values;
  - `listOf('filters')` fills in `locked` where it is missing.
- [ ] The stored JSON and `CustomViewDetailsView` carry `user_filter`. A stored filter without it raises `CorruptedRecordException`.
- [ ] Explicit modes in `CustomViewFactory`, in `DemoDataSeeder` (the table of D7), and in every test that builds saved filters: `CustomViewMother`, `ViewExecutionCriteriaMother`, the `ViewFilters`, `ViewDefinition` and `CustomView` unit tests, the repository, cache and details-view tests, and the create, update and show feature tests.
- [ ] `openapi.yaml`: `ViewFilter.user_filter`, with the table of modes and the default.

**Verification:**
- Unit tests: `UserFilterModeTest`; `ViewFiltersTest` (the mode is required and round-trips).
- `EloquentCustomViewRepositoryTest`: the mode round-trips, and a row without it is corrupted.
- Create and update feature tests: the default is `locked`, and the three modes are stored and returned.
- `DemoDataSeederTest`: the modes of D7.
- `make fresh`, `make docs`, then `make check`.

**Dependencies:** T0 · **Scope:** L. The change itself is small; the size comes from the tests and fixtures that must write the mode · **Commit:** `feat(views): let the owner set a user filter mode on saved filters`

#### Task 2: Rules of saved definitions

**Description:** Reject saved definitions that break D2.

**Acceptance criteria:**
- [ ] 422 with the messages of D2 for:
  - mixed modes on one field;
  - a `restricted` filter with an operator other than `eq` or `in`;
  - two `restricted` filters on one field.

  The mixed-modes check lives in `ViewFilters`; the other two in `ViewDefinition`, which knows the operators allowed per field.
- [ ] `open` and `locked` ranges (`gte` and `lte` on one field) are accepted.
- [ ] `openapi.yaml` states the rules in the `ViewFilter` description.

**Verification:** `ViewDefinitionTest` and `ViewFiltersTest` cases for each rule; one 422 feature test per rule in the create endpoint test. `make docs`, `make check`.

**Dependencies:** T1 · **Scope:** S · **Commit:** `feat(views): validate the user filter modes of saved definitions`

### Checkpoint: Saved filters
- [ ] `GET /views/1` shows `locked` and `restricted` filters after `make fresh`.
- [ ] `make check` is green. Post-implementation flow. Human review before the execution slice.

### Phase 2 — User filters on execution

#### Task 3: The effective definition

**Description:** Build `UserFilters` and `ViewDefinition::refinedBy()` following D3 and D4, tested without HTTP or a database.

**Acceptance criteria:**
- [ ] `UserFilters` (`Domain/ValueObject/CustomView/`):
  - `fromList(array)`: at most 20 filters, each built with `ViewFilter::fromArray()`;
  - `isEmpty()` and `byField()`.
- [ ] `ViewDefinition::refinedBy(UserFilters)` returns a new definition, following every row of D3:
  - `locked` → 422;
  - `restricted` → accepts a subset with `eq` or `in`, and replaces the saved filter;
  - `open` → replaces every saved filter of the field;
  - a column without a saved filter → added;
  - a field that is neither a column nor has an `open` or `restricted` filter → 422;
  - the catalog rules still apply.
- [ ] The saved filters that stay keep their modes. Added user filters carry no mode of their own and are only used for execution.
- [ ] New `InvalidViewFilterException` named constructors with the messages of D3.

**Verification:** `ViewDefinitionTest` (or a new `ViewDefinitionRefinementTest`) covers every row of D3 and the limit of 20 filters; `UserFiltersTest`. `make check`.

**Dependencies:** T2 · **Scope:** M · **Commit:** `feat(views): refine a view definition with user filters`

#### Task 4: `execute` accepts user filters

**Description:** Expose the refinement on `POST /views/{id}/execute` (D4, D6).

**Acceptance criteria:**
- [ ] `ExecuteCustomView` gains `filters: array`. The handler builds `UserFilters` and puts `definition->refinedBy()` in `ViewExecutionCriteria`. With no filters, the saved definition is passed unchanged.
- [ ] `ExecuteCustomViewRequest` validates:
  - `filters`: `nullable`, `array`, `list`, at most 20;
  - `filters.*.column`: `required`, `string`;
  - `filters.*.operator`: `required`, one of the operators;
  - `filters.*.value`: `present`;
  - `filters.*.user_filter`: `prohibited`.
- [ ] `openapi.yaml`: `ViewExecutionInput.filters`, the rules of D3, and 422 examples.

**Verification:**
- `ExecuteCustomViewHandlerTest`: the builder receives the refined definition, and receives the saved one without filters.
- `CachedViewBuilderTest`: different user filters miss the cache.
- `ExecuteCustomViewEndpointTest`:
  - on the seeded-style views, `restricted` narrows, and a value outside it is 422;
  - `open` replaces and widens;
  - `locked` is 422;
  - a hidden field is 422;
  - `user_filter` in a user filter is 422;
  - the rows stay within the visible customers.
- A manual check with `curl` against `make fresh` data.
- `make docs`, then `make check`.

**Dependencies:** T3 · **Scope:** M · **Commit:** `feat(views): accept user filters when executing a view`

### Checkpoint: Complete
- [ ] Every success criterion of the spec is met.
- [ ] `make fresh`, `make docs` and `make check` are green.
- [ ] Post-implementation flow (`make qa`, code-simplify, `make test`, code review, `make check`). The spec is marked `done`, in a `docs(specs)` commit.

## Risks and mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Requiring `user_filter` in `ViewFilters` breaks many tests and fixtures at once | Medium | T1 updates them in the same commit. Test helpers (`CustomViewMother`, `ViewExecutionCriteriaMother`) default to `locked`, so most tests only change through their helper. |
| The development database keeps filters without a mode, and reading them fails with a 500 | Low | `make fresh` in T1, as agreed. The project isn't deployed. |
| `restricted` subset checks compare values of different types (`"1"` vs `1`, case of text) | Medium | Compare with the same strict rules the catalog uses: identifiers are integers, choices are exact enum values, text is compared exactly. A test covers `"Active"` vs `active`. |
| Cached pages computed before T1 have filters without `user_filter` in their key | Low | Cache keys are hashes of the definition, so the new shape produces new keys. Old entries expire within 5 minutes. |
| The FormRequest default and the domain drift apart (a path that builds a command without the default) | Low | Only `ValidatesViewConfiguration` builds saved filters from input. The handler unit tests use explicit modes, and the feature tests assert the default. |

## Parallelisation

None. Each task builds on the previous one, and all four touch the view value objects.

## Open questions

None.
