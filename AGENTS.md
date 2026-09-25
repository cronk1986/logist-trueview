# AGENTS.md

Laravel 11 API (PHP 8.3) built with hexagonal architecture, DDD, CQRS and domain events. Namespace `Logis\` maps to `app/`. The development environment runs in Docker: run every command through `make` and never on the host.

## Architecture

```
app/
├── SharedContext/                    # building blocks reused by every context
│   ├── Domain/                       # Event/, QueryResponse/
│   └── Infrastructure/               # Bus/, BusMiddleware/, Event/, Http/ (AbstractController, ApiResponse), Providers/
└── LogisContext/                     # bounded context
    ├── Domain/
    │   ├── Model/<Aggregate>/        # entities, repository interfaces, domain service interfaces
    │   ├── ValueObject/<Aggregate>/  # UserId, Email, ...
    │   ├── Exception/<Aggregate>/    # UserNotFoundException, ...
    │   └── Event/<Aggregate>/        # UserCreated, ...
    ├── Application/
    │   ├── Command/<UseCase>/        # command + handler
    │   ├── Query/<UseCase>/          # query + handler
    │   └── ModelView/<Aggregate>/    # UserView, SmallUserView, ...
    └── Infrastructure/
        ├── Http/Controller/          # <Aggregate>Controller
        ├── Http/Request/<Aggregate>/ # one FormRequest per action
        ├── Http/Middleware/
        ├── Persistence/<Aggregate>/  # <Aggregate>Model, <Aggregate>Mapper, Eloquent<Aggregate>Repository, other adapters
        ├── Listeners/
        └── AsyncListeners/
