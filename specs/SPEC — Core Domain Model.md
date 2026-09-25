# SPEC — Core Domain Model

Status: approved

## Objective

Build the data model that the Global Search endpoint (`search`) and the Custom Asset Views endpoint (`views`) will work on, as required by section 1.1 of `logis-test/LARAVEL_TECHNICAL_TEST.md`. This spec adds no endpoints. It delivers:

- migrations for the four core tables in the brief (`customers`, `assets`, `tickets`, `custom_views`) and for the tables their foreign keys point to (`contracts`, `locations`, `ticket_statuses`, plus `users.customer_id`);
- for each table, an Eloquent model in `Infrastructure/Persistence/<Aggregate>/` with its relationships and casts, mapped to a domain entity in `Domain/Model/<Aggregate>/` by an Eloquent repository;
- factories and seeders that give `search` and `views` realistic data to work with;
- the regrouping of the existing `Infrastructure/Persistence/` by aggregate, following the convention now recorded in `AGENTS.md`.

The consumers are the next two specs. Success means both can be written against this model without changing its schema.

### Requirements from the brief

- **Assets:** `id`, `customer_id` (FK), `serial_number` (unique, indexed), `manufacturer`, `model`, `item_name`, `contract_id` (nullable FK), `location_id` (nullable FK), `status` (enum: active, inactive, retired), timestamps, soft deletes.
- **Tickets:** `id`, `customer_id` (FK), `asset_id` (nullable FK), `repair_id` (unique, indexed), `incident_number` (unique, indexed), `subject`, `status_id` (FK), `issue_description` (text), `created_by` (FK to users), timestamps, soft deletes.
- **Customers:** `id`, `erp_id` (unique, indexed), `name`, `parent_customer_id` (nullable, self-reference for sub-accounts), timestamps.
- **Custom views:** `id`, `user_id` (FK), `customer_id` (FK), `title` (unique per user), `description`, `view_type` (enum: my_tickets, my_assets, etc.), `columns` (json), `filters` (json), `is_default`, `is_public`, timestamps.
- Section "Assessment Timeline": "4 core tables with relationships and indexes, proper foreign keys and constraints, seeders for basic data".
- Section 2: search covers Tickets, Assets, **Contracts** and **Locations**. A user can only see their **customer's** data, including sub-accounts. The views example filters with `$user->customer_id`.

## Key design decisions

### D1. Supporting tables that the foreign keys need

The brief lists four tables but references three more through foreign keys, and search needs a fourth link. They are created with the minimum columns that the legacy code (`endpoint-1.php`, `endpoint-2.php`) uses:

| Table | Why | Columns (besides `id` and timestamps) |
|---|---|---|
| `contracts` | `assets.contract_id`; searched by name | `customer_id` FK, `name` (indexed), `status` enum `active`/`inactive` |
| `locations` | `assets.location_id`; searched by title and address | `customer_id` FK, `title`, `full_address`, `city`, `state`, `postal_code` (address fields nullable) |
| `ticket_statuses` | `tickets.status_id` | `name` (unique) |
| `users.customer_id` | the views example filters with `$user->customer_id` | `customer_id` FK, nullable |

The legacy schema links assets to locations and contracts through junction tables (`tbl_asset_loc_4r6w`, `tbl_asset_contracts_5m8n`). This spec follows the brief instead: one nullable `contract_id` and one nullable `location_id` on `assets`.

Which customers a user can see is decided in the `search` spec, on top of `users.customer_id` and `customers.parent_customer_id` (D10).

### D2. Aggregates and their relations

Every table is its own aggregate, with one entity, one repository interface and one Eloquent repository:

| Aggregate | Entity | Id value object | Other value objects |
|---|---|---|---|
| Customer | `Customer` | `CustomerId` | `ErpId` |
| Contract | `Contract` | `ContractId` | `ContractStatus` (enum) |
| Location | `Location` | `LocationId` | — |
| Asset | `Asset` | `AssetId` | `SerialNumber`, `AssetStatus` (enum) |
| TicketStatus | `TicketStatus` | `TicketStatusId` | — |
| Ticket | `Ticket` | `TicketId` | `RepairId`, `IncidentNumber` |
| CustomView | `CustomView` | `CustomViewId` | `ViewType` (enum), `ViewColumns`, `ViewFilters` |
| User (existing) | `User` | `UserId` | `Email`; gains an optional `Customer` |

