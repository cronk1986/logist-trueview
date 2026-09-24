# PLAN — Authentication

Spec: `specs/SPEC — Authentication.md` (Status: approved)
Status: approved

## Overview

This plan implements Sanctum token authentication with rotation on every request, a 10-second grace period, expiration, revocation, rate limiting and cleanup, plus the API foundation from section 1.3 of the brief.

The work is sliced by endpoint: login → me → rotation → refresh → logout → cleanup. Each slice goes from the domain to a feature test, so the system works after every task.

Every task ends with the task's tests and `make check`. The mandatory post-implementation flow from `AGENTS.md` runs at each checkpoint: `make qa`, `code-simplify`, `make test`, `code-review-and-quality`.

## Dependency graph

```
T1 test database ─┐
T2 Sanctum + schema + provider ─┐
T3 API foundation (response, errors, AbstractController, CORS, logging)
        │
        ├─ T4 domain for login (VOs, User, AccessToken::issueOnLogin, events, exceptions, interfaces, InMemory)
        │     └─ T5 application for login (LogIn, GetAccessTokenByToken, AccessTokenView)
        │           └─ T6 infrastructure + HTTP for login (repositories, hasher, generator, AuthController::login)
        │                 ├─ T7 me (GetUser, UserView, MeRequest)
        │                 ├─ T8 rate limiter 60/min
        │                 └─ T9 rotation domain + handler
        │                       └─ T10 atomic claimRotation in MySQL
        │                             └─ T11 rotation middleware (X-Refreshed-Token)
        │                                   ├─ T12 refresh (409)
        │                                   └─ T13 logout (current + in-grace predecessor)
        └─ T14 cleanup (scheduler + prune)
T15 OpenAPI docs (after T13)
```

## Architecture decisions

These come from the spec; they are not redecided here:

- The controller generates the plaintext token, the command receives only the hash, and the query `GetAccessTokenByToken` returns `AccessTokenView`.
- Tokens are identified by their hash (`TokenHash`). The predecessor is linked through `rotated_from`, which holds its hash.
- Rotation is claimed with a conditional `UPDATE … WHERE token = ? AND rotated_at IS NULL`. A claim that affects zero rows throws `AccessTokenAlreadyRotatedException`: the middleware swallows it and `refresh` returns 409.
- The HTTP layer follows the conventions in `AGENTS.md`: `AbstractController`, `AuthController` with four actions, one FormRequest per action with `toCommand()` or `toQuery()`, and the response built with `ApiResponse::fromQueryResponse()`.
- Domain code lives in `Model/`, `ValueObject/`, `Exception/` and `Event/`, each split per aggregate (`User`, `AccessToken`).

## Tasks

### Phase 1 — Foundation

#### Task 1: Separate test database

**Description:** Create a `logis_testing` database, so that feature tests with `RefreshDatabase` never touch the development database.

**Acceptance criteria:**
- [x] A MySQL init script creates `logis_testing` and grants it to the `logis` user. A Makefile target (`make test-db`) creates it on existing volumes, because init scripts only run when the volume is created.
- [x] `phpunit.xml` sets `DB_DATABASE=logis_testing`.
- [x] `tests/Feature/ExampleTest.php` is replaced by a smoke test that uses `RefreshDatabase`, and the `logis` database is left untouched.

**Verification:** `make test-db && make test`. Afterwards, the development tables still exist in `logis`.

**Dependencies:** None

**Files:** `docker/mysql/init/01-testing-db.sql`, `compose.dev.yaml`, `Makefile`, `phpunit.xml`, `tests/Feature/...`

**Scope:** S

#### Task 2: Install Sanctum, adapt the schema and create LogisServiceProvider

**Description:** Install Sanctum, adapt the token table and the Eloquent models, and create the context's ServiceProvider.

**Acceptance criteria:**
- [x] `php artisan install:api` has been run:
  - `routes/api.php` has the `v1` prefix;
  - Sanctum's `expiration` is `null`;
  - the `personal_access_tokens` migration has `ip_address`, `user_agent`, `rotated_at` and `rotated_from` (with an index).
- [x] `Persistence\User` uses `HasApiTokens` and is cleaned up to the project rules: `strict_types`, `final`, no comments.
- [x] `Persistence\AccessTokenModel` extends Sanctum's model and is registered with `Sanctum::usePersonalAccessTokenModel`. The morph map is enforced with the alias `user`.
- [x] `LogisServiceProvider` exists, is registered in `bootstrap/providers.php` and loads `config/access_tokens.php`.
- [x] The Spanish comments in `deptrac.yaml` are translated into English.

