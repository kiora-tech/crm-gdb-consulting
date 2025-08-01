# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Build & Run Commands
- Tests: `docker-compose exec php bin/phpunit [path]` (e.g., `docker-compose exec php bin/phpunit tests/Controller/SecurityControllerTest.php`)
- Single test: `docker-compose exec php bin/phpunit --filter=testName tests/Path/Class.php`
- Build: `make build` or `docker-compose up -d`
- Build Docker image with tag: `make build_app TAG=0.11.1` (creates Docker image with version tag)
- Update DB: `docker-compose exec php bin/console doctrine:migrations:migrate`
- Load fixtures: `docker-compose exec php bin/console doctrine:fixtures:load`
- Quality checks: `docker-compose exec php vendor/bin/grumphp run` (runs PHPStan and PHPUnit)
- Reset database: `make reset-db`
- Access PHP container: `make php`
- Compile assets: `make ready`
- Initialize project: `make init` (builds Docker images)
- Install Symfony: `make install_symfony` (creates DB, runs migrations, loads fixtures)
- Migrate files to MinIO: `docker-compose exec php bin/console app:migrate-files [--dry-run] [--type=documents|templates|all]`

## IMPORTANT: Always run GrumPHP after making changes
After making any code changes, ALWAYS run GrumPHP to verify that your changes pass all quality checks:
```
docker-compose exec php vendor/bin/grumphp run
```
This will run PHPStan static analysis and PHPUnit tests to ensure code quality and prevent regressions.

## Project Architecture
- Symfony 7.2 application with PHP 8.4
- Docker-based development environment with PHP, MySQL, Nginx, MinIO
- Core entities: Customer (central entity), Energy, Document, Contact, Comment
- Key components:
  - BaseCrudController: Abstract controller for standard CRUD operations
  - Twig components (Button, Table, DeleteButton, FormActions, ClientSearch)
  - ImportService: Handles batch Excel imports via message queue
  - TemplateProcessor: Generates documents from templates
  - Flysystem for file storage with MinIO (S3-compatible)

## Development URLs
- Application: http://localhost:8080
- MailHog (email testing): http://localhost:8025
- MySQL: localhost:3306
- MinIO Console: http://localhost:9001 (credentials: minio / minio123)

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

## Core Development Patterns
- Use BaseCrudController for entity CRUD operations
- Use CustomerInfoController for customer-related entities
- Implement Twig components for UI elements
- PaginationService for handling pagination
- ImportService and message queue for batch operations
- TemplateProcessor for document generation from templates
- Flysystem for file storage (configured with MinIO)