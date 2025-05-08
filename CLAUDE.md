# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Build & Run Commands
- Tests: `docker-compose exec php bin/phpunit [path]` (e.g., `docker-compose exec php bin/phpunit tests/Controller/SecurityControllerTest.php`)
- Single test: `docker-compose exec php bin/phpunit --filter=testName tests/Path/Class.php`
- Build: `make build` or `docker-compose up -d`
- Update DB: `docker-compose exec php bin/console doctrine:migrations:migrate`
- Load fixtures: `docker-compose exec php bin/console doctrine:fixtures:load`
- Quality checks: `docker-compose exec php vendor/bin/grumphp run` (runs PHPStan and PHPUnit)

## IMPORTANT: Always run GrumPHP after making changes
After making any code changes, ALWAYS run GrumPHP to verify that your changes pass all quality checks:
```
docker-compose exec php vendor/bin/grumphp run
```
This will run PHPStan static analysis and PHPUnit tests to ensure code quality and prevent regressions.

## Code Style Guidelines
- PSR-12 for formatting
- Symfony coding standards
- Type hints required for all methods and properties
- Repository methods should return properly typed objects
- Naming: camelCase for methods/variables, PascalCase for classes
- Controllers should extend AbstractController
- Entities should use attributes for ORM mapping
- Controllers should use attributes for routes
- Repository classes for DB queries, no direct query in controllers
- TWIG templates use snake_case variables, kebab-case classes
- Translations stored in YAML format, accessed via trans filter in templates