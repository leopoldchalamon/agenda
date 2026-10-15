DOCKER_COMP = docker compose
PHP_EXEC    = $(DOCKER_COMP) exec -T php

.DEFAULT_GOAL = help
.PHONY: help up down sh test phpstan cs cs-fix deptrac schema check fix

help: ## List targets
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-10s\033[0m %s\n", $$1, $$2}'

up: ## Start the containers
	@$(DOCKER_COMP) up -d --wait

down: ## Stop the containers
	@$(DOCKER_COMP) down --remove-orphans

sh: ## Open a shell in the php container
	@$(DOCKER_COMP) exec php sh

test: ## Run PHPUnit
	@$(PHP_EXEC) bin/phpunit

phpstan: ## Run PHPStan
	@$(PHP_EXEC) vendor/bin/phpstan analyse --no-progress

cs: ## Check code style (dry run)
	@$(PHP_EXEC) vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## Fix code style
	@$(PHP_EXEC) vendor/bin/php-cs-fixer fix

deptrac: ## Check architecture layers
	@$(PHP_EXEC) vendor/bin/deptrac --no-progress

schema: ## Validate the Doctrine mapping and database schema
	@$(PHP_EXEC) bin/console doctrine:schema:validate

check: cs phpstan deptrac schema test ## Run every check

fix: cs-fix ## Fix what can be fixed automatically