**Verification:** `make migrate` passes and `make check` is green.

**Dependencies:** T1

**Files:** `composer.json`, `routes/api.php`, `bootstrap/app.php`, `bootstrap/providers.php`, `config/sanctum.php`, `config/access_tokens.php`, the migration, `Persistence/User.php`, `Persistence/AccessTokenModel.php`, `Providers/LogisServiceProvider.php`, `deptrac.yaml`

**Scope:** M. There are many files, but the changes are mechanical.

#### Task 3: API foundation

**Description:** Build the pieces every endpoint relies on: the response format, error rendering, the base controller, CORS and logging.

**Acceptance criteria:**
- [x] `AbstractController` replaces `Controller.php`, with a constructor that receives both buses.
- [x] `ApiResponse::fromQueryResponse()` and `ApiResponse::error()` produce `{ success, data, message, meta{timestamp, version} }`, and `execution_time_ms` is added by the existing middleware.
- [x] `ApiExceptionRenderer` is wired in `withExceptions()` for `api/*`. It maps 401, 404, 405, 409, 422 (errors in `data`), 429 (`Retry-After`) and 500 (no details), and logs only 5xx errors.
- [x] `config/cors.php` is published and exposes `X-Refreshed-Token`. Logging uses `daily` and keeps 14 days.

**Verification:** feature tests check the response format for an unknown route (404) and a wrong method (405). `make check` passes.

**Dependencies:** T2

**Files:** `SharedContext/Infrastructure/Http/{AbstractController,ApiResponse,ApiExceptionRenderer}.php`, `bootstrap/app.php`, `config/cors.php`, `config/logging.php`, `tests/Feature/...`

**Scope:** M

### Checkpoint: Foundation
- [x] `make check` passes.
- [x] `curl localhost:8000/api/v1/nope` returns a 404 in the standard format.
- [x] Human review before starting on the domain.

### Phase 2 — Login and me

#### Task 4: Domain for login

**Description:** Model the users and access tokens needed for login, with in-memory implementations for tests.

**Acceptance criteria:**
- [x] Value objects:
  - `ValueObject/User/{UserId, Email}`;
  - `ValueObject/AccessToken/{PlainTextToken, TokenHash}`;
  - `PlainTextToken::hash()` returns SHA-256;
  - an invalid `Email` throws `Exception/User/InvalidEmailException`.
- [x] Entities and events:
  - `Model/User/{User, UserRepository, PasswordHasher}`;
  - `Model/AccessToken/{AccessToken, AccessTokenRepository}`;
  - `AccessToken::issueOnLogin()` publishes `Event/User/UserLoggedIn` with only the `UserId`.
- [x] Exceptions `InvalidCredentialsException`, `UserNotFoundException` and `AccessTokenNotFoundException`, each with a named constructor.
- [x] InMemory implementations of the repositories and the hasher under `tests/Unit/LogisContext/Domain/Model/...`. Unit tests for the value objects and for `AccessToken`.

**Verification:** `make test` (unit tests) and `make check`. Deptrac confirms that Domain depends only on Carbon.

**Dependencies:** T2

**Files:** about 12 small files in `Domain/` and their tests.

**Scope:** M. The files are small, but there are many.

#### Task 5: Application for login

**Description:** Implement the login command and the query that returns the issued token.

**Acceptance criteria:**
- [x] `LogIn` + `LogInHandler`:
  - builds the `Email`, authenticates and runs a dummy hash verification when the user is unknown;
  - stores the token with an expiry of now + TTL (the TTL is a constructor argument);
  - raises `UserLoggedIn`.
- [x] `GetAccessTokenByToken` + its handler return `QueryResponse(AccessTokenView{token, expires_at})` and throw `AccessTokenNotFoundException` when the token doesn't exist.
- [x] `ModelView/AccessToken/AccessTokenView` exists.
- [x] Unit tests cover the handlers without the bus: a valid login, an unknown email, a wrong password, an invalid email and the event raised; the query found and not found. There is also a view test.

**Verification:** `make check`.

**Dependencies:** T4

**Files:** `Application/Command/LogIn/*`, `Application/Query/GetAccessTokenByToken/*`, `Application/ModelView/AccessToken/AccessTokenView.php`, tests

**Scope:** M

#### Task 6: Login endpoint

