# SPEC — Authentication

Status: approved

## Objective

Provide token-based API authentication for every other endpoint of the Logis API (search, views), as required by section 1.2 of `logis-test/LARAVEL_TECHNICAL_TEST.md`. The spec also covers the API foundation from section 1.3 that these endpoints need: `v1` routing, the standard response format, global error handling, request validation, logging and CORS.

Users are API clients (a SPA or Postman) that log in with email and password, receive a bearer token and send it on every request.

### Requirements from the brief

- Laravel Sanctum for API token authentication.
- Token rotation on every authenticated request.
- Token expiration, configurable, default 60 minutes.
- Token revocation.
- Rate limiting, 60 requests per minute.
- Endpoints: `POST /api/v1/auth/login`, `POST /api/v1/auth/logout`, `POST /api/v1/auth/refresh`, `GET /api/v1/auth/me`.
- Bcrypt password hashing.
- Tokens stored in the database with metadata: IP, user agent, last used.
- Automatic cleanup of expired tokens.
- Middleware for token validation and rotation.
- Section 1.3: API versioning, global error handling, standard response format, request validation, logging configuration, CORS configuration.

## Key design decisions

### D1. Sliding expiration

Every token gets `expires_at = now + ttl` when issued. Because each authenticated request issues a new token, the TTL behaves as an inactivity timeout: a client that stays idle for longer than the TTL must log in again. Sanctum's global `expiration` setting is `null`; only the per-token `expires_at` is used.

### D2. Rotation with a grace period

Rotating on every request revokes the token that concurrent in-flight requests are still carrying. Without mitigation, a SPA that fires two requests in parallel gets a 401 on the second one.

The rotation middleware runs after the controller, on every authenticated route except `logout` and `refresh`:

1. Generate a new plaintext token and dispatch `RotateAccessToken`.
2. The handler atomically claims the rotation of the current token with a conditional update:
   ```sql
   UPDATE personal_access_tokens
      SET rotated_at = :now, expires_at = LEAST(expires_at, :now + grace)
    WHERE token = :currentHash AND rotated_at IS NULL
   ```
3. If the update affected one row, the handler stores the successor with `rotated_from = :currentHash`, `expires_at = now + ttl` and the request's IP and user agent. The middleware then asks `GetAccessTokenByToken` and returns the token in the `X-Refreshed-Token` header.
4. If the update affected zero rows, a concurrent request already rotated this token. `claimRotation()` returns `false`, the handler throws `AccessTokenAlreadyRotatedException` and the transaction rolls back. The middleware catches it and serves the response without the header.
5. If a concurrent `logout` deleted the token during the request, the handler throws `AccessTokenNotFoundException`. The middleware catches it too and serves the response unchanged.

This gives the following guarantees:

- **Concurrent requests** carrying the old token keep working for the grace period: 10 seconds, sized for requests fired in parallel, not for slow retries.
- **One successor per token.** InnoDB serialises the conditional update on the row lock, so two simultaneous requests can never fork the token into two valid chains.
- **Bounded exposure.** A leaked old token is valid for at most `grace_seconds` after rotation.

Client contract: whenever a response carries `X-Refreshed-Token`, replace the stored token. When the header is absent, keep the current token.

Known limitation: if the client loses the response that carried the successor, the old token stops working when the grace period ends, and the client must log in again. The successor can't be re-sent, because only its SHA-256 hash is stored. This is documented, not solved.

### D3. Token issuance: the controller generates, the command stores the hash, the query returns the view

Commands return nothing. Login still has to hand the plaintext token back, so the flow has three steps:

1. The controller generates the plaintext token with `PlainTextTokenGenerator` (Infrastructure). The format is Sanctum's: 40 random characters plus a CRC32 checksum, with no `id|` part.
2. The command receives only the SHA-256 hash (`TokenHash`) and stores it.
3. The query `GetAccessTokenByToken(plainText)` hashes the token, finds the row and returns `AccessTokenView { token, expires_at }` in its `QueryResponse`. The controller only wraps that `QueryResponse` in the response format.

Consequences:

- **Tokens are identified by their hash.** Commands and domain entities use `TokenHash`, which is unique, and never the auto-increment ID. Sanctum's guard resolves tokens without `|` by hash too.
- **The plaintext is never persisted, logged or put in an event.** It only exists in the request's memory: in the controller and in the query that builds the response.

