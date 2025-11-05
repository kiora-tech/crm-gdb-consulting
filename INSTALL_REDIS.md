# Installation et Configuration Redis - Guide Complet

## ✅ Déjà fait

1. ✅ Extension PHP Redis ajoutée au Dockerfile
2. ✅ Redis ajouté au docker-compose
3. ✅ Cache configuré dans cache.yaml
4. ✅ Code implémenté (Service + Messages + Handlers + Controllers)

## 🚀 Étapes d'installation

### 1. Installer les packages Composer nécessaires

```bash
cd /home/james/projets/crm-gdb-consulting

# Installer le package Symfony Redis Messenger
composer require symfony/redis-messenger

# Si l'extension PHP Redis n'est pas disponible, installer Predis comme fallback
# composer require predis/predis
```

### 2. Rebuild les images Docker (pour installer l'extension PHP Redis)

```bash
# Arrêter les containers
docker compose down

# Rebuild l'image PHP avec l'extension Redis
docker compose build php

# Rebuild aussi supervisor qui utilise la même base
docker compose build supervisor
```

### 3. Démarrer tous les services

```bash
# Démarrer tous les containers (incluant Redis)
docker compose up -d

# Vérifier que tous les services tournent
docker compose ps
```

Vous devriez voir :
- ✅ php (running)
- ✅ redis (running)
- ✅ database (running)
- ✅ nginx (running)
- ✅ supervisor (running)
- ✅ mailer (running)

### 4. Vérifier l'installation Redis

```bash
# Tester que Redis est accessible
docker compose exec redis redis-cli ping
# Doit retourner: PONG

# Vérifier que PHP peut se connecter à Redis
docker compose exec php php -r "echo extension_loaded('redis') ? 'Redis extension loaded!' : 'Redis extension NOT loaded';"
# Doit retourner: Redis extension loaded!
```

### 5. Vider le cache Symfony

```bash
docker compose exec php bin/console cache:clear
docker compose exec php bin/console cache:warmup
```

### 6. Tester le cache Redis

```bash
# Vérifier que Symfony peut se connecter à Redis
docker compose exec php bin/console cache:pool:list

# Vous devriez voir: cache.microsoft_graph
```

### 7. Démarrer le worker Messenger (pour traiter les messages async)

Le worker est normalement déjà géré par Supervisor. Vérifiez :

```bash
# Vérifier que supervisor tourne
docker compose ps supervisor

# Voir les logs supervisor
docker compose logs supervisor | tail -50

# Si besoin, démarrer manuellement
docker compose exec php bin/console messenger:consume async -vv
```

### 8. Tester l'implémentation

#### Test 1 : Cache vide (premier chargement)

```bash
# Vider le cache Redis
docker compose exec redis redis-cli FLUSHALL

# Maintenant, ouvrez http://localhost:8082/outlook-calendar
# Premier chargement : 3-8 secondes (normal, pas de cache)
```

#### Test 2 : Cache hit (chargements suivants)

```bash
# Rechargez la page http://localhost:8082/outlook-calendar
# Deuxième chargement : < 100ms ✨ (ultra rapide !)
```

#### Test 3 : Vérifier les clés de cache

```bash
# Voir les clés de cache créées
docker compose exec redis redis-cli KEYS "ms_graph:*"

# Exemple de sortie :
# 1) "ms_graph:1:calendars"
# 2) "ms_graph:1:events:AAMkAD...:2025-11-04:2025-12-04"
# 3) "ms_graph:1:categories"
```

#### Test 4 : Vérifier les messages async

```bash
# Voir les messages en attente
docker compose exec php bin/console messenger:stats

# Voir les logs de refresh
docker compose exec php tail -f var/log/dev.log | grep "Refreshing"
```

## 🐛 Dépannage

### Problème : "Connection refused to redis:6379"

```bash
# Vérifier que Redis tourne
docker compose ps redis

# Redémarrer Redis
docker compose restart redis

# Vérifier les logs
docker compose logs redis
```

### Problème : "Extension redis not loaded"

```bash
# Rebuild l'image PHP
docker compose down
docker compose build php --no-cache
docker compose up -d

# Vérifier à nouveau
docker compose exec php php -m | grep redis
```

### Problème : "Pool cache.microsoft_graph not found"

```bash
# Vider le cache Symfony
docker compose exec php bin/console cache:clear

# Vérifier la config
docker compose exec php bin/console debug:container cache.microsoft_graph
```

### Problème : Messages async ne sont pas traités

```bash
# Vérifier supervisor
docker compose logs supervisor

# Redémarrer supervisor
docker compose restart supervisor

# Ou consommer manuellement
docker compose exec php bin/console messenger:consume async -vv --limit=10
```

## 📊 Monitoring

### Voir les performances

```bash
# Logs de cache hit/miss
docker compose exec php tail -f var/log/dev.log | grep "Cache"

# Logs de refresh async
docker compose exec php tail -f var/log/dev.log | grep "Microsoft"

# Stats Redis
docker compose exec redis redis-cli INFO stats
```

### Statistiques de cache

```bash
# Nombre de clés en cache
docker compose exec redis redis-cli DBSIZE

# TTL d'une clé spécifique
docker compose exec redis redis-cli TTL "ms_graph:1:calendars"

# Voir le contenu d'une clé
docker compose exec redis redis-cli GET "ms_graph:1:calendars"
```

## 🎯 Tests GrumPHP

Une fois tout installé et fonctionnel :

```bash
# Lancer les tests de qualité
docker compose exec php vendor/bin/grumphp run

# Si PHPStan échoue, lancer séparément
docker compose exec php vendor/bin/phpstan analyse src

# Lancer les tests unitaires
docker compose exec php bin/phpunit
```

## ✅ Checklist finale

- [ ] Redis tourne : `docker compose ps redis`
- [ ] Extension PHP Redis chargée : `docker compose exec php php -m | grep redis`
- [ ] Cache pool configuré : `docker compose exec php bin/console cache:pool:list`
- [ ] Worker Messenger actif : `docker compose ps supervisor`
- [ ] Calendrier charge rapidement : < 100ms sur cache hit
- [ ] Messages async traités : `docker compose exec php bin/console messenger:stats`
- [ ] Tests passent : `docker compose exec php vendor/bin/grumphp run`

## 🎉 Résultat attendu

Après installation complète :

| Action | Temps (avant) | Temps (après) | Gain |
|--------|---------------|---------------|------|
| Calendrier (1er chargement) | 3-8s | 3-8s | - |
| Calendrier (cache hit) | 3-8s | **< 100ms** | **30-80x** |
| Création événement | 2-4s | **< 500ms** | **4-8x** |
| Catégories | 1-3s | **< 50ms** | **20-60x** |

**Le compte Microsoft ne bloque plus l'interface !** 🚀
