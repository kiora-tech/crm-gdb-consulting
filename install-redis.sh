#!/bin/bash

set -e

echo "🚀 Installation Redis + Cache pour Microsoft Graph"
echo "=================================================="
echo ""

# Couleurs
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Fonction pour afficher les messages
info() {
    echo -e "${GREEN}✓${NC} $1"
}

warn() {
    echo -e "${YELLOW}⚠${NC} $1"
}

error() {
    echo -e "${RED}✗${NC} $1"
}

# 1. Installer les packages Composer
echo ""
echo "📦 Étape 1/6 : Installation des packages Composer..."
if command -v composer &> /dev/null; then
    composer require symfony/redis-messenger --no-interaction
    info "Package symfony/redis-messenger installé"
else
    warn "Composer n'est pas disponible localement"
    echo "   Vous devrez installer le package manuellement après avoir démarré Docker :"
    echo "   docker compose exec php composer require symfony/redis-messenger"
fi

# 2. Arrêter les containers
echo ""
echo "🛑 Étape 2/6 : Arrêt des containers..."
if docker compose down; then
    info "Containers arrêtés"
else
    warn "Erreur lors de l'arrêt des containers (peut-être déjà arrêtés)"
fi

# 3. Rebuild les images
echo ""
echo "🔨 Étape 3/6 : Rebuild des images Docker (avec extension Redis)..."
echo "   Cela peut prendre quelques minutes..."
if docker compose build php supervisor; then
    info "Images rebuilt avec succès"
else
    error "Erreur lors du rebuild des images"
    exit 1
fi

# 4. Démarrer les services
echo ""
echo "▶️  Étape 4/6 : Démarrage des services..."
if docker compose up -d; then
    info "Services démarrés"
else
    error "Erreur lors du démarrage des services"
    exit 1
fi

# Attendre que les services soient prêts
echo ""
echo "⏳ Attente que les services soient prêts..."
sleep 5

# 5. Vérifier l'installation
echo ""
echo "🔍 Étape 5/6 : Vérification de l'installation..."

# Vérifier Redis
if docker compose exec -T redis redis-cli ping | grep -q "PONG"; then
    info "Redis est accessible"
else
    error "Redis n'est pas accessible"
    exit 1
fi

# Vérifier l'extension PHP Redis
if docker compose exec -T php php -r "echo extension_loaded('redis') ? 'OK' : 'FAILED';" | grep -q "OK"; then
    info "Extension PHP Redis chargée"
else
    error "Extension PHP Redis non chargée"
    exit 1
fi

# 6. Installer le package Composer si pas fait
echo ""
echo "📦 Installation du package Symfony Redis dans le container..."
if docker compose exec -T php composer show symfony/redis-messenger &> /dev/null; then
    info "Package symfony/redis-messenger déjà installé"
else
    warn "Installation du package symfony/redis-messenger..."
    docker compose exec -T php composer require symfony/redis-messenger --no-interaction
fi

# 7. Vider le cache
echo ""
echo "🗑️  Étape 6/6 : Configuration du cache..."
if docker compose exec -T php bin/console cache:clear; then
    info "Cache vidé"
else
    warn "Erreur lors du vidage du cache"
fi

if docker compose exec -T php bin/console cache:warmup; then
    info "Cache réchauffé"
else
    warn "Erreur lors du réchauffement du cache"
fi

# Vérifier le pool de cache
echo ""
echo "🔍 Vérification du cache pool..."
if docker compose exec -T php bin/console cache:pool:list | grep -q "cache.microsoft_graph"; then
    info "Pool cache.microsoft_graph configuré"
else
    error "Pool cache.microsoft_graph non trouvé"
    exit 1
fi

# Résumé final
echo ""
echo "=================================================="
echo -e "${GREEN}✅ Installation terminée avec succès !${NC}"
echo "=================================================="
echo ""
echo "📊 État des services :"
docker compose ps

echo ""
echo "🎯 Prochaines étapes :"
echo ""
echo "1. Vérifier que supervisor gère le worker Messenger :"
echo "   docker compose logs supervisor | tail -20"
echo ""
echo "2. Tester le cache Redis :"
echo "   docker compose exec redis redis-cli KEYS \"ms_graph:*\""
echo ""
echo "3. Ouvrir l'application :"
echo "   http://localhost:8082/outlook-calendar"
echo ""
echo "4. Vérifier les performances :"
echo "   - 1er chargement : 3-8s (normal)"
echo "   - 2ème chargement : < 100ms (cache) ✨"
echo ""
echo "5. Voir les logs de refresh async :"
echo "   docker compose exec php tail -f var/log/dev.log | grep 'Refreshing'"
echo ""
echo "📖 Pour plus de détails, consultez : INSTALL_REDIS.md"
