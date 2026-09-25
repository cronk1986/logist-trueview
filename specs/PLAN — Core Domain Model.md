# PLAN — Core Domain Model

Spec: `specs/SPEC — Core Domain Model.md` (Status: done)
Status: approved

## Overview

This plan builds the schema, aggregates and persistence mapping for customers, users' customers, ticket statuses, contracts, locations, assets, tickets and custom views, after regrouping the existing persistence by aggregate.

The work is sliced by aggregate, in foreign key order. Each slice goes from the migration to a repository test against MySQL: migration, value objects, exceptions, entity, repository interface, Eloquent model, mapper, Eloquent repository, factory, binding and tests. After every task the schema migrates, `findById` returns a fully mapped entity, and `make check` is green.

The files of each slice are small and uniform, so a slice counts as one task even though it touches more than five files. Splitting it into domain and persistence would leave half-built aggregates between tasks.

Every task ends with its tests and `make check`, and becomes one commit. The mandatory post-implementation flow from `AGENTS.md` runs at each checkpoint: `make qa`, `code-simplify`, `make test`, `code-review-and-quality`.

## Dependency graph

```
T1 group Persistence/ by aggregate (pure move)
 └─ T2 UserMapper + AccessTokenMapper (pure refactor)
     └─ T3 Customer (one-level hierarchy)
         ├─ T4 users.customer_id → User::customer(): ?Customer
         ├─ T6 Contract ──┐
         ├─ T7 Location ──┤
         │                └─ T8 Asset (soft deletes, ?Contract, ?Location)
         │                     └─ T9 Ticket (Customer, TicketStatus, User, ?Asset) ← T4, T5
         └─ T11 CustomView (User, Customer) ← T4, T10
T5 TicketStatus + TicketStatusSeeder (no dependencies besides T2)
T10 ViewType, ViewColumns, ViewFilter, ViewFilters (pure domain)
T12 DemoDataSeeder (after T9 and T11)
```

## Architecture decisions

These come from the spec and are not redecided here:

- Every foreign key with a constraint is held as an object in the entity. A nullable key gives a nullable object (R4, D2).
- Related objects are read-only snapshots. Each aggregate changes only through its own repository.
- `<Aggregate>Mapper::toDomain()` is the only place that builds an entity from a model. The mappers compose one another, and each repository eager loads its whole graph with `with()` (D2, D4).
- The customer hierarchy has one level: `Customer` rejects a parent that has a parent (R6).
- Repositories declare only `findById`, except `UserRepository` and `AccessTokenRepository`, which keep their current methods (D4).
- `columns` and `filters` are JSON. `ViewColumns` and `ViewFilters` validate only their structure (D5).
- Enums are MySQL `ENUM` columns, cast to PHP backed enums from the domain (D7).
- No endpoint changes, so the OpenAPI spec doesn't change. `GET /auth/me` keeps its response.

### How each mapper handles optional relations

```php
private function contract(AssetModel $model): ?Contract
{
    if ($model->contract === null) {
        return null;
    }

    return $this->contractMapper->toDomain(model: $model->contract);
}
```

### Eager-loaded graph per repository

| Repository | `with()` |
|---|---|
| Customer | `parent` |
| User | `customer.parent` |
| Contract, Location | `customer.parent` |
| Asset | `customer.parent`, `contract.customer.parent`, `location.customer.parent` |
| Ticket | `customer.parent`, `status`, `creator.customer.parent`, `asset.customer.parent`, `asset.contract.customer.parent`, `asset.location.customer.parent` |
| CustomView | `user.customer.parent`, `customer.parent` |

Each repository test asserts that `findById` runs a fixed number of queries (`DB::enableQueryLog()`), whatever the data.

## Tasks

### Phase 1 — Regroup the existing persistence

#### Task 1: Group `Infrastructure/Persistence/` by aggregate

**Description:** Move the existing classes into per-aggregate folders without changing any behaviour (D9).

**Acceptance criteria:**
- [x] `Persistence/User/{UserModel, EloquentUserRepository, BcryptPasswordHasher}` and `Persistence/AccessToken/{AccessTokenModel, EloquentAccessTokenRepository}` exist, with their namespaces updated. No PHP file is left directly in `Persistence/`.
- [x] The imports are updated in `LogisServiceProvider`, `UserFactory`, `DatabaseSeeder`, `CurrentAccessToken`, the middleware and the tests. The Sanctum model and the morph map still resolve.
- [x] `tests/Feature/LogisContext/Infrastructure/Persistence/EloquentAccessTokenRepositoryTest.php` moves to `.../Persistence/AccessToken/`.
- [x] The file paths in `specs/SPEC — Authentication.md` and `specs/PLAN — Authentication.md` are updated.

