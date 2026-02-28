#!/bin/bash

# Script pour construire et publier les images Docker de production
# Utilisation: ./build.sh <version_tag>

set -e

if [ -z "$1" ]; then
  echo "Erreur: Vous devez spécifier un tag de version"
  echo "Usage: ./build.sh <version_tag>"
  exit 1
fi

TAG=$1
DOCKER_IMAGE_PREFIX="registry.kiora.tech/kiora/crm-gdb_"
BASE_TAG="0.4.1"  # Version fixe pour les images de base

echo "Préparation pour la création des images Docker:"
echo " - Images finales: version $TAG"
echo " - Images de base: version $BASE_TAG (fixe)"

# Création d'un dossier de build temporaire pour isoler le build de production
echo "Création d'un dossier de build temporaire..."
BUILD_DIR=$(mktemp -d -t crm-gdb-build-XXXXXXXXXX)
echo "Dossier de build temporaire: $BUILD_DIR"

# Copie des fichiers du projet dans le dossier temporaire
echo "Copie des fichiers du projet dans le dossier temporaire..."
cp -r . $BUILD_DIR/

# Préparation de l'environnement de production dans le dossier temporaire
echo "Configuration du mode production dans le dossier temporaire..."
sed -i "s/APP_ENV=.*/APP_ENV=prod/" $BUILD_DIR/.env
sed -i "s/APP_VERSION=.*/APP_VERSION=$TAG/" $BUILD_DIR/.env

# Nettoyage des répertoires de cache dans le dossier temporaire
echo "Nettoyage des répertoires de cache dans le dossier temporaire..."
rm -rf $BUILD_DIR/var/cache/* $BUILD_DIR/var/log/* $BUILD_DIR/public/uploads/client/prospect/* $BUILD_DIR/public/uploads/client/resume/* $BUILD_DIR/public/uploads/template/prospect/* $BUILD_DIR/public/uploads/template/resume/*

# Vérifier si les images de base existent localement
echo "Vérification de l'existence des images de base (version $BASE_TAG)..."
PHP_BASE_EXISTS=$(docker image ls -q ${DOCKER_IMAGE_PREFIX}php_base:$BASE_TAG)
PHP_BUILD_EXISTS=$(docker image ls -q ${DOCKER_IMAGE_PREFIX}php_build:$BASE_TAG)

# Si les images de base n'existent pas localement, les télécharger depuis le registre
if [ -z "$PHP_BASE_EXISTS" ] || [ -z "$PHP_BUILD_EXISTS" ]; then
  echo "Images de base non trouvées localement. Téléchargement depuis le registre..."
  docker pull ${DOCKER_IMAGE_PREFIX}php_base:$BASE_TAG || echo "ERREUR: Impossible de télécharger l'image ${DOCKER_IMAGE_PREFIX}php_base:$BASE_TAG"
  docker pull ${DOCKER_IMAGE_PREFIX}php_build:$BASE_TAG || echo "ERREUR: Impossible de télécharger l'image ${DOCKER_IMAGE_PREFIX}php_build:$BASE_TAG"
fi

# Vérifier à nouveau si les images sont disponibles
PHP_BASE_EXISTS=$(docker image ls -q ${DOCKER_IMAGE_PREFIX}php_base:$BASE_TAG)
PHP_BUILD_EXISTS=$(docker image ls -q ${DOCKER_IMAGE_PREFIX}php_build:$BASE_TAG)

if [ -z "$PHP_BASE_EXISTS" ] || [ -z "$PHP_BUILD_EXISTS" ]; then
  echo "ERREUR: Images de base introuvables. Veuillez exécuter d'abord 'make docker_publish IMAGE=php TAG=$BASE_TAG'"
  rm -rf $BUILD_DIR
  exit 1
fi

# Installation des dépendances pour la production
echo "Installation des dépendances pour la production..."
docker run --rm \
  -v $BUILD_DIR:/app \
  -w /app \
  -e APP_ENV=prod \
  -e COMPOSER_MEMORY_LIMIT=-1 \
  -u $(id -u):$(id -g) \
  ${DOCKER_IMAGE_PREFIX}php_build:$BASE_TAG composer install --no-dev --optimize-autoloader

# Compilation des assets
echo "Compilation des assets..."
docker run --rm \
  --network host \
  -v $BUILD_DIR:/app \
  -w /app \
  -e APP_ENV=prod \
  -u $(id -u):$(id -g) \
  ${DOCKER_IMAGE_PREFIX}php_base:$BASE_TAG bin/console asset-map:compile

# Construction et publication des images finales
cd $BUILD_DIR
echo "Construction et publication de l'image php principale..."
docker buildx build --platform linux/arm64,linux/amd64 --target prod -f docker/php/Dockerfile -t ${DOCKER_IMAGE_PREFIX}php:$TAG --push .

echo "Construction et publication de l'image supervisor..."
docker buildx build --platform linux/arm64,linux/amd64 --target supervisor -f docker/php/Dockerfile -t ${DOCKER_IMAGE_PREFIX}php:$TAG-supervisor --push .

echo "Construction et publication de l'image nginx..."
docker buildx build --platform linux/arm64,linux/amd64 --target prod -f docker/nginx/Dockerfile -t ${DOCKER_IMAGE_PREFIX}nginx:$TAG --push .

# Retour au répertoire du projet
cd -

# Mise à jour des références de version dans le projet original
echo "Mise à jour des versions dans le projet..."
sed -i "s/\(registry\.kiora\.tech\/kiora\/crm-gdb_php:\)[0-9.]\+/\1$TAG/" compose.yaml
sed -i "s/\(registry\.kiora\.tech\/kiora\/crm-gdb_nginx:\)[0-9.]\+/\1$TAG/" compose.yaml
sed -i "s/APP_VERSION=.*/APP_VERSION=$TAG/" .env
sed -i "s/version-[0-9.]\+-blue/version-$TAG-blue/" README.md

# Nettoyage du dossier temporaire
echo "Nettoyage du dossier temporaire..."
rm -rf $BUILD_DIR

echo "Construction et publication terminées avec succès!"
echo "Images finales version $TAG publiées en utilisant les images de base version $BASE_TAG (fixe)."