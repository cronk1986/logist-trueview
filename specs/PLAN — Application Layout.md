# PLAN — Application Layout

Spec: `specs/SPEC — Application Layout.md` (Status: done)
Status: approved

## Overview

A pure refactor. The Application layer is grouped by aggregate, and `Query/` keeps only queries and their handlers. Nothing changes in behaviour, endpoints or test assertions.

The work is sliced by area, not by kind of file. Each task moves one area completely: classes, tests, imports and the specs that name their paths. So after every task the application runs, `make check` is green with the same 363 tests, and the task becomes one commit.

The order is auth first, then search, then `AGENTS.md`. Auth is the simplest move (no renames) and proves the approach. Search adds the renames and the new `Services/` and `Exception/` folders. `AGENTS.md` goes last, so that the convention is committed together with code that already follows it.

## Dependency graph

```
T0 commit the spec and this plan
 ├─ T1 auth: Command/AccessToken, Query/User, Query/AccessToken
 └─ T2 search: Query/Search (renames), Services/Search, Exception/Search, ModelView/Search
      └─ T3 AGENTS.md (already edited in the working tree) ← T1, T2
```

T1 and T2 touch different files, apart from `LogisServiceProvider`, where each changes its own imports.

## Architecture decisions

These come from the spec and are not decided again here:

- Folders under `Command/` and `Query/` are named after the aggregate, and classes after the use case (R1). A command belongs to the aggregate it changes, and a query to the aggregate it returns (R2, R6).
- `GlobalSearch` → `SearchService` and `GlobalSearchHandler` → `SearchServiceHandler`. Every search folder in Application is named `Search` (R3).
- Collaborators go to `Services/<Aggregate>/`, exceptions to `Exception/<Aggregate>/` and result DTOs to `ModelView/<Aggregate>/` (R4). `EntitySearcherNotRegisteredException` keeps extending `LogicException` (R5).

### How each file is moved

1. `git mv` the file, so its history follows it.
2. Update its `namespace` line.
3. Replace the old fully qualified name everywhere with a scripted search and replace over `app/`, `tests/`, `routes/` and `database/`. Nothing is edited by hand.
4. `make pint` to re-sort the `use` statements the moves put out of order.
5. Larastan level 8 then fails on any reference that was missed (an unknown class), before the tests even run.

The buses need no change. `HandlerResolver` builds the handler name as `$message::class . 'Handler'`, so a message and its handler only need to share a namespace.

## Tasks

### Phase 0 — Documents

#### Task 0: Commit the spec and the plan

**Acceptance criteria:**
- [x] `docs(specs): specify the application layout by aggregate` commits `specs/SPEC — Application Layout.md` and this plan.
- [x] The `AGENTS.md` change stays uncommitted until T3.

**Verification:** `git show --stat HEAD` lists only the two spec files.

**Dependencies:** None · **Scope:** XS

### Phase 1 — Auth

#### Task 1: Group the auth commands and queries by aggregate

**Description:** Move the three commands to `Command/AccessToken/`, `GetUser` to `Query/User/` and `GetAccessTokenByToken` to `Query/AccessToken/`, with their tests. No class is renamed.

**Acceptance criteria:**
- [x] `app/LogisContext/Application/`:
  - `Command/AccessToken/` holds `LogIn`, `LogOut` and `RotateAccessToken` with their handlers;
  - `Query/User/` holds `GetUser` and `GetUserHandler`;
  - `Query/AccessToken/` holds `GetAccessTokenByToken` and its handler;
  - the folders `Command/LogIn/`, `Command/LogOut/`, `Command/RotateAccessToken/`, `Query/GetUser/` and `Query/GetAccessTokenByToken/` no longer exist.
- [x] The five handler tests mirror the new paths under `tests/Unit/LogisContext/Application/`.
- [x] Every importer is updated: `AuthController`, `RotateAccessTokenMiddleware`, the four auth FormRequests, and `LogisServiceProvider` (its `when()` for `LogInHandler` and `RotateAccessTokenHandler`).
- [x] `SPEC — Authentication.md` and `PLAN — Authentication.md` point to the new paths.

**Verification:**
- `make check` is green with 363 tests.
- `LoginTest`, `LogoutTest`, `RefreshTest`, `MeTest` and `TokenRotationTest` prove that both buses still resolve every moved handler.
- `grep -rn "Command\\\\LogIn\\\\\|Command\\\\LogOut\\\\\|Command\\\\RotateAccessToken\\\\\|Query\\\\GetUser\\\\\|Query\\\\GetAccessTokenByToken\\\\" app tests` finds nothing.

**Dependencies:** T0

**Files:** 10 moved classes, 5 moved tests, 7 importers, 2 specs. The files are many but the change is mechanical: namespaces and imports only.

**Scope:** M (mechanical) · **Commit:** `refactor(auth): group auth commands and queries by aggregate`