- **Every foreign key with a constraint is held as an object.** If the database enforces referential integrity on a relation, the aggregate holds the related entity, never a bare id. The nullability of the foreign key only decides whether the object is nullable:

  | Entity | Required (NOT NULL FK) | Optional (nullable FK) |
  |---|---|---|
  | `User` | — | `customer(): ?Customer` |
  | `Customer` | — | `parent(): ?Customer` |
  | `Contract` | `customer(): Customer` | — |
  | `Location` | `customer(): Customer` | — |
  | `Asset` | `customer(): Customer` | `contract(): ?Contract`, `location(): ?Location` |
  | `Ticket` | `customer(): Customer`, `status(): TicketStatus`, `createdBy(): User` | `asset(): ?Asset` |
  | `CustomView` | `user(): User`, `customer(): Customer` | — |

  Columns that hold an external identifier with no table and no constraint (`repair_id`, `incident_number`, `erp_id`) are plain value objects, not relations.
- Related objects are **read-only snapshots**. The owning aggregate never changes them, and its repository never saves them. `Customer` changes only through `CustomerRepository`. This keeps the consistency boundary of each aggregate small.
- **The customer hierarchy has one level** (D10). A sub-account's parent is always a top-level customer, so `parent()` of a parent is `null`, and loading a customer loads at most two rows. Without this rule, `parent(): ?Customer` would walk an unbounded chain.
- **The graph is finite and loaded eagerly.** The deepest one is `Ticket → Asset → Contract → Customer → parent`. Each repository declares the complete list of relations it eager loads (`TicketModel::query()->with(relations: ['customer.parent', 'status', 'creator.customer.parent', 'asset.customer.parent', 'asset.contract.customer.parent', 'asset.location.customer.parent'])`). The number of queries is fixed, one per relation, with no N+1. These repositories serve commands that work on one aggregate. Lists for search and views are read through query handlers that return views, so the cost of the full graph never multiplies.
- The mapping of each related model is not duplicated: `AssetMapper` uses `CustomerMapper`, `ContractMapper` and `LocationMapper`, the same mappers their own repositories use (see D4).
- The Eloquent models carry every relationship (`AssetModel::customer()`, `contract()`, `location()`, `TicketModel::asset()`, `status()`, `creator()`, `CustomerModel::parent()`, `subAccounts()`, and so on). The mappers and the read side of `search` and `views` use them.
- Id value objects follow the existing `UserId` pattern: `fromInt()`, `value()`, `equals()`, and a semantic `Invalid<X>IdException` when the value is below 1. There is no shared base class, so each exception stays specific.
- `ViewType` has four values: `my_assets`, `my_tickets`, `my_contracts` and `my_locations`, one per entity that search covers.
- Enums (`AssetStatus`, `ContractStatus`, `ViewType`) are PHP backed enums in `Domain/ValueObject/<Aggregate>/`. The Eloquent models cast the column to the domain enum.

### D3. Entities are reconstituted, not yet mutated

No use case in this spec creates or changes these aggregates. The entities therefore expose a constructor and getters, and enforce their invariants on construction:

- `Customer`: a customer can't be its own parent, and its parent can't have a parent (one level, D10).
- `SerialNumber`, `ErpId`, `RepairId`, `IncidentNumber`: trimmed, non-empty, at most 255 characters.
- `CustomView`: title trimmed, non-empty, at most 255 characters.

Behaviour methods and domain events (`CustomViewCreated`, `CustomViewSetAsDefault`, and so on) arrive with the `views` spec, together with the commands that trigger them. This spec raises no events.

### D4. Repositories: `findById` only

Each repository interface declares only `findById(<X>Id $id): ?<X>`. It proves the model-to-entity mapping and gives the next specs an anchor to extend.

Because one entity is now built by several repositories (a `Customer` inside an `Asset`, a `Ticket` or a `User`), the translation from model to entity moves from each repository's private `toDomain()` into one mapper per aggregate: `Persistence/<Aggregate>/<Aggregate>Mapper::toDomain(model: …)`. Each repository calls its own mapper, and the mappers of the related aggregates. `EloquentUserRepository` and `EloquentAccessTokenRepository` switch to `UserMapper` and `AccessTokenMapper` in the regrouping refactor (D9).