### D4. Revocation

- Revoking a token deletes its row, which is Sanctum's semantics.
- `logout` revokes the current token, every successor issued from it by concurrent requests (followed transitively through `rotated_from`), and its predecessor if the predecessor is still in its grace period. Logging out right after or during a rotation therefore never leaves a usable token behind.
- `refresh` is an explicit rotation. It uses the same command and grace period as D2 and returns the view in the body. When the token was already rotated by a concurrent request, it returns 409, because the successor was already issued.

### D5. Rate limiting

- Neither Laravel 11 nor Sanctum defines an API limiter by default.
- The project defines the `api` limiter with `RateLimiter::for('api')`: 60 requests per minute, keyed by user ID when authenticated and by IP otherwise.
- `->throttleApi()` in `bootstrap/app.php` applies it to every `v1` route, `login` included.
- There is no extra login limiter: it isn't in the brief and Sanctum doesn't ship one.
- Laravel's middleware priority runs `auth:sanctum` before the throttle. On protected routes, an unauthenticated request gets a 401 without spending quota, and authenticated requests are limited per user. Guest routes such as `login` are limited per IP.
- The throttle runs before the controller and the rotation middleware, so a 429 never rotates a token.

### D6. Validation

- **HTTP input** is validated with `FormRequest` classes, Laravel's equivalent of the Symfony Validator. There is one per action, and each builds its message with `toCommand()` or `toQuery()`. `LoginRequest` requires `email` (`required|email`) and `password` (`required|string`). A failure throws `ValidationException` before the controller runs.
- **Domain invariants** are enforced by value objects. `Email::fromString()` throws `InvalidEmailException`. Commands carry primitives, and handlers build the value objects.
- **Error formatting** happens in the exception handler, not in a middleware. Laravel's router turns an exception into a response before the outer middlewares see it. `withExceptions()` in `bootstrap/app.php` delegates to `ApiExceptionRenderer`, which is the equivalent of a Symfony `kernel.exception` listener.

### D7. Login security

- The password is checked with Laravel's `Hash`, bcrypt driver (`BCRYPT_ROUNDS=12`, `4` in tests).
- Unknown email and wrong password both return the same 401 `Invalid credentials` response.
- When the email is unknown, a verification against a dummy hash still runs, so response timing doesn't reveal which emails exist. The dummy hash uses cost 12, the value of `BCRYPT_ROUNDS`. If `BCRYPT_ROUNDS` changes, the constant in `BcryptPasswordHasher` must be regenerated with the same cost.
- `InvalidEmailException` returns a generic message and never echoes the input.

### D8. Cleanup

- The scheduler runs `sanctum:prune-expired --hours=24` every hour. The retention can be changed with `ACCESS_TOKEN_PRUNE_HOURS`.
- A new `scheduler` service in `compose.dev.yaml` runs `php artisan schedule:work`.

## Configuration

`config/access_tokens.php`:

| Key | Env | Default |
|---|---|---|
| `ttl_minutes` | `ACCESS_TOKEN_TTL_MINUTES` | 60 |
| `grace_seconds` | `ACCESS_TOKEN_GRACE_SECONDS` | 10 |
| `prune_hours` | `ACCESS_TOKEN_PRUNE_HOURS` | 24 |

- **CORS:** `config/cors.php` is published, with `paths: ['api/*']`, the allowed origin from `CORS_ALLOWED_ORIGINS` (Swagger UI in local) and `exposed_headers: ['X-Refreshed-Token']`.
- **Logging:** `LOG_STACK=daily`, keeping 14 days. `ApiExceptionRenderer` logs only 5xx errors, with the exception. Tokens and passwords are never logged.

## API contract

Every response, success or error, has the same format required by the brief. `data` carries the `QueryResponse` data on success and the error details on failure. The HTTP status tells the client what happened. The HTTP middleware appends `meta.execution_time_ms`.

```json
{
  "success": true,
  "data": { "token": "Xk29...a81f", "expires_at": "2026-09-25T11:00:00Z" },
  "message": "Logged in",
  "meta": { "timestamp": "2026-09-25T10:00:00Z", "version": "1.0", "execution_time_ms": 85 }
}
```

```json
{
  "success": false,
  "data": { "email": ["The email field must be a valid email address."] },
  "message": "Validation failed",
  "meta": { "timestamp": "2026-09-25T10:00:00Z", "version": "1.0", "execution_time_ms": 4 }
}
```

