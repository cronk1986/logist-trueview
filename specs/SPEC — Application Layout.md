# SPEC — Application Layout

Status: approved

## Objective

Group the Application layer by aggregate, as Domain and Infrastructure already are, and move out of `Query/` the classes that are not a query or its handler. The change is a pure refactor: no behaviour, endpoint, response or test assertion changes.

Today `Application/Command/` and `Application/Query/` have one folder per use case (`Command/LogIn/`, `Query/GetUser/`), and `Query/GlobalSearch/` also holds the search port, its registry, its criteria, its result and an exception. Grouping by use case scatters one aggregate's use cases across many folders, and nothing says where collaborators of a use case belong.

### The convention

- `Application/Command/<Aggregate>/` and `Application/Query/<Aggregate>/` hold **only** messages and their handlers: `<Message>.php` and `<Message>Handler.php`. The folder is named after the aggregate, and the classes after the use case: `Query/User/GetUser.php`, `Command/AccessToken/LogIn.php`.
- **Which aggregate:**
  - a command belongs to the aggregate it changes;
  - a query belongs to the aggregate it returns.
- Other Application classes live in their own folders, also grouped by aggregate:

  | Folder | Holds |
  |---|---|
  | `Application/Services/<Aggregate>/` | application services, ports and the value objects they exchange |
  | `Application/Exception/<Aggregate>/` | Application exceptions |
  | `Application/ModelView/<Aggregate>/` | views and result DTOs (unchanged) |

- `Search` is not an aggregate. It is the documented exception already used in Domain (`Domain/ValueObject/Search/`, `Domain/Exception/Search/`), and it now also applies to `Query/Search/`, `Services/Search/`, `Exception/Search/` and `ModelView/Search/`.
- The query bus and the command bus keep working unchanged: `HandlerResolver` finds `<Message>Handler` in the namespace of the message, whatever the folder is called.

## Moves

### Commands

Every current command changes an `AccessToken`:
- `LogIn` saves a new token;
- `LogOut` revokes tokens;
- `RotateAccessToken` saves a successor.

`LogIn` reads the `User`, but it only changes `AccessToken`.

| Today | After |
|---|---|
| `Command/LogIn/LogIn.php`, `LogInHandler.php` | `Command/AccessToken/LogIn.php`, `LogInHandler.php` |
| `Command/LogOut/LogOut.php`, `LogOutHandler.php` | `Command/AccessToken/LogOut.php`, `LogOutHandler.php` |
| `Command/RotateAccessToken/RotateAccessToken.php`, `RotateAccessTokenHandler.php` | `Command/AccessToken/RotateAccessToken.php`, `RotateAccessTokenHandler.php` |

### Queries

| Today | After |
|---|---|
| `Query/GetUser/GetUser.php`, `GetUserHandler.php` | `Query/User/GetUser.php`, `GetUserHandler.php` |
| `Query/GetAccessTokenByToken/GetAccessTokenByToken.php`, `…Handler.php` | `Query/AccessToken/GetAccessTokenByToken.php`, `…Handler.php` |
| `Query/GlobalSearch/GlobalSearch.php` | `Query/Search/SearchService.php` (renamed) |
| `Query/GlobalSearch/GlobalSearchHandler.php` | `Query/Search/SearchServiceHandler.php` (renamed) |

The search query is renamed to `SearchService`, so that `SearchServiceHandler` carries the "search service" name of the brief. `SearchService` is the only class with that name, and it is the query.

### Search collaborators

| Today (`Query/GlobalSearch/`) | After |
|---|---|
| `EntitySearcher.php` | `Services/Search/EntitySearcher.php` |
| `EntitySearchers.php` | `Services/Search/EntitySearchers.php` |
| `SearchCriteria.php` | `Services/Search/SearchCriteria.php` |
| `EntitySearcherNotRegisteredException.php` | `Exception/Search/EntitySearcherNotRegisteredException.php` |
| `EntitySearchResult.php` | `ModelView/Search/EntitySearchResult.php`, next to `EntitySearchResultView` |

`EntitySearcherNotRegisteredException` keeps extending `LogicException`. It reports a programming error (an entity without a searcher), which must be a logged 500, not a 4xx. `AGENTS.md` records that an Application exception for a programming or configuration error may extend `LogicException` instead of a `SharedContext/Domain/Exception/` base class.

### Tests

Tests keep mirroring the path of the class under test (`AGENTS.md`):

| Today (`tests/Unit/LogisContext/Application/`) | After |
|---|---|
| `Command/LogIn/LogInHandlerTest.php` | `Command/AccessToken/LogInHandlerTest.php` |
| `Command/LogOut/LogOutHandlerTest.php` | `Command/AccessToken/LogOutHandlerTest.php` |
| `Command/RotateAccessToken/RotateAccessTokenHandlerTest.php` | `Command/AccessToken/RotateAccessTokenHandlerTest.php` |
| `Query/GetUser/GetUserHandlerTest.php` | `Query/User/GetUserHandlerTest.php` |
| `Query/GetAccessTokenByToken/GetAccessTokenByTokenHandlerTest.php` | `Query/AccessToken/GetAccessTokenByTokenHandlerTest.php` |
| `Query/GlobalSearch/GlobalSearchHandlerTest.php` | `Query/Search/SearchServiceHandlerTest.php` |
| `Query/GlobalSearch/EntitySearchersTest.php` | `Services/Search/EntitySearchersTest.php` |
| `Query/GlobalSearch/InMemoryEntitySearcher.php` | `Services/Search/InMemoryEntitySearcher.php` (same path as its interface) |
| `Query/GlobalSearch/SearchCriteriaMother.php` | `Services/Search/SearchCriteriaMother.php` |
| `Query/GlobalSearch/EntitySearchResultTest.php` | `ModelView/Search/EntitySearchResultTest.php` |