`save()`, `delete()` and search methods are added by the spec that needs them, not here (YAGNI). `UserRepository` and `AccessTokenRepository` keep their current methods.

`search` and `views` will read lists through query handlers that return views (`ModelView`), possibly with the query builder. That read side is outside this spec.

### D5. `columns` and `filters` of custom views

The legacy code stores PHP-serialised arrays keyed by legacy column names. Here they are JSON columns with this shape:

```json
{
  "columns": ["serial_number", "manufacturer", "model", "location"],
  "filters": [
    { "column": "manufacturer", "operator": "eq", "value": "Zebra" },
    { "column": "created_at", "operator": "gt", "value": "2024-01-01" }
  ]
}
```

- `ViewColumns` wraps a non-empty list of unique, non-empty strings.
- `ViewFilters` wraps a list of `ViewFilter` objects (`column`, `operator`, `value`), where the value is a scalar, null or a list of scalars.
- This spec validates the **structure** only. Whitelisting the allowed columns and operators per `ViewType`, and restricted fields, belongs to the `views` spec, because that is where the query is built.
- `CustomViewModel` casts both columns to `array`. The repository builds the value objects from them.

### D6. Constraints, deletes and indexes

| Table | Constraint / index | On delete |
|---|---|---|
| `customers` | `erp_id` unique; `parent_customer_id` FK to `customers` | restrict |
| `users` | `customer_id` FK | restrict |
| `contracts`, `locations` | `customer_id` FK; `contracts.name` index | restrict |
| `assets` | `serial_number` unique; composite `(customer_id, status)`; `contract_id`, `location_id` FK | customer: restrict; contract, location: set null |
| `tickets` | `repair_id` unique; `incident_number` unique; composite `(customer_id, status_id)`; `asset_id` FK; `status_id` FK; `created_by` FK to `users` | customer, status, creator: restrict; asset: set null |
| `custom_views` | unique `(user_id, title)`; `customer_id` FK; composite `(user_id, view_type)` | user: cascade; customer: restrict |

- `restrict` is the default. Customers, statuses and users are never deleted in cascade by accident.
- `serial_number`, `repair_id` and `incident_number` are globally unique, as the brief says. A soft-deleted asset still holds its serial number, so the number can't be reused (accepted, R2). `repair_id` and `incident_number` are identifiers from external systems (ERP, repair provider) with no table of their own, so they carry no foreign key (R3).
- Indexes built for search (full-text or otherwise) are the `search` spec's decision.
- "Only one default view per user and view type" can't be a unique index in MySQL without a generated column. It is enforced by the `views` command, not by the schema.

### D7. Migrations and existing data

- One migration per table, ordered by dependency: `customers`, `add_customer_id_to_users`, `ticket_statuses`, `contracts`, `locations`, `assets`, `tickets`, `custom_views`.
- `users.customer_id` is nullable (R5), so the migration works on existing rows. The project has never been deployed anyway: development databases are recreated with `make fresh`, and `RefreshDatabase` rebuilds `logis_testing`.
- `enum` columns are real MySQL `ENUM`s, as in the brief. The trade-off: adding a value (for example a new `view_type`) needs an `ALTER TABLE` migration. The values are few and stable, so the explicit schema wins.

### D8. Seed data

- `TicketStatusSeeder` (reference data, every environment): Open, In Progress, Awaiting Parts, Resolved, Closed.
- `DemoDataSeeder` (only `local` and `testing`, like the demo user today):
  - one parent customer "Logis Demo" (`erp_id` `C-1000`) with two sub-accounts;
  - the demo user `demo@logis.test` belongs to the parent customer;
  - per customer: 3 contracts, 5 locations, 30 assets and 20 tickets, with some Zebra and Honeywell assets so searches have hits;
  - two custom views for the demo user (one `my_assets` view marked as default, one public `my_tickets` view);
  - a second customer that is unrelated to the demo user, so later specs can test isolation between customers.
