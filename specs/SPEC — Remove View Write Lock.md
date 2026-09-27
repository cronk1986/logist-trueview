# SPEC — Remove View Write Lock

Status: done

## Objective

Remove the per-owner write lock that `SPEC — Custom Asset Views.md` added to the view commands (D5, "Concurrent writes are serialised per owner").

Every command that changes views locks the owner's row in `users` (`SELECT … FOR UPDATE`) before loading the view. The lock prevents two races between writes of the same owner:

- two concurrent `set-default` requests leaving two default views of one type;
- a `PUT` and a `set-default` on the same view, where the later `save()` overwrites the other's change.

Both races need the same user to send two writes to their own views at the same instant. The product assumption is that **one person works in one account at a time**, and only the owner can change, delete or set a view as default. The races are therefore very unlikely, and the lock is over-engineering: it adds a repository method, an application service method, a query per command and tests that only check the lock.

Success: the four view commands no longer lock anything, and their behaviour and responses are unchanged.

## Decision

- Assumption, recorded in D5 of the views spec: a user doesn't write to their own views from several sessions at the same time. Concurrent writes of the same owner are not serialised.
- Title uniqueness doesn't depend on the lock. The handler checks `findByOwnerAndTitle()`, and the `(user_id, title)` unique index turns a race into a 409 (`CustomViewTitleAlreadyUsedException`).
- **Accepted worst case:** two `set-default` requests of the same owner and type at the same instant can leave two defaults. The next `set-default` fixes it, because it unmarks the default it finds before marking the new one. A `PUT` racing a `set-default` can lose one of the two changes. The owner can repeat it.

## Changes

| Area | Change |
|---|---|
| `Domain/Model/CustomView/CustomViewRepository` | Remove `lockViewsOf(UserId $owner)` |
| `Infrastructure/Persistence/CustomView/EloquentCustomViewRepository` | Remove `lockViewsOf()` and the `UserModel` import it needs |
| `Application/Services/CustomView/CustomViewAccess` | Remove `viewToChange()` |
| `Application/Command/CustomView/CreateCustomViewHandler` | Remove the `lockViewsOf()` call |
| `Update`, `Delete`, `SetDefault` handlers | Use `viewManagedBy()` instead of `viewToChange()` |
| `tests/Unit/…/InMemoryCustomViewRepository` | Remove `lockViewsOf()` and `lockedOwners()` |
| Handler unit tests (Create, Update, Delete, SetDefault) | Remove the `lockedOwners()` assertions |
| `EloquentCustomViewRepositoryTest` | Remove the test that the lock ends in `for update` |
| `SPEC — Custom Asset Views.md` | Replace the "Concurrent writes are serialised per owner" point of D5 with the assumption above |
| `PLAN — Custom Asset Views.md` | Record the removal in "Deviations during implementation", and drop the lock risk |

No endpoint, response, status code or OpenAPI document changes.

## Commands

```
make qa      # Pint (read-only), Larastan, Deptrac
make test    # PHPUnit against logis_testing
make check   # qa + docs-lint + test, required before the commit
```

## Code style

The handlers go back to the shape they had before the lock:

```php
public function handle(DeleteCustomView $command): void
{
    $owner = $this->access->user(userId: $command->userId);
    $view = $this->access->viewManagedBy(user: $owner, viewId: $command->viewId);

    $view->delete();
    $this->views->delete(id: $view->savedId());
}
```

The rules of `AGENTS.md` apply.

## Testing strategy

- No new tests. The existing handler, repository and feature tests keep covering creation, update, deletion, set-default, the 403 and 404 paths and the 409 of a duplicate title.
- `grep -rn "lockViewsOf\|viewToChange\|lockedOwners" app tests` returns nothing.

## Boundaries

- **Always:** one `refactor(views)` commit for the code and tests, plus one `docs(specs)` commit for this spec and the updates to the views spec and plan; `make check` before each commit; work on the branch `feat/custom-views`.
- **Ask first:** any other form of concurrency control, such as a schema constraint for one default per owner and type.
- **Never:** change endpoints, responses or the OpenAPI document; remove the `(user_id, title)` unique index or its 409 mapping.

## Success criteria

1. No code or test refers to `lockViewsOf`, `viewToChange` or `lockedOwners`.
2. The view commands run no `SELECT … FOR UPDATE`.
3. D5 of the views spec states the assumption instead of the lock.
4. `make check` is green.

## Open questions

None.