```

- Domain code is grouped by kind first and by aggregate second. For example, `Domain/ValueObject/User/Email.php` and `Domain/Exception/User/UserNotFoundException.php`, never `Domain/Model/User/Email.php`.
- Each bounded context has exactly one ServiceProvider, registered in `bootstrap/providers.php`.
- The domain is framework-agnostic. Eloquent models live in Infrastructure, and repositories map them to domain entities and back.
- **Persistence is grouped by aggregate**, mirroring `Domain/Model/<Aggregate>/`: `Persistence/User/{UserModel, UserMapper, EloquentUserRepository, BcryptPasswordHasher}`, `Persistence/AccessToken/{AccessTokenModel, AccessTokenMapper, EloquentAccessTokenRepository}`. An adapter lives in the folder of the aggregate whose domain interface it implements. `BcryptPasswordHasher` implements `Domain/Model/User/PasswordHasher`, so it goes in `Persistence/User/`. Never put a class directly in `Persistence/`.
- **Every Eloquent model has a domain model.** Each `<Aggregate>Model` maps to the entity `Domain/Model/<Aggregate>/<Aggregate>`. `Persistence/<Aggregate>/<Aggregate>Mapper::toDomain()` is the only place that builds the entity from the model, and the repositories use it. No Eloquent model exists without its domain counterpart, and Eloquent models never leave Infrastructure.
- **Every foreign key with a constraint is held as an object.** If the database enforces referential integrity on a relation, the entity holds the related entity, never a bare id: `Asset::customer(): Customer`. A nullable foreign key gives a nullable object: `Asset::contract(): ?Contract`.
  - Related objects are read-only snapshots. An aggregate never changes or saves another one: each aggregate changes only through its own repository.
  - The repository eager loads the whole graph with `with()`, never lazily, so the number of queries is fixed.
  - Columns that hold external identifiers with no table and no constraint (`repair_id`, `erp_id`) are value objects, not relations.
- Dates are always `CarbonImmutable::now()->utc()`.

## Layer dependencies

`deptrac.yaml` enforces these rules (`make deptrac`):

| Layer | May depend on |
|---|---|
| SharedDomain | DomainVendor |
| Domain | SharedDomain, DomainVendor |
| Application | Domain, SharedDomain, DomainVendor |
| SharedInfrastructure | SharedDomain, DomainVendor, Vendor |
| Infrastructure | Application, Domain, SharedDomain, SharedInfrastructure, DomainVendor, Vendor |

- `DomainVendor` is the whitelist of third-party libraries allowed in Domain and Application. At the moment it contains only Carbon.
- Adding a library to `DomainVendor` requires an explicit decision. Do not add one to make a violation go away.

## Buses and handlers

| Bus | Method | Message | Handler returns | Middleware |
|---|---|---|---|---|
| `CommandBusInterface` | `dispatch(object $command): void` | `Application/Command/**` | `void` | `TransactionMiddleware`, `DomainEventMiddleware` |
| `QueryBusInterface` | `ask(object $query): QueryResponse` | `Application/Query/**` | `QueryResponse` | none (no transaction) |

- **Resolution by naming convention:** the handler of `Foo` is `FooHandler`, in the same namespace. Handlers are neither registered nor scanned. `HandlerNotFoundException` is thrown when no handler exists.
- **Handler signature:** a `final` class with `public function handle(Foo $message)`: exactly one parameter, typed with the concrete message class. The custom Larastan rule `tools/PHPStan/HandlerSignatureRule.php` enforces this.
- Messages are plain immutable objects (`final readonly`) with no marker interface.
- Queries never modify state. Commands never return data.
- `QueryResponse::from($data, Meta::...)` builds query results. For `meta`, use `Meta::pagination()`, `Meta::cursor()` or `Meta::from()`. The HTTP middleware adds `meta.execution_time_ms`.

## HTTP layer

- **Controllers** extend `SharedContext\Infrastructure\Http\AbstractController`, whose only job is a constructor that receives `CommandBusInterface` and `QueryBusInterface`. A controller that needs another dependency adds it to its own constructor and calls `parent::__construct()`.
- A controller groups the actions of one aggregate or resource (`AuthController::login()`, `logout()`, `refresh()`, `me()`), not one class per action.
- **Every action has its own FormRequest** in `Http/Request/<Aggregate>/`, named after the action (`LoginRequest`, `RefreshTokenRequest`). The FormRequest validates the input and builds the message:
  - `toCommand(...)` returns the command, for write actions;
  - `toQuery(...)` returns the query, for read actions;
  - the arguments are values that don't come from the request input, such as a token the controller generated, or a route ID;
  - `query()` isn't used because `Illuminate\Http\Request` already defines it.
- Controllers contain no logic and build no response data:
  - they dispatch the command, ask the query and return `ApiResponse::fromQueryResponse($response, $message)`;
  - a write action with no data to return responds with `ApiResponse::success($message)`;
  - a controller builds a message directly only from values it produced itself.
- Eloquent models are suffixed `Model` (`UserModel`, `AccessTokenModel`), so they never clash with domain entities.
- `Http/CurrentAccessToken` reads the authenticated Sanctum token from the request, for FormRequests and middlewares.
- Response data comes from views in `Application/ModelView/<Aggregate>/`: `final readonly` DTOs with a named constructor and `toArray()`, returned by query handlers inside a `QueryResponse`.
- Every response, success or error, uses the standard format `{ success, data, message, meta }`. Errors carry their details in `data`, and the HTTP status tells the client what happened. `ApiExceptionRenderer` maps exceptions to statuses; validation errors return 422.
- **Swagger is part of every endpoint change.** Whenever an endpoint is created, modified or deleted, update `docs/openapi/openapi.yaml` in the same change:
  - the path, method, parameters and request body;
  - every response status with its schema;
  - security and headers such as `X-Refreshed-Token`.

  Then run `make docs`, which validates the spec and regenerates `docs/openapi.json`, served by Swagger UI at http://localhost:8080. An endpoint change is not complete until Swagger reflects it.

## Domain events

- Every event implements `DomainEvent`, usually by extending `AbstractDomainEvent`, whose `occurredOn()` returns the time in UTC.
- Aggregates or domain services raise events with `DomainEventPublisher::publish($event)`. Repositories never dispatch events.
- `DomainEventMiddleware` publishes the collected events after the handler finishes, still inside the command transaction. On failure it clears them and rethrows.
- **Sync listeners** live in `Infrastructure/Listeners/`: a plain class with `handle(ConcreteEvent $event)`. They run inside the transaction, so a failure rolls back the whole command.
- **Async listeners** live in `Infrastructure/AsyncListeners/`: they extend `AsyncEventListener` and go to the `events` queue. The job is inserted inside the same transaction (`QUEUE_CONNECTION=database`): all or nothing.
- Laravel discovers listeners automatically in both directories (`bootstrap/app.php`).

## Tests

- Unit tests live in `tests/Unit/<Context>/{Domain,Application,Infrastructure}/` and mirror the path of the class under test in `app/<Context>/`. Example: `app/LogisContext/Application/Command/CreateUser/CreateUserHandler.php` is tested by `tests/Unit/LogisContext/Application/Command/CreateUser/CreateUserHandlerTest.php`.
- Every handler has a unit test that instantiates the handler directly, without the bus.
- **No mocks of our own interfaces.** Use an InMemory implementation placed at the same path as the interface, but under `tests/Unit/`. Example: `app/LogisContext/Domain/Model/User/UserRepository.php` is implemented by `tests/Unit/LogisContext/Domain/Model/User/InMemoryUserRepository.php`.
- Value objects, entities and views have their own unit tests, for example `tests/Unit/LogisContext/Domain/ValueObject/User/EmailTest.php`.
- Assert domain events with `DomainEventPublisher::lastEventOfType(Foo::class)`, never with `Event::fake()`. Call `DomainEventPublisher::reset()` in `setUp()`.
- Endpoints are covered by feature tests in `tests/Feature/` against the MySQL database `logis_testing` (`make test` creates it), with `RefreshDatabase`.
- The base `TestCase` forgets the resolved auth guards before every request, so a revoked or expired token is not served from the guard cache of a previous request in the same test.
- Freeze and move time with `freezeTime()` and `travel()`.

## Code rules

- All code, identifiers, commit messages and documentation are written in English.
- No comments. Code must explain itself through naming and small methods. The only exception is PHPDoc types that Larastan needs, such as generics or array shapes.
- **Named arguments everywhere.** Every call to a function, method or constructor passes its arguments by name: `new LogIn(email: $email, password: $password)`, `UserId::fromInt(value: $id)`, `hash(algo: 'sha256', data: $token)`. This applies to our code, to Laravel and to native PHP functions. Arguments stay positional only where names are impossible or unsafe:
  - **variadic calls:** a call that passes values into a variadic parameter is fully positional, e.g. `sprintf('User %d not found.', $id)`, because PHP forbids positional arguments after named ones;
  - **argument unpacking:** calls that use `...$arguments`;
  - **`@no-named-arguments` methods**, such as PHPUnit assertions (`$this->assertSame($expected, $actual)`);
  - **magic methods** resolved through `__call`/`__callStatic` with no real method behind them, such as `Route::middleware('api')` or Carbon's `addMinutes(60)`. Laravel reads their arguments by position, so names break them at runtime;
  - **dynamic callables** such as `$next($request)`.

  The Larastan rule `tools/PHPStan/NamedArgumentsRule.php` enforces this in every file under the `Logis\`, `Tests\`, `Tools\` and `Database\` namespaces. It flags positional arguments where a name is allowed, and named arguments on magic methods. `config/` and `routes/` are declarative framework files and are not checked.
- Readability comes before brevity. Prefer an explicit `if` guard over compact constructs such as `?? throw`, `?: throw`, nested ternaries or long chained expressions. Keep one intention per line.
- Negation has no space after `!`: `if (!$user->isActive())`, never `if (! $user->isActive())`. Pint enforces it with `unary_operator_spaces` and `not_operator_with_successor_space: false` in `pint.json`.
- Every file starts with `declare(strict_types=1)`. Classes are `final` by default.
- No framework names in class names, even in Infrastructure: `DomainEventDispatcher`, not `LaravelDomainEventDispatcher`.
- Exceptions are semantic and specific to the problem. For example, `UserNotFoundException::withId($id)` or `InvalidViewColumnException::for($column)`. Never throw `Exception`, `RuntimeException`, `InvalidArgumentException` or similar generic exceptions directly. Build them with named constructors. The Larastan rule `tools/PHPStan/SemanticExceptionRule.php` forbids `throw new` of built-in PHP exceptions in Domain and Application.
- Domain and Application exceptions extend one of the abstract classes in `SharedContext/Domain/Exception/`. That base class decides the HTTP status in `ApiExceptionRenderer`, and the exception message is returned to the client, so it must be safe to show. These exceptions are not logged.

  | Base class | Status |
  |---|---|
  | `UnauthorizedException` | 401 |
  | `NotFoundException` | 404 |
  | `ConflictException` | 409 |
  | `InvalidValueException` | 422 |

  Any other exception returns a 500 with no details and is logged.

## Commits

Every commit follows [Conventional Commits](https://www.conventionalcommits.org):

```
<type>(<scope>)!: <description>

<body>

<footers>
```

- **Subject:** at most 72 characters, including the type and scope. English, imperative mood ("add", not "added" or "adds"), lowercase after the colon, no final period. It must complete the sentence *"If applied, this commit will…"*.
- **Body** (optional for trivial changes): separated from the subject by a blank line, wrapped at 72 characters. It explains what changed and why, not how.
- **Footers:** git trailers such as `Refs: specs/SPEC — Authentication.md`, `Closes: #12` or `Co-Authored-By: …`. A breaking change adds `!` after the type or scope and a `BREAKING CHANGE: <explanation>` footer.
- One logical change per commit. Don't mix a refactor with a feature or a fix.

| Type | Use |
|---|---|
| `feat` | New functionality |
| `fix` | Bug fix |
| `refactor` | Code change that neither fixes a bug nor adds functionality |
| `perf` | Performance improvement |
| `test` | Adding or fixing tests only |
| `docs` | Documentation only: OpenAPI, specs, `AGENTS.md`, README |
| `style` | Formatting only, no logic change |
| `build` | Dependencies, composer, Docker images |
| `ci` | Continuous integration configuration |
| `chore` | Maintenance that touches neither `app/` nor tests |
| `revert` | Reverts a previous commit |

| Scope | Area |
|---|---|
| `auth` | Authentication, access tokens, rate limiting |
| `search` | Global search endpoint |
| `views` | Custom asset views |
| `shared` | `SharedContext`: buses, domain events, response format, exception rendering |
| `docker` | `compose.dev.yaml`, `docker/`, containers |
| `docs` | OpenAPI and Swagger UI |
| `specs` | `specs/` (SPEC and PLAN documents) |
| `agents` | `AGENTS.md` and `CLAUDE.md` |
| `core` | Core domain model: customers, contracts, locations, assets, tickets, their migrations, factories and seeders |
| `qa` | Quality tooling: Pint, Larastan and its custom rules, Deptrac, Makefile targets |

The scope may be omitted when a change spans several areas. Examples:

```
feat(auth): rotate access tokens on every authenticated request
fix(auth): revoke rotated successors on logout
docs(docs): document the refresh endpoint in the OpenAPI spec
build(docker): add a scheduler service for token pruning
chore(qa): enforce named arguments with a Larastan rule
```

## Specs and plans

- Every new spec is created with `agent-skills:spec` and saved in `specs/` as `SPEC — <spec name>.md`.
- Once a human approves the spec (`Status: approved`), the implementation plan is generated with `agent-skills:plan` and saved in `specs/` as `PLAN — <spec name>.md`.
- Do not start implementing a non-trivial feature without an approved spec.

## Quality gate

`make check` runs everything, in this order:

| Step | Tool | Target |
|---|---|---|
| Lint / code style (read-only) | Pint, PSR-12 plus project rules (`pint.json`) | `make pint` applies the fixes |
| Static analysis | Larastan level 8 over `app`, `config`, `database`, `routes`, `tests` and `tools`, with the custom rules in `tools/PHPStan/` (`phpstan.neon`) | `make stan` |
| Layer dependencies | Deptrac (`deptrac.yaml`) | `make deptrac` |
| API docs | Redocly lint of `docs/openapi/openapi.yaml` | `make docs-lint` (`make docs` also regenerates `docs/openapi.json`) |
| Tests | PHPUnit | `make test` |

`make qa` runs the first three. `make check` runs `qa`, then `docs-lint`, then `test`. A feature or fix is complete only when `make check` passes. Never commit unless `make check` passes.

## Mandatory post-implementation flow

After implementing any feature or fix, run these steps in order. If a step fails, fix it and repeat that step before moving on.

1. **Quality checks** (always through Docker): `make qa`.
2. **Simplify:** run `agent-skills:code-simplify` on the new code and apply the improvements that make sense.
3. **Tests:** `make test`. If they fail, fix the code and start again from step 1.
4. **Review:** run `agent-skills:code-review-and-quality`. Resolve the relevant findings, then run `make check` again before closing the task or committing.
5. **API docs:** if any endpoint was created, modified or deleted, `docs/openapi/openapi.yaml` is updated and `make docs` passes.