- There is one factory per model in `database/factories/`: `CustomerFactory`, `ContractFactory`, `LocationFactory`, `AssetFactory`, `TicketStatusFactory`, `TicketFactory`, `CustomViewFactory`. `UserFactory` creates a customer when none is given.

### D9. Regrouping `Infrastructure/Persistence/`

Following the new rule in `AGENTS.md`:

```
Infrastructure/Persistence/
├── User/          UserModel, UserMapper, EloquentUserRepository, BcryptPasswordHasher
├── AccessToken/   AccessTokenModel, AccessTokenMapper, EloquentAccessTokenRepository
├── Customer/      CustomerModel, CustomerMapper, EloquentCustomerRepository
├── Contract/      ContractModel, ContractMapper, EloquentContractRepository
├── Location/      LocationModel, LocationMapper, EloquentLocationRepository
├── Asset/         AssetModel, AssetMapper, EloquentAssetRepository
├── TicketStatus/  TicketStatusModel, TicketStatusMapper, EloquentTicketStatusRepository
├── Ticket/        TicketModel, TicketMapper, EloquentTicketRepository
└── CustomView/    CustomViewModel, CustomViewMapper, EloquentCustomViewRepository
```

Grouping and mappers go into two separate commits: the move is a pure `refactor`, and the mapper extraction is another one.

- `BcryptPasswordHasher` goes to `User/`, not `AccessToken/`, because it implements `Domain/Model/User/PasswordHasher`. The rule: an adapter lives in the folder of the aggregate whose interface it implements.
- The move is a pure refactor with its own commit (`refactor(auth): group persistence by aggregate`), made before any new code. Namespaces, imports, the provider, the factory, the seeder and the tests are updated. The Sanctum model and the morph map keep working.
- The file paths in `SPEC — Authentication.md` are updated in the same commit.

### D10. Customers a user can see (input for `search` and `views`)

In the legacy code the browser sends the customers in the request body, and the server trusts it. That is the vulnerability to remove:

- `endpoint-1.php`: `erpCustomerId` (a comma-separated list), `subAccountPermission` and `RmaGSearchCustomerNSIdOwn`. With `subAccountPermission == 3` it searches every customer in the list; otherwise it searches only the user's own customer.
- `endpoint-2.php`: `erp_customer_id`, `CustomerIdArray`, `subAccountPermission` and `rma_customer_group_id`. Group `1` restricts to the user's own customer; `subAccountPermission == 3` opens the list. Views belong to an `erp_contact_id`, a contact of an ERP customer.

`custom_views.customer_id` can't answer this question: it records the customer a view belongs to, and a user with no views would see nothing. The model that follows from the legacy code is:

- **own customer:** `users.customer_id` (nullable, R5). A user with no customer sees no data;
- **sub-accounts:** the customers whose `parent_customer_id` is the user's own customer. The hierarchy has **one level**. The legacy code sends a flat list of customer and sub-accounts, and has no sign of deeper nesting;
- **permission to see sub-accounts:** the legacy `subAccountPermission`. It becomes a column on `users` in the `search` spec, the first spec that uses it. This spec doesn't add it.

Visibility of custom views, taken from `endpoint-2.php` (lines 218 and 1780). The `views` spec implements it in `ViewPolicy`:

- a user sees **their own views**, plus the **public views of their own customer** (`is_public = 1 AND customer_id = <user's customer>`). A public view is shared inside its customer, not globally, because its filters carry real values (serial numbers, manufacturers, locations);
- a user with no customer sees no views: `custom_views.customer_id` is NOT NULL, so they can't own one, and no customer's public views are theirs;
- **only the owner** (`user_id`) can update or delete a view, or set it as default. The default is per owner and view type;
- **executing** a view always filters the data by the customers of the user who runs it, never by the owner's. A public view shows each user only their own data.

## Architecture

