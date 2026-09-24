COMPOSE := docker compose -f compose.dev.yaml
APP     := $(COMPOSE) exec laravel
# The worker and the scheduler start separately, once dependencies are installed and migrations have run
CORE    := laravel nginx mysql apidocs

# Host UID/GID so files created inside the container belong to you
export HOST_UID := $(shell id -u)
export HOST_GID := $(shell id -g)

.DEFAULT_GOAL := help
.PHONY: help up down stop restart worker-restart build ps logs shell artisan composer install migrate fresh test-db test pint stan deptrac qa check docs docs-lint destroy

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

.env:
	cp .env.example .env

up: .env ## Start the project: containers, dependencies, APP_KEY, migrations, queue worker and scheduler
	$(COMPOSE) up -d --build --wait $(CORE)
	$(MAKE) install
	$(COMPOSE) up -d worker scheduler
	@echo ""
	@echo "  API:      http://localhost:$${APP_PORT:-8000}"
	@echo "  API docs: http://localhost:$${APIDOCS_PORT:-8080}"
	@echo "  MySQL:    localhost:$${FORWARD_DB_PORT:-3307}"
	@echo "  Worker:   consuming queues events,default (make logs s=worker)"
	@echo "  Schedule: prunes expired access tokens hourly (make logs s=scheduler)"

install: ## Install dependencies, generate APP_KEY if missing and migrate
	$(APP) composer install --no-interaction
	@grep -q '^APP_KEY=base64:' .env || $(APP) php artisan key:generate --no-interaction
	$(APP) php artisan migrate --force

down: ## Stop and remove the containers (keeps the database)
	$(COMPOSE) down

stop: ## Stop the containers without removing them
	$(COMPOSE) stop

restart: ## Restart the containers
	$(COMPOSE) restart

worker-restart: ## Restart the queue worker
	$(COMPOSE) restart worker

build: ## Rebuild the Laravel image
	$(COMPOSE) build laravel

ps: ## Container status
	$(COMPOSE) ps

logs: ## Follow logs (make logs s=nginx for a single service)
	$(COMPOSE) logs -f $(s)

shell: ## Shell inside the Laravel container
	$(APP) sh

artisan: ## Run artisan (make artisan c="route:list")
	$(APP) php artisan $(c)

composer: ## Run composer (make composer c="require foo/bar")
	$(APP) composer $(c)

migrate: ## Run the migrations
	$(APP) php artisan migrate

fresh: ## Recreate the database and run the seeders
	$(APP) php artisan migrate:fresh --seed

test-db: ## Create the logis_testing database if it does not exist
	$(COMPOSE) exec -T mysql sh -c 'mysql -uroot -p"$$MYSQL_ROOT_PASSWORD"' < docker/mysql/init/01-testing-db.sql

test: test-db ## Run the tests (against the logis_testing database)
	$(APP) php artisan test

pint: ## Format the code with Laravel Pint (PSR-12)
	$(APP) ./vendor/bin/pint

stan: ## Static analysis with Larastan (level 8)
	$(APP) ./vendor/bin/phpstan analyse --memory-limit=1G

deptrac: ## Check dependencies between layers
	$(APP) ./vendor/bin/deptrac analyse

qa: ## Quality checks: code style (read-only), static analysis and layers
	$(APP) ./vendor/bin/pint --test
	$(APP) ./vendor/bin/phpstan analyse --memory-limit=1G --no-progress
	$(APP) ./vendor/bin/deptrac analyse --no-progress

check: qa docs-lint test ## Everything required before a commit: quality checks, OpenAPI validation and tests

docs-lint: ## Validate the OpenAPI spec
	docker run --rm -v "$(CURDIR)/docs:/spec" redocly/cli lint /spec/openapi/openapi.yaml

docs: docs-lint ## Validate and build docs/openapi.json from docs/openapi/
	docker run --rm -v "$(CURDIR)/docs:/spec" redocly/cli bundle /spec/openapi/openapi.yaml -o /spec/openapi.json

destroy: ## Remove containers and volumes (DELETES the database)
	$(COMPOSE) down -v
