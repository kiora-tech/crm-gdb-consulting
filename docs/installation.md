# Guide d'installation

Ce document explique comment installer et configurer l'environnement de développement pour CRM-GDB.

## Prérequis

- Docker
- Docker Compose
- Git
- Make (pour les utilitaires)

## Installation

### 1. Cloner le dépôt

```bash
git clone [URL_DU_DEPOT] crm-gdb
cd crm-gdb
```

### 2. Configuration de l'environnement

Copier le fichier d'environnement d'exemple et personnaliser selon vos besoins :

```bash
cp .env.example .env
```

Copier le fichier de configuration Docker Compose :

```bash
cp compose.override.yaml.dist compose.override.yaml
```

### 3. Installation avec Make

L'application utilise des commandes Make pour simplifier le processus d'installation et de développement :

```bash
# Initialise l'environnement Docker et installe les dépendances
make init

# Installe Symfony et configure la base de données
make install_symfony
```

Cette commande va :
- Construire les images Docker
- Installer les dépendances via Composer
- Créer la base de données
- Exécuter les migrations
- Compiler les assets

### 4. Accéder à l'application

Une fois l'installation terminée, vous pouvez accéder à l'application :

- **Application web :** http://localhost:8080
- **Mailhog (pour tester les emails) :** http://localhost:8025
- **MySQL :** localhost:3306 (accès via les identifiants configurés dans .env)

## Commandes utiles

Le projet contient de nombreuses commandes Make pour faciliter les tâches courantes :

```bash
# Démarrer les conteneurs
make up

# Accéder au conteneur PHP
make php

# Accéder au conteneur Node
make node

# Exécuter les tests
make test

# Mettre à jour Symfony (migrations, etc.)
make update_symfony

# Réinitialiser la base de données
make reset-db

# Charger une base de données externe
make load-external-db

# Compiler les assets
make ready
```

## Structure des répertoires

```
crm-gdb/
├── assets/                 # Assets frontend (JS, CSS)
├── bin/                    # Exécutables Symfony
├── config/                 # Configuration de l'application
├── docker/                 # Configuration Docker
├── docs/                   # Documentation
├── make/                   # Fichiers Make modulaires
├── migrations/             # Migrations de base de données
├── public/                 # Fichiers publics
├── src/                    # Code source de l'application
├── templates/              # Templates Twig
├── tests/                  # Tests automatisés
├── translations/           # Fichiers de traduction
├── var/                    # Données variables (cache, logs)
└── vendor/                 # Dépendances (géré par Composer)
```

## Résolution des problèmes courants

### Problèmes de permissions

Si vous rencontrez des problèmes de permissions sur les répertoires var/cache ou var/log :

```bash
sudo chown -R $(whoami):$(whoami) var
chmod -R 777 var
```

### Problèmes de base de données

Si la base de données n'est pas accessible ou présente des erreurs :

```bash
# Réinitialiser complètement la base de données
make reset-db

# Vérifier les logs de la base de données
docker compose logs database
```

### Container PHP qui ne démarre pas

Vérifiez les logs :

```bash
docker compose logs php
```

Pour plus d'informations, consultez la [documentation complète des composants](./components.md) et l'[architecture du projet](./architecture.md).