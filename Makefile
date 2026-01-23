.DEFAULT_GOAL := help

# Current user ID and group ID except MacOS where it conflicts with Docker abilities
ifeq ($(shell uname), Darwin)
    export UID=1000
    export GID=1000
else
    export UID=$(shell id -u)
    export GID=$(shell id -g)
endif

export COMPOSE_PROJECT_NAME=migration-checker

init: build composer-install codecept-build ## Initialize the project

build: ## Build Docker image
	docker compose build

up: ## Up the dev environment
	docker compose up -d

down: ## Down the dev environment
	docker compose down --remove-orphans

clear: ## Remove development Docker containers and volumes
	docker compose down --volumes --remove-orphans

shell: ## Get into container shell
	docker compose exec app bash

composer-install: ## Run `composer install`
	docker compose run --rm app composer install

composer-update: ## Run `composer update`
	docker compose run --rm app composer update

codecept-build: ## Run `codeception build`
	docker compose run --rm app codecept build

test: ## Run all tests
	docker compose run --rm app codecept run Unit,Db

test-debug: ## Run all tests with debugging mode enabled
	docker compose run --rm -e XDEBUG_MODE=debug,develop app codecept run Unit,Db --debug

validate: syntax phpcs phpstan rector ## Do all validations (coding style, PHP syntax, static analysis, etc.)

phpcs: ## Validate the coding style
	docker compose run --rm app phpcs --extensions=php --colors --standard=PSR12Ext --runtime-set php_version 80100 --ignore=vendor/* -p -s .

phpcbf: ## Fix the coding style
	docker compose run --rm app phpcbf --extensions=php --colors --standard=PSR12Ext --runtime-set php_version 80100 --ignore=vendor/* -p .

syntax: ## Validate PHP syntax
	docker compose run --rm app parallel-lint --colors src tests

phpstan: ## Do static analysis with PHPStan
	docker compose run --rm app phpstan analyse --memory-limit=256M .

rector: ## Do code analysis with Rector
	docker compose run --rm app rector --dry-run

# Output the help for each task, see https://marmelab.com/blog/2016/02/29/auto-documented-makefile.html
help: ## This help
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)