```
app/LogisContext/
├── Domain/
│   ├── Model/<Aggregate>/          <Aggregate>.php, <Aggregate>Repository.php
│   ├── ValueObject/<Aggregate>/    <Aggregate>Id, enums, SerialNumber, ErpId, RepairId, IncidentNumber,
│   │                               ViewColumns, ViewFilters, ViewFilter
│   └── Exception/<Aggregate>/      Invalid<X>IdException, InvalidSerialNumberException, InvalidErpIdException,
│                                   InvalidRepairIdException, InvalidIncidentNumberException,
│                                   InvalidViewColumnsException, InvalidViewFilterException,
│                                   InvalidCustomViewTitleException, CustomerCannotBeItsOwnParentException
└── Infrastructure/
    ├── Persistence/<Aggregate>/    <Aggregate>Model, <Aggregate>Mapper, Eloquent<Aggregate>Repository
    └── Providers/LogisServiceProvider   repository bindings
database/
├── migrations/                     8 new migrations (D7)
├── factories/                      7 new factories, UserFactory updated
└── seeders/                        DatabaseSeeder, TicketStatusSeeder, DemoDataSeeder
```

Every validation exception extends `InvalidValueException` (422). None of them reaches HTTP in this spec, but they will once commands exist.

## Code style: the Asset aggregate

```php
final class Asset
{
    public function __construct(
        private readonly AssetId $id,
        private readonly Customer $customer,
        private readonly SerialNumber $serialNumber,
        private readonly string $manufacturer,
        private readonly string $model,
        private readonly string $itemName,
        private readonly ?Contract $contract,
        private readonly ?Location $location,
        private readonly AssetStatus $status,
        private readonly CarbonImmutable $createdAt,
    ) {
    }

    public function isActive(): bool
    {
        return $this->status === AssetStatus::Active;
    }

    // getters ...
}
```

```php
final class EloquentAssetRepository implements AssetRepository
{
    public function __construct(private readonly AssetMapper $mapper)
    {
    }

    public function findById(AssetId $id): ?Asset
    {
        $model = AssetModel::query()->with(relations: 'customer')->find(id: $id->value());

        if ($model === null) {
            return null;
        }

        return $this->mapper->toDomain(model: $model);
    }
}
```

```php
final class AssetMapper
{
    public function __construct(private readonly CustomerMapper $customerMapper)
    {
    }

    public function toDomain(AssetModel $model): Asset
    {
        return new Asset(
            id: AssetId::fromInt(value: $model->id),
            customer: $this->customerMapper->toDomain(model: $model->customer),
            serialNumber: SerialNumber::fromString(value: $model->serial_number),
            manufacturer: $model->manufacturer,
            model: $model->model,
            itemName: $model->item_name,
            contract: $this->contract(model: $model),
            location: $this->location(model: $model),
            status: $model->status,
            createdAt: CarbonImmutable::parse(time: $model->created_at)->utc(),
        );
    }
}
```

- Optional relations are mapped in small private methods with an explicit `if` (`if ($model->contract === null) { return null; }`), not with inline ternaries.
- Eloquent models declare their columns with `@property` annotations and their relationships with generic return types (`@return BelongsTo<CustomerModel, $this>`) for Larastan.
- Named arguments, `declare(strict_types=1)`, `final` classes and no comments, as in `AGENTS.md`.

## Commands

```
make fresh     # recreate the development database with migrations and seeders
make migrate   # run pending migrations
make qa        # Pint (read-only), Larastan, Deptrac
make test      # PHPUnit against logis_testing
make check     # qa + docs-lint + test, required before every commit
```

## Testing strategy

- **Unit** (`tests/Unit/LogisContext/Domain/...`):
  - every value object: valid values, invalid values raise their exception, `equals()`;
  - every entity: construction, getters, invariants (`CustomerCannotBeItsOwnParentException`), helpers such as `Asset::isActive()`;
  - `ViewColumns` and `ViewFilters`: an empty list, duplicate columns, a malformed filter, and value types.
- **Repository integration** (`tests/Feature/LogisContext/Infrastructure/Persistence/<Aggregate>/Eloquent<Aggregate>RepositoryTest.php`, `RefreshDatabase`):
  - `findById` maps every column, including nulls, enums and JSON, and the strong relations as objects;
  - `findById` loads the strong relations eagerly: the number of queries is fixed, whatever the depth (`DB::enableQueryLog()`);
  - `findById` returns `null` for a missing id, and for a soft-deleted asset or ticket.
- **Schema** (`tests/Feature/Database/SchemaTest.php`):
  - unique constraints: `serial_number`, `repair_id`, `incident_number`, `erp_id`, and `(user_id, title)`;
  - delete rules: deleting a contract nulls `assets.contract_id`; deleting a customer that still has assets fails.