In production, 500 responses never expose exception details.

| Endpoint | Auth | Request | Success `data` | Errors |
|---|---|---|---|---|
| `POST /api/v1/auth/login` | guest | `{ email, password }` | 200 `AccessTokenView { token, expires_at }` | 401 invalid credentials, 422 validation, 429 |
| `POST /api/v1/auth/logout` | token | none | 200 `{}` | 401 |
| `POST /api/v1/auth/refresh` | token | none | 200 `AccessTokenView { token, expires_at }` | 401, 409 already rotated |
| `GET /api/v1/auth/me` | token, rotated | none | 200 `UserView { id, name, email }` and the `X-Refreshed-Token` header | 401 |

`customer_id` will be added to `UserView` by the spec that introduces customers.

`ApiExceptionRenderer` maps exceptions to statuses:

| Status | Exceptions | `data` |
|---|---|---|
| 401 | `AuthenticationException`, `InvalidCredentialsException` | `{}` |
| 404 | route or model not found, `*NotFoundException` | `{}` |
| 405 | method not allowed | `{}` |
| 409 | `AccessTokenAlreadyRotatedException` | `{}` |
| 422 | `ValidationException`, `InvalidEmailException` | `{ field: [messages] }` |
| 429 | `ThrottleRequestsException`, plus the `Retry-After` header | `{}` |
| 500 | anything else | `{}` |

## Architecture

The layout follows the conventions in `AGENTS.md`: Domain code grouped by kind first and by aggregate second, one controller per aggregate, and one FormRequest per action.

```
app/SharedContext/Infrastructure/Http/
    AbstractController.php                 replaces Controller.php; constructor with CommandBusInterface + QueryBusInterface
    ApiResponse.php                        fromQueryResponse(), success() for commands with no query, error()
    ApiExceptionRenderer.php               exception → response format + status
app/LogisContext/
    Domain/
        Model/User/                        User, UserRepository, PasswordHasher
        Model/AccessToken/                 AccessToken, AccessTokenRepository
        ValueObject/User/                  UserId, Email
        ValueObject/AccessToken/           PlainTextToken, TokenHash
        Exception/User/                    InvalidEmailException, InvalidCredentialsException, UserNotFoundException
        Exception/AccessToken/             AccessTokenNotFoundException, AccessTokenAlreadyRotatedException
        Event/User/                        UserLoggedIn
        Event/AccessToken/                 AccessTokenRotated, AccessTokenRevoked
    Application/
        Command/LogIn/                     LogIn, LogInHandler
        Command/LogOut/                    LogOut, LogOutHandler
        Command/RotateAccessToken/         RotateAccessToken, RotateAccessTokenHandler
        Query/GetAccessTokenByToken/       GetAccessTokenByToken, GetAccessTokenByTokenHandler
        Query/GetUser/                     GetUser, GetUserHandler
        ModelView/User/                    UserView
        ModelView/AccessToken/             AccessTokenView
    Infrastructure/
        Http/Controller/                   AuthController (login, logout, refresh, me)
        Http/Request/Auth/                 LoginRequest, LogoutRequest, RefreshTokenRequest, MeRequest
        Http/Middleware/                   RotateAccessTokenMiddleware
        Http/PlainTextTokenGenerator.php
        Http/CurrentAccessToken.php        reads the current AccessTokenModel from the guard
        Persistence/                       UserModel (Eloquent + HasApiTokens), AccessTokenModel (extends Sanctum's model),
                                           EloquentUserRepository, EloquentAccessTokenRepository, BcryptPasswordHasher
config/access_tokens.php, config/cors.php, config/logging.php, routes/api.php (prefix v1), routes/console.php (schedule)
```

### Actions, requests and handlers

| Action | FormRequest builds | Command (write, transactional) | Query (read) |
|---|---|---|---|
| `AuthController::login` | `LoginRequest::toCommand(PlainTextToken)` | `LogIn(email, password, tokenHash, ipAddress, userAgent)` | `GetAccessTokenByToken(newPlainText)` → `AccessTokenView` |
| `AuthController::refresh` | `RefreshTokenRequest::toCommand(PlainTextToken)` | `RotateAccessToken(currentTokenHash, newTokenHash, ipAddress, userAgent)` | `GetAccessTokenByToken(newPlainText)` → `AccessTokenView` |
| `AuthController::logout` | `LogoutRequest::toCommand()` | `LogOut(currentTokenHash)` | none |
| `AuthController::me` | `MeRequest::toQuery()` | none | `GetUser(userId)` → `UserView` |
| `RotateAccessTokenMiddleware` | builds the command itself | `RotateAccessToken(...)`, the same command as `refresh` | `GetAccessTokenByToken(newPlainText)` → header |

