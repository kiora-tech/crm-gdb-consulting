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
	docker compose exec database mysql -usymfony -psymfony -e "DROP DATABASE IF EXISTS symfony_test; CREATE DATABASE symfony_test;" || true
	docker compose exec php sh -c 'DATABASE_URL="mysql://symfony:symfony@database:3306/symfony_test?serverVersion=8.0.40&charset=utf8mb4" bin/console doctrine:migrations:migrate --no-interaction'

grumphp: ## Run GrumPHP (PHPStan, PHPUnit, PHP CS Fixer)
	$(PHP) vendor/bin/grumphp run

grumphp-ci: ## Run GrumPHP for CI (non-interactive)
	docker compose exec php vendor/bin/grumphp run

.PHONY: test test-coverage test-% reset-test-db grumphp grumphp-ci