### Checkpoint: Auth
- [x] `make check` is green with 363 tests.
- [x] A manual login and `GET /auth/me` in Swagger still work.

### Phase 2 — Search

#### Task 2: Regroup the search application layer

**Description:** Rename the search query and its handler, move them to `Query/Search/`, and move their collaborators out of `Query/` to `Services/Search/`, `Exception/Search/` and `ModelView/Search/`, with their tests.

**Acceptance criteria:**
- [x] `app/LogisContext/Application/`:
  - `Query/Search/` holds only `SearchService` (formerly `GlobalSearch`) and `SearchServiceHandler` (formerly `GlobalSearchHandler`);
  - `Services/Search/` holds `EntitySearcher`, `EntitySearchers` and `SearchCriteria`;
  - `Exception/Search/` holds `EntitySearcherNotRegisteredException`;
  - `ModelView/Search/` holds `EntitySearchResult` next to `EntitySearchResultView`;
  - `Query/GlobalSearch/` no longer exists.
- [x] Tests mirror the new paths:
  - `Query/Search/SearchServiceHandlerTest`;
  - `Services/Search/{EntitySearchersTest, InMemoryEntitySearcher, SearchCriteriaMother}`;
  - `ModelView/Search/EntitySearchResultTest`.
- [x] Every importer is updated:
  - `SearchRequest`, whose `toQuery()` now returns `SearchService`;
  - `LogisServiceProvider`, `CachedEntitySearcher`, `NewestMatches` and the four `Eloquent<X>Searcher`s;
  - `EntitySearchResultView` and the tests that use these classes.
- [x] `SPEC — Global Search.md` and `PLAN — Global Search.md` use the new names and paths.

**Verification:**
- `make check` is green with 363 tests.
- `SearchEndpointTest` proves the query bus resolves `SearchServiceHandler` for the renamed `SearchService`.
- `LogisServiceProviderTest` proves the searcher registry is still wired.
- Deptrac reports no new uncovered dependency.
- `grep -rn "GlobalSearch" app tests specs` finds nothing.

**Dependencies:** T0 (independent of T1)

**Files:** 7 moved classes (2 renamed), 5 moved tests, about 12 importers, 2 specs. The change is mechanical.

**Scope:** M (mechanical) · **Commit:** `refactor(search): regroup the search application layer`

### Checkpoint: Search
- [x] `make check` is green with 363 tests.
- [x] A manual `GET /api/v1/search?q=zebra` with `demo@logis.test` returns the same data as before.

### Phase 3 — Convention

#### Task 3: Commit the convention in AGENTS.md

**Description:** The `AGENTS.md` change is already written in the working tree. This task checks that it describes the code as it now stands, and commits it.

**Acceptance criteria:**
- [x] The architecture tree shows `Command/<Aggregate>/`, `Query/<Aggregate>/`, `Services/<Aggregate>/`, `Exception/<Aggregate>/` and `ModelView/<Aggregate>/`.
- [x] The rules are there:
  - a command belongs to the aggregate it changes, and a query to the one it returns;
  - `Command/` and `Query/` hold only messages and handlers;
  - the `Search` exception applies to Application too;
  - the `LogicException` rule for programming errors.
- [x] The test example is `Command/User/CreateUserHandler.php` → `Command/User/CreateUserHandlerTest.php`.
- [x] Every example path in `AGENTS.md` exists in the code or is clearly hypothetical, like `CreateUser`.

**Verification:** `make check`. Compare `find app/LogisContext/Application -type d` with the tree.

**Dependencies:** T1, T2 · **Scope:** XS · **Commit:** `docs(agents): group the application layer by aggregate`

### Checkpoint: Complete
- [x] Every success criterion of the spec is met.
- [x] `make check` is green with 363 tests, the same count as before T1.
- [x] Post-implementation flow from `AGENTS.md`:
  - `make qa`;
  - `code-simplify` (a pure move: expected to find nothing);
  - `make test`;
  - `code-review-and-quality` on the diff.
- [ ] Human review. No merge until the user says so.

## Risks and mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| A reference to an old namespace is missed | Medium | Larastan level 8 fails on unknown classes, and a `grep` for the old names runs after each task. |
| A class string is built dynamically, so static analysis can't see it | Low | The only dynamic lookup is `HandlerResolver` (`$message::class . 'Handler'`), which keeps working because message and handler move together. The feature tests go through the real buses. |
| The spec updates miss a path in the older specs | Low | `grep` for the old folder names over `specs/` after each task. |
| `use` statements end up out of order after the scripted replace | Low | `make pint` sorts them. `make check` runs Pint read-only before each commit. |
| Git doesn't see the renamed files as renames | Low | `git mv` plus unchanged contents apart from the namespace. `git log --follow` confirms the history. |

## Parallelisation

T1 and T2 touch different files, but both edit `LogisServiceProvider`. They run one after the other on the same branch, to avoid a conflict in that file.

## Open questions

None.