**Description:** Connect login end to end, from the HTTP request to MySQL.

**Acceptance criteria:**
- [x] Infrastructure: `EloquentUserRepository`, `EloquentAccessTokenRepository` (`save`, `findByHash`), `BcryptPasswordHasher` and `PlainTextTokenGenerator`, bound in `LogisServiceProvider` together with the TTL.
- [x] HTTP: `LoginRequest::toCommand(PlainTextToken)`, `AuthController::login` and the route `POST /api/v1/auth/login`.
- [x] Feature tests:
  - a valid login returns 200 `{token, expires_at}`, and the stored row has the hash, IP, user agent and `expires_at`;
  - invalid credentials return 401 with the same message for an unknown email and a wrong password;
  - bad input returns 422 with the errors in `data`;
  - the returned token authenticates with `auth:sanctum`.
- [x] The demo user in `DatabaseSeeder`.

**Verification:** `make check`. Manual check: `curl -X POST …/auth/login` with the demo user.

**Dependencies:** T3, T5

**Files:** `Persistence/*Repository.php`, `Persistence/BcryptPasswordHasher.php`, `Http/PlainTextTokenGenerator.php`, `Http/Controller/AuthController.php`, `Http/Request/Auth/LoginRequest.php`, `routes/api.php`, the provider, the seeder, tests

**Scope:** L. It is the first end-to-end slice. Split it into infrastructure and HTTP if it grows.

#### Task 7: me endpoint

**Description:** Return the authenticated user.

**Acceptance criteria:**
- [x] `GetUser` + its handler return `QueryResponse(UserView{id, name, email})` and throw `UserNotFoundException` when the user doesn't exist. `ModelView/User/UserView` exists.
- [x] `MeRequest::toQuery()` takes the user ID from the guard. `AuthController::me` is served at `GET /api/v1/auth/me` behind `auth:sanctum`.
- [x] Feature tests: 200 with the user, 401 without a token, 401 with an expired token.

**Verification:** `make check`.

**Dependencies:** T6

**Scope:** S

#### Task 8: API rate limiter

**Description:** Limit API usage to 60 requests per minute.

**Acceptance criteria:**
- [x] `RateLimiter::for('api')` allows 60 per minute, keyed by user ID or by IP. `->throttleApi()` is enabled.
- [x] A feature test checks that the 61st request returns 429 in the standard format with `Retry-After`.

**Verification:** `make check`.

**Dependencies:** T6

**Scope:** XS

### Checkpoint: Login and me
- [x] Post-implementation flow: `make qa`, `code-simplify`, `make test`, `code-review-and-quality`, `make check`.
- [x] Manual check: login, then `me` with the returned token.
- [x] Human review.

### Phase 3 — Rotation (the riskiest part)

#### Task 9: Rotation in the domain and application

**Description:** Model token rotation with its grace period.

**Acceptance criteria:**
- [x] `AccessToken::rotate(newHash, ip, userAgent, now, ttl, grace)`:
  - marks the current token with `rotatedAt` and sets `expiresAt = min(expiresAt, now + grace)`;
  - returns the successor with `rotatedFrom` set;
  - publishes `Event/AccessToken/AccessTokenRotated`;
  - throws `Exception/AccessToken/AccessTokenAlreadyRotatedException` when the token is already rotated.
- [x] `RotateAccessToken` + its handler take the TTL and the grace period as constructor arguments and throw `AccessTokenNotFoundException` for an unknown token.
- [x] Unit tests:
  - a rotation;
  - a second rotation throws;
  - the grace period never extends `expires_at`;
  - the successor has the TTL;
  - the event is raised.

**Verification:** `make check`.

**Dependencies:** T4, T5

**Scope:** M

#### Task 10: Atomic rotation claim in MySQL

**Description:** Make sure only one request can rotate a given token.

**Acceptance criteria:**
- [x] `AccessTokenRepository::claimRotation(AccessToken)` exists, with the conditional `UPDATE … WHERE token = ? AND rotated_at IS NULL`. It throws `AccessTokenAlreadyRotatedException` when it affects zero rows.
- [x] The InMemory implementation reproduces the same semantics.
- [x] An integration test on MySQL checks that, of two claims on the same token, only the first succeeds.

**Verification:** `make check`.

**Dependencies:** T9

**Scope:** S

#### Task 11: Rotation middleware

**Description:** Rotate the token on every authenticated request and send the new one to the client.