### Files that only change imports

- **Infrastructure:**
  - `AuthController`, `RotateAccessTokenMiddleware`;
  - `LoginRequest`, `LogoutRequest`, `MeRequest`, `RefreshTokenRequest`, `SearchRequest` (its `toQuery()` returns `SearchService`);
  - `LogisServiceProvider` (the `when()` for `LogInHandler` and `RotateAccessTokenHandler`, and `EntitySearchers`);
  - `CachedEntitySearcher`, `NewestMatches`, the four `Eloquent<X>Searcher`s.
- **Application:** `EntitySearchResultView`.
- **Tests:** the four searcher tests, `LogisServiceProviderTest`, `CachedEntitySearcherTest`, `EntitySearchResultViewTest`.

## Documentation

- **`AGENTS.md`:**
  - the architecture tree shows `Command/<Aggregate>/`, `Query/<Aggregate>/`, `Services/<Aggregate>/`, `Exception/<Aggregate>/` and `ModelView/<Aggregate>/`, with the convention above;
  - the `Search` exception covers the Application folders too;
  - the test example becomes `Application/Command/User/CreateUserHandler.php` → `tests/Unit/LogisContext/Application/Command/User/CreateUserHandlerTest.php`;
  - the `LogicException` rule for programming errors in Application.
- **Specs and plans:** the file paths and class names in `SPEC — Authentication.md`, `PLAN — Authentication.md`, `SPEC — Global Search.md` and `PLAN — Global Search.md` are updated, as the core spec did when it regrouped `Persistence/` (D9 of `SPEC — Core Domain Model.md`).
- **OpenAPI:** no change. No endpoint, parameter or response changes.

## Commands

```
make qa        # Pint (read-only), Larastan, Deptrac
make test      # PHPUnit against logis_testing
make check     # qa + docs-lint + test, required before every commit
```

## Code style

Only namespaces and imports change. A moved query looks like this:

```php
<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Query\User;

final readonly class GetUser
{
    public function __construct(public int $userId)
    {
    }
}
```

The files are moved with `git mv`, so their history follows them.

## Testing strategy

- The existing 363 tests are the safety net. They pass with **unchanged assertions**: only their namespace, their location and their imports change.
- **The buses still resolve every handler after the move:**
  - `LoginTest`, `LogoutTest`, `RefreshTest` and `MeTest` dispatch through the command and query buses;
  - `SearchEndpointTest` asks the renamed `SearchService`.
- `grep -rn "Application\\\\Query\\\\GlobalSearch\|Application\\\\Command\\\\Log\|Query\\\\Get" app tests` finds no reference left to the old namespaces.
- Deptrac reports no new violation and no new uncovered dependency. The new folders are inside `app/LogisContext/Application/`, which is already the Application layer.

## Boundaries

- **Always:**
  - `git mv` for every move;
  - one commit per logical change;
  - `make check` before each commit;
  - tests keep mirroring the class paths.
- **Ask first:**
  - renaming any class other than `GlobalSearch` and `GlobalSearchHandler`;
  - moving anything outside `Application/` and `tests/Unit/LogisContext/Application/`.
- **Never:**
  - change behaviour, a test assertion or an endpoint;
  - touch `logis-test/`.

## Commits

On the current branch `feat/global-search`, because the search classes exist only there:

1. `refactor(auth): group auth commands and queries by aggregate` — `Command/AccessToken/`, `Query/User/`, `Query/AccessToken/`, their tests and the authentication spec and plan.
2. `refactor(search): regroup the search application layer` — `Query/Search/` with the rename, `Services/Search/`, `Exception/Search/`, `ModelView/Search/`, their tests and the search spec and plan.
3. `docs(agents): group the application layer by aggregate` — the convention in `AGENTS.md`.

## Success criteria

1. `Application/Command/` and `Application/Query/` contain only `<Aggregate>/` folders (plus `Search/`), and each holds only messages and their handlers.
2. The layout of the tables above exists, and no class is left in `Query/GlobalSearch/`, `Query/GetUser/`, `Query/GetAccessTokenByToken/`, `Command/LogIn/`, `Command/LogOut/` or `Command/RotateAccessToken/`.
3. The tests mirror the new paths, and `make check` is green with the same number of tests.
4. `AGENTS.md` describes the convention, and the four affected specs and plans point to the new paths.

## Resolved decisions

- **R1.** Folders under `Command/` and `Query/` are named after the aggregate, and classes after the use case.
- **R2.** A command belongs to the aggregate it changes, and a query to the aggregate it returns. So `LogIn` and `LogOut` go to `Command/AccessToken/`, with `RotateAccessToken`.
- **R3.** The search query is `SearchService`, its handler `SearchServiceHandler`, and every search folder in Application is named `Search`.
- **R4.** Collaborators go to `Services/<Aggregate>/`, exceptions to `Exception/<Aggregate>/`, and result DTOs to `ModelView/<Aggregate>/`.
- **R5.** An Application exception for a programming error may extend `LogicException`, so that it is a logged 500.
- **R6.** `LogIn` and `LogOut` live in `Command/AccessToken/`, confirmed on review: both only change tokens, and `LogIn` only reads the user.

## Open questions

None.