**Verification:** `make check`. The 71 existing tests pass unchanged. `grep -rn "Infrastructure\\\\Persistence\\\\[A-Z][a-zA-Z]*;" app tests database` finds no import without an aggregate folder.

**Dependencies:** None

**Files:** 5 moved classes, `LogisServiceProvider.php`, `UserFactory.php`, `DatabaseSeeder.php`, callers and tests, 2 specs

**Scope:** M (mechanical) · **Commit:** `refactor(auth): group persistence by aggregate`

#### Task 2: Extract `UserMapper` and `AccessTokenMapper`

**Description:** Move the private `toDomain()` methods of both repositories into mappers, which the repositories receive through their constructors (D4).

**Acceptance criteria:**
- [x] `Persistence/User/UserMapper::toDomain(UserModel $model): User` and `Persistence/AccessToken/AccessTokenMapper::toDomain(AccessTokenModel $model): AccessToken` exist.
- [x] The repositories no longer have a private `toDomain()`. The container autowires the mappers.
- [x] Unit tests for each mapper build an unsaved model with `forceFill()` and assert every field of the entity.

**Verification:** `make check`. The existing repository tests pass unchanged.

**Dependencies:** T1

**Files:** 2 mappers, 2 repositories, 2 mapper tests

**Scope:** S · **Commit:** `refactor(auth): extract persistence mappers`

### Checkpoint: Regrouping
- [x] `make check` is green, and `make up` plus a manual login and `/me` in Swagger still work.
- [x] Post-implementation flow. Human review before continuing.

### Phase 2 — Customers and users

#### Task 3: Customer aggregate

**Description:** The `customers` table and the `Customer` aggregate, with a one-level hierarchy (R6).

**Acceptance criteria:**
- [x] Migration `create_customers_table`: `erp_id` unique, `name`, `parent_customer_id` nullable FK to `customers` with `restrictOnDelete`, timestamps.
- [x] Domain:
  - `CustomerId` and `ErpId` with `InvalidCustomerIdException` and `InvalidErpIdException`;
  - `Customer(id, erpId, name, ?Customer parent)` with `isSubAccount()`;
  - `CustomerCannotBeItsOwnParentException` when the parent has the same id;
  - `CustomerHierarchyTooDeepException` when the parent has a parent;
  - `CustomerRepository::findById`.
- [x] Infrastructure: `CustomerModel` (`parent()`, `subAccounts()`), `CustomerMapper` (maps the parent through itself), `EloquentCustomerRepository` (`with('parent')`), `CustomerFactory` (with a `subAccountOf()` state), and the binding.
- [x] Tests:
  - unit tests for the value objects and the entity invariants;
  - repository tests: a top-level customer, a sub-account with its parent, a missing id, and the query count;
  - schema test: duplicated `erp_id`, and deleting a parent that has sub-accounts fails.

**Verification:** `make check`

**Dependencies:** T2

**Scope:** M · **Commit:** `feat(core): add the customer aggregate`

#### Task 4: Users belong to an optional customer

**Description:** Add `users.customer_id` and `User::customer(): ?Customer` (R5).

**Acceptance criteria:**
- [x] Migration `add_customer_id_to_users_table`: nullable FK to `customers` with `restrictOnDelete`. `down()` drops the foreign key and the column.
- [x] `User` gains `?Customer $customer`. `UserMapper` uses `CustomerMapper`, and `EloquentUserRepository` loads `customer.parent`.
- [x] `UserModel::customer()` relation. `UserFactory` creates a customer by default and has a `withoutCustomer()` state.
- [x] Tests: the repository maps a user with and without a customer; the query count; `/auth/me` and the login tests are unchanged.

**Verification:** `make check`. `make fresh` migrates on the development database.

**Dependencies:** T3

**Scope:** M · **Commit:** `feat(auth): link users to an optional customer`

### Checkpoint: Customers and users
- [x] `make check` is green. Post-implementation flow. Human review.

### Phase 3 — Reference and customer-owned catalogs

#### Task 5: TicketStatus aggregate and seeder

**Acceptance criteria:**
- [x] Migration `create_ticket_statuses_table`: `name` unique, timestamps.
- [x] `TicketStatusId`, `InvalidTicketStatusIdException`, `TicketStatus(id, name)`, `TicketStatusRepository::findById`.
- [x] `TicketStatusModel`, `TicketStatusMapper`, `EloquentTicketStatusRepository`, `TicketStatusFactory`, and the binding.
- [x] `TicketStatusSeeder`, called from `DatabaseSeeder` in every environment: Open, In Progress, Awaiting Parts, Resolved, Closed. Idempotent through `upsert` on `name`.
- [x] Tests: unit tests, the repository test, and the seeder run twice without duplicates.

**Verification:** `make check`

**Dependencies:** T2

