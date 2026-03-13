##@ Testing

PHPUNIT = vendor/bin/phpunit

# Run tests without code coverage
test: ## Run PHPUnit tests
	$(PHP) $(PHPUNIT)

# Run a specific testsuite
test-%: ## Run a specific PHPUnit testsuite (e.g., make test-<suite_name>)
	$(if $(shell $(MAKE) -n before-test-$* 2> /dev/null),$(MAKE) before-test-$*,)
	$(PHP) $(PHPUNIT) --testsuite $*

# Add this line to your Makefile to define a before-test-<suite_name> target
# before-test-<suite_name>:

reset-test-db: ## Reset test database (drop, create, migrate)
	docker compose exec database psql -U symfony -c "DROP DATABASE IF EXISTS symfony_test;" || true
	docker compose exec database psql -U symfony -c "CREATE DATABASE symfony_test;" || true
	docker compose exec php sh -c 'DATABASE_URL="postgresql://symfony:symfony@database:5432/symfony_test?serverVersion=16&charset=utf8" bin/console doctrine:migrations:migrate --no-interaction'

grumphp: ## Run GrumPHP (PHPStan, PHPUnit, PHP CS Fixer)
	$(PHP) vendor/bin/grumphp run

grumphp-ci: ## Run GrumPHP for CI (non-interactive)
	docker compose exec php vendor/bin/grumphp run

.PHONY: test test-coverage test-% reset-test-db grumphp grumphp-ci