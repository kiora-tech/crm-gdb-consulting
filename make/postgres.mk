PSQL ?= psql
PG_DUMP ?= pg_dump

##@ PostgreSQL
load-db: backup.sql ## Load database
	ssh $(PREPROD_USER)@$(PREPROD_IP) 'rm backup.sql'

restore-db: backup.sql ## Restore database
	cat backup.sql | docker compose exec -T database $(PSQL) -U symfony $(DB_NAME)

backup.sql:
	ssh $(PREPROD_USER)@$(PREPROD_IP) 'docker compose exec -T database $(PG_DUMP) -U symfony $(DB_NAME) > backup.sql'
	scp $(PREPROD_USER)@$(PREPROD_IP):backup.sql .

.PHONY: load-db restore-db