**Scope:** M · **Commit:** `feat(core): add ticket statuses`

#### Task 6: Contract aggregate

**Acceptance criteria:**
- [x] Migration `create_contracts_table`: `customer_id` FK restrict, `name` indexed, `status` ENUM(`active`, `inactive`), timestamps.
- [x] `ContractId`, `ContractStatus`, `InvalidContractIdException`, `Contract(id, Customer customer, name, status)` with `isActive()`, `ContractRepository::findById`.
- [x] `ContractModel` (casts `status` to `ContractStatus`), `ContractMapper` (uses `CustomerMapper`), `EloquentContractRepository` (`with('customer.parent')`), `ContractFactory`, and the binding.
- [x] Tests: unit tests, the repository test (including the customer object and the query count), and the schema test (deleting a customer with contracts fails).

**Verification:** `make check`

**Dependencies:** T3

**Scope:** M · **Commit:** `feat(core): add the contract aggregate`

#### Task 7: Location aggregate

**Acceptance criteria:**
- [x] Migration `create_locations_table`: `customer_id` FK restrict, `title`, nullable `full_address`, `city`, `state`, `postal_code`, timestamps.
- [x] `LocationId`, `InvalidLocationIdException`, `Location(id, Customer customer, title, ?fullAddress, ?city, ?state, ?postalCode)`, `LocationRepository::findById`.
- [x] `LocationModel`, `LocationMapper`, `EloquentLocationRepository`, `LocationFactory`, and the binding.
- [x] Tests: unit tests, and the repository test with null address fields.

**Verification:** `make check`

**Dependencies:** T3

**Scope:** M · **Commit:** `feat(core): add the location aggregate`

### Phase 4 — Assets and tickets

#### Task 8: Asset aggregate

**Acceptance criteria:**
- [x] Migration `create_assets_table`:
  - `customer_id` FK restrict; `serial_number` unique; `manufacturer`, `model`, `item_name`;
  - `contract_id` and `location_id` nullable FKs with `nullOnDelete`;
  - `status` ENUM(`active`, `inactive`, `retired`);
  - timestamps and `softDeletes`;
  - composite index `(customer_id, status)`.
- [x] Domain:
  - `AssetId`, `SerialNumber` (trimmed, non-empty, at most 255 characters), `AssetStatus`, and their exceptions;
  - `Asset(id, Customer, SerialNumber, manufacturer, model, itemName, ?Contract, ?Location, AssetStatus, createdAt)` with `isActive()`;
  - `AssetRepository::findById`.
- [x] Infrastructure:
  - `AssetModel` with `SoftDeletes`, relations and the status cast;
  - `AssetMapper` composing the customer, contract and location mappers;
  - `EloquentAssetRepository` with the graph from the table above;
  - `AssetFactory` with `withContract()` and `withLocation()` states;
  - the binding.
- [x] Tests:
  - unit tests;
  - repository tests: with and without a contract and a location, a soft-deleted asset returns `null`, and the query count;
  - schema tests: duplicated serial number, including a soft-deleted asset (R2); deleting a contract or a location nulls the FK.

**Verification:** `make check`

**Dependencies:** T6, T7

**Scope:** L (uniform files) · **Commit:** `feat(core): add the asset aggregate`

#### Task 9: Ticket aggregate

**Acceptance criteria:**
- [x] Migration `create_tickets_table`:
  - `customer_id` FK restrict; `asset_id` nullable FK with `nullOnDelete`;
  - `repair_id` and `incident_number` unique; `subject`; `status_id` FK restrict; `issue_description` text;
  - `created_by` FK to `users`, restrict;
  - timestamps and `softDeletes`;
  - composite index `(customer_id, status_id)`.
- [x] Domain:
  - `TicketId`, `RepairId`, `IncidentNumber`, and their exceptions;
  - `Ticket(id, Customer, ?Asset, RepairId, IncidentNumber, subject, TicketStatus, issueDescription, User createdBy, createdAt)`;
  - `TicketRepository::findById`.
- [x] Infrastructure: `TicketModel` (`SoftDeletes`, `asset()`, `status()`, `creator()`), `TicketMapper`, `EloquentTicketRepository` (the deepest graph), `TicketFactory`, and the binding.
- [x] Tests:
  - unit tests;
  - repository tests: with and without an asset, soft-deleted, and a fixed query count with the whole graph;
  - schema tests: duplicated `repair_id` and `incident_number`; deleting an asset nulls `asset_id`; deleting a status in use fails.

**Verification:** `make check`

**Dependencies:** T4, T5, T8

**Scope:** L (uniform files) · **Commit:** `feat(core): add the ticket aggregate`

### Checkpoint: Assets and tickets
- [x] `make check` is green, and `make fresh` migrates. Post-implementation flow. Human review.

