include make/*.mk
.DEFAULT_GOAL:=help

DOCKER_IMAGE_PREFIX=registry.kiora.tech/kiora/crm-gdb_
APP_VERSION=$(shell grep -oP 'APP_VERSION=\K[0-9\.]+' .env)

update: init vendor update_symfony build test-unit

build_app: ## build the app  make build_app TAG=ton_tag
ifndef TAG
	$(error Vous devez spécifier une image avec 'make build_app TAG=ton_tag')
endif
	rm -rf var/cache/* var/log/* public/uploads/client/prospect/* public/uploads/client/resume/* public/uploads/template/prospect/* public/uploads/template/resume/*
	sed -i 's/APP_VERSION=.*/APP_VERSION=$(TAG)/' .env;
	${DOCKER_CMD} run --rm \
		-v $(shell pwd):/app \
		-w /app \
		-e APP_ENV=prod \
		-e COMPOSER_MEMORY_LIMIT=-1 \
		-u $(shell id -u):$(shell id -g) \
		$(DOCKER_IMAGE_PREFIX)php_build:0.4.1 composer install --no-dev --optimize-autoloader

	${DOCKER_CMD} run --rm \
		-v $(shell pwd):/app \
		-w /app \
		-e APP_ENV=prod \
		-u $(shell id -u):$(shell id -g) \
		$(DOCKER_IMAGE_PREFIX)php_base:0.4.1 bin/console asset-map:compile

	# Étape 4: Construire l'image Docker de l'application avec un tag incrémental et push directement vers le registre
	${DOCKER_CMD} buildx build --platform linux/arm/v7,linux/arm64,linux/amd64 --target prod -f docker/php/Dockerfile -t $(DOCKER_IMAGE_PREFIX)php:$(TAG) --push .
	${DOCKER_CMD} buildx build --platform linux/arm/v7,linux/arm64,linux/amd64 --target supervisor -f docker/php/Dockerfile -t $(DOCKER_IMAGE_PREFIX)php:$(TAG)-supervisor --push .
	${DOCKER_CMD} buildx build --platform linux/arm/v7,linux/arm64,linux/amd64 --target prod -f docker/nginx/Dockerfile -t $(DOCKER_IMAGE_PREFIX)nginx:$(TAG) --push .

	#ajouter du tag dans le fichier compose.yaml pour php et nginx
	sed -i 's/\(registry\.kiora\.tech\/kiora\/crm-gdb_php:\)[0-9.]\+/\1$(TAG)/' compose.yaml
	sed -i 's/\(registry\.kiora\.tech\/kiora\/crm-gdb_nginx:\)[0-9.]\+/\1$(TAG)/' compose.yaml