- The requests take the current token hash and the user ID from the guard (`$this->user()`), and the IP and user agent from the request itself.
- `GetAccessTokenByToken` is built in the controller because its only input is the token the controller generated.
- That makes 3 command handlers and 2 query handlers.

### Domain User and Sanctum

There are two separate models of the user, one per layer:

- **Domain** `User` (`UserId`, name, `Email`, password hash) is a plain PHP entity that knows nothing about Sanctum or Eloquent. It is read through `UserRepository`.
- `AccessToken` is a separate domain entity, identified by its `TokenHash`. It references `UserId` and holds `expiresAt`, `ipAddress`, `userAgent`, `rotatedAt` and `rotatedFrom`.
- **Infrastructure** `Persistence\UserModel` is the Eloquent model with Sanctum's `HasApiTokens` trait, which Sanctum's guard needs. `auth:sanctum`:
  1. reads the Bearer header;
  2. finds the `personal_access_tokens` row by hash and checks `expires_at`;
  3. loads the `tokenable` user;
  4. exposes it as `$request->user()` and `$request->user()->currentAccessToken()`.
- The FormRequests and the middleware take only primitives from the guard, the user ID and the current token hash, and put them into commands and queries. Domain and Application never see Sanctum types.
- `EloquentAccessTokenRepository` writes the rows that Sanctum reads. `tokenable_type` uses the morph alias `user` (`Relation::enforceMorphMap`), so the database doesn't depend on the Eloquent class name.

### Other rules

- `PlainTextToken::hash()` returns the `TokenHash` (SHA-256), so the command and the query always hash the same way.
- The TTL and the grace period reach the handlers as constructor arguments, bound in the context's ServiceProvider. No handler reads `config()`.
- Domain events carry only the `UserId` and `occurredOn`, never token data:
  - `AccessToken::issueOnLogin()` raises `UserLoggedIn`;
  - `AccessToken::rotate()` returns the successor and raises `AccessTokenRotated`;
  - `AccessToken::revoke()` raises `AccessTokenRevoked`.
  They have no listeners yet. With no listeners, Laravel's dispatcher does nothing and the event is discarded in memory.
- The Eloquent model is renamed `UserModel`, like `AccessTokenModel`, so it doesn't clash with the domain `User`. It is cleaned up to the project rules: `declare(strict_types=1)`, `final`, no comments.

### Schema

The `personal_access_tokens` migration published by `install:api` is edited in place, since nothing is deployed yet:

- `ip_address varchar(45) nullable`
- `user_agent varchar(512) nullable`
- `rotated_at timestamp nullable`
- `rotated_from varchar(64) nullable`, with an index: the hash of the predecessor. It is not a foreign key, because pruning deletes rows in any order.
- The existing `token` (unique), `expires_at` and `last_used_at` columns are kept. Sanctum updates `last_used_at` itself.

A `DatabaseSeeder` demo user, `demo@logis.test` / `password`, is available for manual testing.

## Code style: the login flow

```
POST /auth/login ─▶ AuthController::login(LoginRequest)
   1. LoginRequest validates                           (422 via ApiExceptionRenderer)
   2. PlainTextTokenGenerator::generate()              → PlainTextToken
   3. CommandBus → $request->toCommand($plainTextToken) → LogIn
        transaction: Email VO → find user → verify password → AccessToken::issueOnLogin (UserLoggedIn) → save
   4. QueryBus → GetAccessTokenByToken(plainText)      → QueryResponse(AccessTokenView)
   5. ApiResponse::fromQueryResponse($response, 'Logged in')
```

```php
abstract class AbstractController
{
    public function __construct(
        protected readonly CommandBusInterface $commandBus,
        protected readonly QueryBusInterface $queryBus,
    ) {
    }
}
```