### Phase 5 — Custom views

#### Task 10: View type, columns and filters value objects

**Description:** Pure domain, with no persistence (D5).

**Acceptance criteria:**
- [x] `ViewType` enum: `my_assets`, `my_tickets`, `my_contracts`, `my_locations`.
- [x] `ViewColumns::fromList()`: a non-empty list of unique, non-empty strings. It throws `InvalidViewColumnsException`.
- [x] `ViewFilter::fromArray()` takes `column`, `operator` and `value`, where the value is a scalar, null or a list of scalars. `ViewFilters::fromList()` wraps the filters. Both throw `InvalidViewFilterException`, and `toArray()` returns the JSON shape of D5.
- [x] Unit tests: valid cases, an empty list, duplicates, missing keys, a nested array value, and the `toArray()` round-trip.

**Verification:** `make check`

**Dependencies:** None (can run in parallel with Phases 2–4)

**Scope:** S · **Commit:** `feat(views): add view column and filter value objects`

#### Task 11: CustomView aggregate

**Acceptance criteria:**
- [x] Migration `create_custom_views_table`:
  - `user_id` FK `cascadeOnDelete`; `customer_id` FK restrict;
  - `title`, nullable `description`, `view_type` ENUM of the four values, `columns` json, `filters` json;
  - `is_default` and `is_public` booleans, default `false`; timestamps;
  - unique `(user_id, title)`, index `(user_id, view_type)`.
- [x] Domain: `CustomViewId`, `InvalidCustomViewIdException`, `InvalidCustomViewTitleException`, `CustomView(id, User, Customer, title, ?description, ViewType, ViewColumns, ViewFilters, isDefault, isPublic)` and `CustomViewRepository::findById`.
- [x] Infrastructure:
  - `CustomViewModel` with casts: `columns` and `filters` to `array`, booleans, `view_type` to `ViewType`;
  - `CustomViewMapper`, `EloquentCustomViewRepository`, `CustomViewFactory` and the binding.
- [x] Tests:
  - unit tests;
  - repository test: the JSON is mapped to value objects, plus the query count;
  - schema tests: a duplicated title for the same user fails; the same title for different users is allowed; deleting the user deletes their views.

**Verification:** `make check`

**Dependencies:** T4, T10

**Scope:** M · **Commit:** `feat(views): add the custom view aggregate`

### Phase 6 — Demo data

#### Task 12: DemoDataSeeder

**Acceptance criteria:**
- [x] `DemoDataSeeder` runs only in `local` and `testing`, and follows D8:
  - a "Logis Demo" parent customer (`C-1000`) with two sub-accounts, plus one unrelated customer;
  - `demo@logis.test` linked to the parent customer;
  - per customer: 3 contracts, 5 locations, 30 assets (some Zebra and Honeywell) and 20 tickets;
  - two views for the demo user: a default `my_assets` view and a public `my_tickets` view.
- [x] `DatabaseSeeder` calls `TicketStatusSeeder` always, and `DemoDataSeeder` only in `local` and `testing`.
- [x] Test: after seeding `logis_testing`, the counts match and the demo user can still log in.

**Verification:** `make fresh` then `make check`

**Dependencies:** T9, T11

**Scope:** S · **Commit:** `feat(core): seed demo customers, assets, tickets and views`

### Checkpoint: Complete
- [x] Every success criterion in the spec is met.
- [x] `make fresh` and `make check` are green.
- [x] Post-implementation flow. Human review before the last commit.

## Risks and mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| The eager-loaded graph misses a relation, and a mapper triggers a lazy load (N+1) | Medium | Query-count assertion in every repository test. `Model::preventLazyLoading()` is enabled outside production, but Laravel only enforces it on collections of several models (spec R9). |
| Existing data breaks the one-level hierarchy, and the mapper throws | Low | Only seeders and factories write customers. `subAccountOf()` rejects sub-account parents, and the mapper reports corrupted rows as a 500 (spec R8). |
| Moving the Sanctum model breaks auth at runtime (T1) | Medium | The 71 auth tests cover login, rotation and logout, plus a manual check in Swagger at the checkpoint. |
| The ENUM cast fails on an unknown value | Low | Casts to backed enums fail loudly, and values are only written by factories and seeders. |
| Test time grows with deeper factories | Low | The factories create only the required relations by default. Optional ones use states. |

## Parallelisation

- T5 and T10 are independent of the customer chain and can run in parallel.
- T6 and T7 are independent of each other after T3.
- Migrations are timestamped in dependency order, so parallel work must keep the timestamps ordered.

## Resolved decisions

- **Commit scope.** New scope `core` (core domain model: aggregates, migrations, factories and seeders), added to `AGENTS.md` in T1. Custom views keep `views`, and users keep `auth`.
