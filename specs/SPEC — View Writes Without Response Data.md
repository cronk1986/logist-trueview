# SPEC — View Writes Without Response Data

Status: done

## Objective

`SPEC — Custom Asset Views.md` makes the three write endpoints that keep a view return its details:

- `POST /views` answers 201 with the new view;
- `PUT /views/{id}` answers 200 with the updated view;
- `POST /views/{id}/set-default` answers 200 with the view, now the default.

The controller asks a query after each command to build that response (`GetCustomViewByTitle` after create, `GetCustomView` after update and set-default).

The product decision is that **save endpoints return no data**, like `DELETE /views/{id}` already does. The client reads a view through `GET /views/{id}` or `GET /views` when it needs it. `ViewController` already answers the three actions with `ApiResponse::success()`. This spec aligns the tests, the documentation and the specs with that change, and removes the code left without use.

Success: the three endpoints answer `{ success: true, data: {}, message, meta }` with the same status codes and errors as before, `make check` is green, and no code remains that only served those responses.

## Decision

- Status codes don't change: 201 on create, 200 on update and set-default. Error responses (401, 403, 404, 409, 422, 429) don't change either.
- The messages stay: `View created`, `View updated`, `View set as default`.
- The controller runs only the command. No query follows it, so every write saves one query.
- **Accepted trade-off:** the client doesn't receive the id of a new view. It finds the view by listing (`GET /views`); the title is unique per owner.

## Changes

| Area | Change |
|---|---|
| `Infrastructure/Http/Controller/ViewController` | Already changed: `store`, `update` and `setDefault` dispatch the command and return `ApiResponse::success()` |
| `Http/Request/CustomView/CreateCustomViewRequest` | Remove `toQuery()` and the `GetCustomViewByTitle` import |
| `Http/Request/CustomView/UpdateCustomViewRequest`, `SetDefaultCustomViewRequest` | Remove `toQuery()` and the `GetCustomView` import |
| `Application/Query/CustomView/GetCustomViewByTitle` and its handler | Delete: no other caller |
| `tests/Unit/…/Query/CustomView/GetCustomViewByTitleHandlerTest` | Delete |
| `Domain/Exception/CustomView/CustomViewNotFoundException::withTitle()` | Delete: only the removed handler used it |
| `CreateCustomViewEndpointTest`, `UpdateCustomViewEndpointTest`, `SetDefaultCustomViewEndpointTest` | Assert an empty `data`, and check what was saved in the database instead of in the response |
| `docs/openapi/openapi.yaml` | The 201 of create and the 200 of update and set-default use `EmptyResponse`, with an example like the one of `DELETE`. `make docs` regenerates `docs/openapi.json` |
| `SPEC — Custom Asset Views.md` | The three endpoints return no data. Drop `GetCustomViewByTitle` from D6, D13 and the layout |
| `PLAN — Custom Asset Views.md` | Record the change in "Deviations during implementation" |

What stays, because other code still uses it:

- `CustomViewRepository::findByOwnerAndTitle()`: the create and update handlers check title uniqueness with it.
- `GetCustomView`, its handler and `CustomViewDetailsView`: `GET /views/{id}` uses them.
- `CustomViewDetailsResponse` in OpenAPI: `GET /views/{id}` returns it.

## Commands

```
make qa      # Pint (read-only), Larastan, Deptrac
make docs    # validates openapi.yaml and regenerates openapi.json
make test    # PHPUnit against logis_testing
make check   # qa + docs-lint + test, required before the commit
```

## Code style

The write actions keep the shape of `destroy`:

```php
public function update(UpdateCustomViewRequest $request, int $id): JsonResponse
{
    $this->commandBus->dispatch(command: $request->toCommand(id: $id));

    return ApiResponse::success(message: 'View updated');
}
```

The rules of `AGENTS.md` apply.

## Testing strategy

- Feature tests of the three endpoints:
  - the status, the message, `X-Refreshed-Token` and an empty `data`;
  - the saved values, read from `custom_views`: owner, customer, title, description, columns, filters with their `user_filter`, `is_public` and `is_default`.
- The 401, 403, 404, 409 and 422 tests don't change.
- No new unit tests. The handler tests keep covering the commands.
- `grep -rn "GetCustomViewByTitle\|NotFoundException::withTitle" app tests docs` returns nothing.

## Boundaries

- **Always:** one `feat(views)!` commit for the code, tests and OpenAPI, since the response contract changes, plus one `docs(specs)` commit for this spec and the updates to the views spec and plan; `make check` before each commit; work on the branch `feat/custom-views`.
- **Ask first:** returning only the id (or a `Location` header) on create.
- **Never:** change `GET`, `execute` or `DELETE`; change status codes or error responses; remove `findByOwnerAndTitle()`.

## Success criteria

1. `POST /views`, `PUT /views/{id}` and `POST /views/{id}/set-default` answer with an empty `data` and their current status and message.
2. No code, test or document refers to `GetCustomViewByTitle` or `CustomViewNotFoundException::withTitle`, and the three FormRequests have no `toQuery()`.
3. OpenAPI documents `EmptyResponse` for the three endpoints.
4. `make check` is green.

## Open questions

None.