- **Seeders**: `DemoDataSeeder` runs on `logis_testing` and creates the expected counts. `TicketStatusSeeder` is idempotent.
- The existing 71 tests keep passing after the regrouping (D9).

## Boundaries

- **Always:** one aggregate per folder in each layer; every Eloquent model has its domain entity and a repository that maps it; `make check` before each commit; one commit per logical change (refactor first, then the schema, then the aggregates).
- **Ask first:** columns that the brief doesn't list; changing the enum values; adding junction tables; any endpoint (they belong to the `search` and `views` specs).
- **Never:** touch `logis-test/research.md`; expose Eloquent models outside Infrastructure; change or save a related aggregate through the entity that holds it; add repository methods with no consumer.

## Out of scope

- Endpoints and the Swagger documentation for them (the `search` and `views` specs).
- Search indexes, caching and resolving sub-account permissions (`search`).
- Whitelisting view columns and operators, dynamic queries, policies, and the "one default view" rule (`views`).
- Commands that create or change assets, tickets, customers, contracts or locations. The brief doesn't ask for them.

## Success criteria

1. `make fresh` creates the 7 new tables and `users.customer_id` with the constraints and indexes from D6, and seeds the data from D8.
2. Each of the 8 aggregates has an entity, value objects, a repository interface, an Eloquent model in `Persistence/<Aggregate>/` and an Eloquent repository whose `findById` returns a fully mapped entity.
3. `Infrastructure/Persistence/` contains only per-aggregate folders. `BcryptPasswordHasher` is in `Persistence/User/`.
4. The tests described in the testing strategy exist and pass. `make check` is green.
5. Deptrac reports no violations. The domain doesn't import Eloquent or Laravel.

## Resolved decisions

- **R1. `view_type`** has four values: `my_assets`, `my_tickets`, `my_contracts`, `my_locations`.
- **R2. `serial_number`** is globally unique, as the brief says, soft-deleted assets included.
- **R3. `repair_id`** is NOT NULL and unique. It is an external identifier with no table of its own, so it has no foreign key. The same applies to `incident_number`.
- **R4. Relations.** Every foreign key with a constraint is held as an object in the aggregate. A nullable foreign key gives a nullable object (D2).
- **R5. `users.customer_id`** is a nullable foreign key. The views example in the brief filters with `$user->customer_id`.
- **R6. One-level customer hierarchy.** A customer can't be its own parent, and a sub-account's parent is always a top-level customer. The legacy code works this way, and the brief says nothing more.
- **R7. A ticket keeps its soft-deleted asset.** `TicketModel::asset()` includes trashed assets, so the ticket's history keeps pointing at the device, and a future `save()` can't erase the link by accident. Only a hard delete nulls `tickets.asset_id`.
- **R8. Corrupted hierarchies are server errors.** A stored customer whose parent is a sub-account (or itself) raises `CorruptedCustomerHierarchyException` in Infrastructure: a logged 500, not a 422 with internal ids. The domain exceptions stay for entities built in code, and `CustomerFactory::subAccountOf()` rejects a sub-account as parent.
- **R9. Lazy loading is prevented outside production** (`Model::preventLazyLoading()`). Laravel only enforces it on models loaded in a collection of several, so the query-count tests remain the guard for `findById`.
- **R10. Related records belong to the same customer.** Each record belongs to exactly one customer, and what it links to must belong to that same customer, not to its parent or a sub-account:
  - `Asset`: its contract and its location (`AssetLinkedToAnotherCustomerException`);
  - `Ticket`: its asset (`TicketLinkedToAnotherCustomerException`);
  - `CustomView`: its customer is the customer of its owner, so a user without a customer can't own a view (`CustomViewLinkedToAnotherCustomerException`).

  The parent hierarchy is about visibility, not ownership: search widens the list of customers a user may see, and every record still belongs to a single customer. `Ticket::createdBy()` is not checked, because it records who acted, not what the ticket owns. Whether a user may act on a customer is an authorization rule, enforced in the command or policy that creates tickets. A stored row that breaks R10 is reported by its mapper as `CorruptedRecordException` (a logged 500), like R8.