```php
final class AuthController extends AbstractController
{
    public function __construct(
        CommandBusInterface $commandBus,
        QueryBusInterface $queryBus,
        private readonly PlainTextTokenGenerator $tokenGenerator,
    ) {
        parent::__construct($commandBus, $queryBus);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $plainTextToken = $this->tokenGenerator->generate();

        $this->commandBus->dispatch($request->toCommand($plainTextToken));

        $response = $this->queryBus->ask(new GetAccessTokenByToken($plainTextToken->value()));

        return ApiResponse::fromQueryResponse($response, 'Logged in');
    }

    public function me(MeRequest $request): JsonResponse
    {
        $response = $this->queryBus->ask($request->toQuery());

        return ApiResponse::fromQueryResponse($response, 'Authenticated user');
    }
}
```

```php
final class LoginRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function toCommand(PlainTextToken $plainTextToken): LogIn
    {
        return new LogIn(
            email: $this->string('email')->toString(),
            password: $this->string('password')->toString(),
            tokenHash: $plainTextToken->hash()->value(),
            ipAddress: (string) $this->ip(),
            userAgent: (string) $this->userAgent(),
        );
    }
}
```

```php
final class LogInHandler
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $passwordHasher,
        private readonly AccessTokenRepository $accessTokens,
        private readonly int $ttlMinutes,
    ) {
    }

    public function handle(LogIn $command): void
    {
        $user = $this->authenticate(Email::fromString($command->email), $command->password);

        $accessToken = AccessToken::issueOnLogin(
            hash: TokenHash::fromString($command->tokenHash),
            userId: $user->id(),
            ipAddress: $command->ipAddress,
            userAgent: $command->userAgent,
            ttlMinutes: $this->ttlMinutes,
        );

        $this->accessTokens->save($accessToken);
    }

    private function authenticate(Email $email, string $password): User
    {
        $user = $this->users->findByEmail($email);

        if ($user === null) {
            $this->passwordHasher->verifyAgainstDummyHash($password);

            throw InvalidCredentialsException::create();
        }

        if (! $this->passwordHasher->verify($password, $user->passwordHash())) {
            throw InvalidCredentialsException::create();
        }

        return $user;
    }
}
```

```php
final class GetAccessTokenByTokenHandler
{
    public function __construct(private readonly AccessTokenRepository $accessTokens)
    {
    }

    public function handle(GetAccessTokenByToken $query): QueryResponse
    {
        $plainTextToken = PlainTextToken::fromString($query->token);
        $accessToken = $this->accessTokens->findByHash($plainTextToken->hash());

        if ($accessToken === null) {
            throw AccessTokenNotFoundException::create();
        }

        return QueryResponse::from(AccessTokenView::from($plainTextToken, $accessToken)->toArray());
    }
}
```

```php
final readonly class AccessTokenView
{
    private function __construct(public string $token, public string $expiresAt)
    {
    }

    public static function from(PlainTextToken $plainTextToken, AccessToken $accessToken): self
    {
        return new self($plainTextToken->value(), $accessToken->expiresAt()->toIso8601ZuluString());
    }

    /** @return array{token: string, expires_at: string} */
    public function toArray(): array
    {
        return ['token' => $this->token, 'expires_at' => $this->expiresAt];
    }
}
```

## Dependencies and environment changes

- `composer require laravel/sanctum` through `php artisan install:api`, which also creates `routes/api.php` and the migration.
- A new `scheduler` service in `compose.dev.yaml`.
- A separate test database `logis_testing`:
  - a MySQL init script creates it and grants access to the `logis` user;
  - `phpunit.xml` sets `DB_DATABASE=logis_testing`;
  - feature tests use `RefreshDatabase`.

## Commands

```
make up / make down         # Docker environment
make migrate                # php artisan migrate inside the container
make qa                     # pint --test, phpstan, deptrac
make test                   # phpunit
make check                  # qa + test (required before commit)
```

## Testing strategy

**Unit tests** in `tests/Unit/LogisContext/...`, with no database and no mocks of our own interfaces:

- `Domain/ValueObject/...`: `EmailTest`, `PlainTextTokenTest`, `TokenHashTest`.
- `Domain/Model/...`: `AccessTokenTest`, covering issue, rotate and revoke, the rule that the grace period never extends `expires_at`, and the events raised.
- `Application/ModelView/...`: `AccessTokenViewTest`, `UserViewTest`.
- `LogInHandlerTest`:
  - a valid login stores the token with the hash, IP, user agent and expiry, and raises `UserLoggedIn`;
  - an unknown email or a wrong password throws `InvalidCredentialsException`;
  - an invalid email throws `InvalidEmailException`.
