include make/*.mk
.DEFAULT_GOAL:=help

DOCKER_IMAGE_PREFIX=registry.kiora.tech/kiora/crm-gdb_
APP_VERSION=$(shell grep -oP 'APP_VERSION=\K[0-9\.]+' .env)

update: init vendor update_symfony build test-unit

build_app: ## build the app pour prod (sans modifier votre environnement local) - make build_app TAG=ton_tag
ifndef TAG
	$(error Vous devez spécifier une version avec 'make build_app TAG=ton_tag')
endif
	@echo "Lancement du script de build pour la production..."
	@chmod +x ./build.sh
	./build.sh $(TAG)