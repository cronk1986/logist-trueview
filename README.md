# Logis API

The TrueView API refactored from legacy PHP to Laravel 11 (PHP 8.3), with hexagonal architecture, DDD and CQRS. It covers the three parts of the technical assessment:

- **Authentication:** Sanctum tokens that rotate on every request, expire, and can be refreshed and revoked.
- **Global search:** one query across tickets, assets, contracts and locations of the customers the user can see.
- **Custom views:** saved columns and filters over assets, tickets, contracts or locations, executed safely with cursor pagination.

## Requirements

- Docker with Docker Compose
- `make`

PHP, Composer and MySQL run inside the containers. Nothing else is needed on the host.

## Getting started

```bash
make up      # creates .env from .env.example, starts the containers, installs dependencies, generates APP_KEY and migrates
make fresh   # recreates the database with demo data
```

| Service | URL |
|---|---|
| API | http://localhost:8000/api/v1 |
| Swagger UI | http://localhost:8080 |

Demo users, both with password `password`:

| Email | Sees |
|---|---|
| `demo@logis.test` | Its customer "Logis Demo" and its two sub-accounts. Owns the demo views |
| `limited@logis.test` | Only "Logis Demo". Sees the public views of its customer |

In Swagger UI, log in with one of them through `POST /auth/login`. The token and its rotation are handled automatically.

## Configuration

Besides the standard Laravel variables, `.env.example` defines:

| Variable | Default | Purpose |
|---|---|---|
| `ACCESS_TOKEN_TTL_MINUTES` | 60 | Lifetime of an access token |
| `ACCESS_TOKEN_GRACE_SECONDS` | 10 | Time a rotated token keeps working, for parallel requests |
| `ACCESS_TOKEN_PRUNE_HOURS` | 24 | Age after which expired tokens are deleted |
| `SEARCH_CACHE_TTL_SECONDS` | 300 | Cache of search results |
| `VIEWS_CACHE_TTL_SECONDS` | 300 | Cache of view executions |
| `VIEWS_EXECUTION_TIMEOUT_MS` | 5000 | Maximum time of a view execution before it answers 504 |
| `CORS_ALLOWED_ORIGINS` | `http://localhost:8080` | Comma-separated origins allowed by CORS |
| `APP_PORT`, `FORWARD_DB_PORT`, `APIDOCS_PORT` | 8000, 3307, 8080 | Ports published on the host |

## Endpoints

| Area | Endpoints |
|---|---|
| Auth | `POST /auth/login`, `POST /auth/logout`, `POST /auth/refresh`, `GET /auth/me` |
| Search | `GET /search?q=…&limit=…&entities=…` |
| Views | `GET /views`, `POST /views`, `GET /views/{id}`, `PUT /views/{id}`, `DELETE /views/{id}`, `POST /views/{id}/execute`, `POST /views/{id}/set-default` |

- Every response has the same format: `{ success, data, message, meta }`. The HTTP status tells what happened.
- Every authenticated request returns a new token in the `X-Refreshed-Token` header, which replaces the stored one.
- The API allows 60 requests per minute per user.

The full contract, with examples and error codes, is in Swagger UI and in `docs/openapi/openapi.yaml`.

## Common commands

| Command | Does |
|---|---|
| `make up` / `make down` | Start or stop the project (`down` keeps the database) |
| `make fresh` | Recreate the database with demo data |
| `make test` | Run the test suite |
| `make qa` | Code style, static analysis and layer dependencies |
| `make check` | Everything required before a commit: `qa`, OpenAPI lint and tests |
| `make docs` | Validate the OpenAPI spec and rebuild `docs/openapi.json` |
| `make shell` / `make logs` | Shell in the Laravel container / follow the logs |
| `make destroy` | Remove containers and volumes, **deleting the database** |

`make help` lists every target.

## Tests and quality

`make check` runs, in order:

1. Laravel Pint (PSR-12 and project rules)
2. Larastan level 8, with custom rules for named arguments, handler signatures and semantic exceptions
3. Deptrac, which enforces the dependencies between layers
4. Redocly lint of the OpenAPI spec
5. PHPUnit, about 760 tests

Unit tests (`tests/Unit/`) test the domain and the handlers with in-memory implementations, without a database. Feature tests (`tests/Feature/`) call the endpoints against a separate MySQL database, `logis_testing`, which `make test` creates.

## Docker services

| Service | Role |
|---|---|
| `laravel` | PHP-FPM with the application |
| `nginx` | Web server, published on `APP_PORT` |
| `mysql` | MySQL database, published on `FORWARD_DB_PORT` |
| `worker` | Queue worker for asynchronous event listeners |
| `scheduler` | Laravel scheduler: prunes expired tokens every hour |
| `apidocs` | Swagger UI, published on `APIDOCS_PORT` |

## Workflow

- Every feature starts with a spec in `specs/SPEC — <name>.md`. Once approved, an implementation plan follows in `specs/PLAN — <name>.md`.
- Branches follow [Conventional Branch](https://conventional-branch.github.io) (`feat/custom-views`) and commits follow [Conventional Commits](https://www.conventionalcommits.org) (`feat(views): …`).
- A change is complete only when `make check` passes. After each implementation: `make qa`, simplification, `make test`, code review and, if an endpoint changed, `make docs`.

`AGENTS.md` holds the full rules.

## Further documentation

- `ARCHITECTURE.md`: design decisions, assumptions and trade-offs
- `AGENTS.md`: architecture rules and code conventions
- `specs/`: the spec and implementation plan of each feature
- `docs/openapi/openapi.yaml`: the API contract