- `RotateAccessTokenHandlerTest`:
  - rotation stores the successor, puts the predecessor into its grace period and raises `AccessTokenRotated`;
  - an already rotated token throws `AccessTokenAlreadyRotatedException`;
  - an unknown token throws `AccessTokenNotFoundException`.
- `LogOutHandlerTest`: revokes the current token and an in-grace predecessor, leaves unrelated tokens untouched and raises `AccessTokenRevoked`.
- `GetAccessTokenByTokenHandlerTest` and `GetUserHandlerTest`: return the view in a `QueryResponse`, or throw the not-found exception.
- InMemory implementations under `tests/Unit/LogisContext/Domain/Model/...`: `InMemoryUserRepository`, `InMemoryAccessTokenRepository`, `InMemoryPasswordHasher`.

**Feature tests** in `tests/Feature/LogisContext/Auth/`, against MySQL `logis_testing`, with time frozen through `CarbonImmutable::setTestNow`:

- The login happy path, invalid credentials and validation. The 422 response carries the field errors in `data`.
- `me` returns the user and `X-Refreshed-Token`, and the new token works.
- The old token keeps working inside the grace period and returns 401 after it.
- A second request with the old token inside the grace period doesn't rotate again and has no header.
- An expired token returns 401 after the TTL.
- `logout` makes both the current token and its in-grace predecessor return 401.
- `refresh` returns a new token in the body, and returns 409 when the token was already rotated.
- The 61st request in a minute returns 429 in the response format.
- `sanctum:prune-expired` removes tokens that expired more than `prune_hours` ago.
- The response format for 404 and 405.

**Integration test** for `EloquentAccessTokenRepository`: of two claims on the same token, only the first succeeds.

**OpenAPI:** the auth paths, the response format and the `X-Refreshed-Token` header are documented in `docs/openapi`.

## Boundaries

- **Always:**
  - run `make check` before a commit;
  - use UTC `CarbonImmutable` and semantic exceptions;
  - put `if` guards before compact expressions.
- **Ask first:** new composer packages beyond Sanctum, changes to D2 semantics, adding customer or role data to users.
- **Never:**
  - store, log or put in an event a plaintext token;
  - log passwords;
  - return distinct errors for an unknown email and a wrong password;
  - use `Event::fake()` in the tests.

## Out of scope

- Registration.
- Password reset.
- Email verification.
- Abilities or scopes.
- A token list, per-device revocation, or revoking every token of a user.
- Reuse detection, meaning revoking the chain when a token is used after its grace period.
- A shorter retention for rotated rows. Every authenticated request inserts a row, and rotated rows are kept for `grace + prune_hours`. If the table grows too much, prune rotated rows earlier.
- Password rehash on login.
- A configurable minimum age before rotating: the brief asks for rotation on every request.

## Success criteria

- All four endpoints behave as described in the API contract table, including the response format and the status codes.
- Two parallel requests with the same token both succeed, and exactly one of them returns `X-Refreshed-Token`.
- The old token returns 401 once `grace_seconds` has passed after rotation, and any token returns 401 once `ttl_minutes` have passed without use.
- Tokens are stored hashed, with IP, user agent and `last_used_at`.
- Expired tokens are pruned by the scheduler.
- `make check` passes.

## Resolved decisions

- The API foundation from section 1.3 is part of this spec.
- `logout` revokes the current token and its in-grace predecessor.
- The grace period is 10 seconds.
- There is no extra login throttle. The global 60 per minute applies.
- The domain events are kept with no listeners.
- The controller generates the token and the command receives the hash. The response comes from a query that returns a view in `Application/ModelView`.
- Errors use the same response format, with the details in `data`.
- Everything lives in `LogisContext`, except `AbstractController`, `ApiResponse` and `ApiExceptionRenderer`, which live in `SharedContext/Infrastructure/Http`.
- Layout conventions from `AGENTS.md`: Domain `Model/`, `ValueObject/`, `Exception/` and `Event/` per aggregate, `Application/ModelView/<Aggregate>/`, one controller per aggregate, and one FormRequest per action with `toCommand()` or `toQuery()`.

## Open questions

None.