**Acceptance criteria:**
- [x] `RotateAccessTokenMiddleware` runs after the controller:
  - it generates a token and dispatches `RotateAccessToken`;
  - it asks `GetAccessTokenByToken` and sets `X-Refreshed-Token`;
  - on `AccessTokenAlreadyRotatedException` it serves the response without the header.
- [x] It applies to every authenticated route except `logout` and `refresh`.
- [x] Feature tests, with frozen time:
  - `me` returns the header, and the new token works;
  - the old token works for 10 seconds and returns 401 after that;
  - a second request with the old token inside the grace period gets no header and creates no new row.

**Verification:** `make check`. Manual check: two parallel `curl` requests with the same token both return 200, and only one of them carries the header.

**Dependencies:** T7, T10

**Scope:** M

#### Task 12: refresh endpoint

**Description:** Let the client rotate its token explicitly.

**Acceptance criteria:**
- [x] `RefreshTokenRequest::toCommand(PlainTextToken)` and `AuthController::refresh` at `POST /api/v1/auth/refresh`, which returns `AccessTokenView` in the body.
- [x] An already rotated token returns 409 in the standard format.
- [x] Feature tests cover the new token in the body, the old token in its grace period and the 409.

**Verification:** `make check`.

**Dependencies:** T11

**Scope:** S

### Checkpoint: Rotation
- [x] Post-implementation flow, then `make check`.
- [x] Manual check of parallel requests.
- [x] Human review.

### Phase 4 — Revocation, cleanup and documentation

#### Task 13: logout endpoint

**Description:** Revoke the current token and, if it exists, its predecessor still in its grace period.

**Acceptance criteria:**
- [x] `LogOut` + its handler revoke the current token and its `rotatedFrom` predecessor while it is still in grace. `AccessToken::revoke()` publishes `AccessTokenRevoked`.
- [x] `LogoutRequest::toCommand()` and `AuthController::logout` at `POST /api/v1/auth/logout`, returning 200 with `data {}`.
- [x] Unit tests, including that unrelated tokens are left untouched. Feature test: after a rotation, a logout makes both the current token and its predecessor return 401.

**Verification:** `make check`.

**Dependencies:** T11

**Scope:** M

#### Task 14: Expired token cleanup

**Description:** Remove expired tokens automatically.

**Acceptance criteria:**
- [x] `routes/console.php` schedules `sanctum:prune-expired --hours={prune_hours}` hourly.
- [x] A `scheduler` service in `compose.dev.yaml` runs `schedule:work`.
- [x] A feature test checks that the command deletes tokens expired more than `prune_hours` ago and keeps the rest.

**Verification:** `make check`, and `make artisan c="schedule:list"` shows the task.

**Dependencies:** T2

**Scope:** S

#### Task 15: OpenAPI documentation

**Description:** Document the auth endpoints for API clients.

**Acceptance criteria:**
- [x] `docs/openapi` covers the four auth endpoints, the standard response format (success and error), the `X-Refreshed-Token` header and the client contract for rotation.
- [x] `make docs` validates and generates `openapi.json`.

**Verification:** `make docs`, then open Swagger UI and run a login from it.

**Dependencies:** T13

**Scope:** S

### Checkpoint: Complete
- [x] Every success criterion in the spec is met.
- [x] Post-implementation flow, then `make check`.
- [ ] Human review before committing.

## Risks and mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| In feature tests, the auth guard caches the user between requests in the same test, so a revoked or expired token looks valid | High: false positives in the rotation and logout tests | Call `$this->app['auth']->forgetGuards()` between requests in the same test, through a helper in the base `TestCase` |
| The MySQL init script doesn't run on the existing volume | Medium | The `make test-db` target creates the database in the running container |
| The conditional claim relies on InnoDB row locking | High if it fails | The integration test in T10 and the manual check with parallel `curl` in T11 |
| Timestamps outside UTC break `LEAST()` and the grace period | Medium | Check that `APP_TIMEZONE=UTC` and the MySQL connection use UTC. Tests use `setTestNow` |
| `DomainEventPublisher` is static and survives between requests in the same test | Low | `reset()` in `setUp()` |
| T6 is large (L) | Medium | Split it into T6a (infrastructure) and T6b (HTTP) if it goes over 5 files with logic |

## Parallelisation

- T14 (cleanup) only depends on T2 and can be done at any time.
- T7 and T8 are independent of each other once T6 is done.
- T12 and T13 are independent of each other once T11 is done.
- Everything else is sequential.

## Open questions

None.
