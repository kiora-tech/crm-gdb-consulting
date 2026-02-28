# CRM-GDB

![Version](https://img.shields.io/badge/version-0.14.0-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.4-purple.svg)
![Symfony](https://img.shields.io/badge/Symfony-8.0-black.svg)

Un systeme de gestion de la relation client (CRM) moderne developpe avec Symfony 8.0 et PHP 8.4, concu specifiquement pour Kiora et GDB Consulting.

## A propos

CRM-GDB est une application web qui permet de gerer efficacement les clients, les contacts, les contrats d'energie, et les documents. L'application est concue en suivant les principes DRY (Don't Repeat Yourself), SOLID et KISS (Keep It Simple, Stupid).

## Fonctionnalites principales

- **Gestion des clients** : Suivi complet des informations des clients, statuts des prospects et historique
- **Gestion des contrats d'energie** : Suivi des contrats d'electricite et de gaz avec dates d'echeance
- **Gestion documentaire** : Stockage et generation de documents a partir de templates
- **Gestion des utilisateurs** : Administration des utilisateurs avec differents niveaux d'acces
- **Tableau de bord** : Vue d'ensemble des indicateurs cles de performance
- **Import/Export** : Capacite d'importer et d'exporter des donnees au format Excel

## Architecture technique

- **Backend** : Symfony 8.0, PHP 8.4
- **Frontend** : Bootstrap, Stimulus.js, Turbo
- **Base de donnees** : MySQL 8.0
- **Stockage fichiers** : MinIO (S3-compatible) via Flysystem
- **Conteneurisation** : Docker (multi-architecture arm64/amd64)

## Prerequis

- Docker et Docker Compose
- Docker Buildx (pour les builds multi-architecture)
- Make

## Installation

```bash
# Initialiser le projet (build des images Docker)
make init

# Installer Symfony (creation de la BDD, migrations, fixtures)
make install_symfony

# Compiler les assets
make ready
```

## Commandes utiles

| Commande | Description |
|---|---|
| `make build` | Demarrer l'environnement Docker |
| `make php` | Acceder au conteneur PHP |
| `make ready` | Compiler les assets |
| `make reset-db` | Reinitialiser la base de donnees |
| `make reset-test-db` | Reinitialiser la base de donnees de test |
| `make build_app TAG=x.y.z` | Construire et publier les images Docker de production |

## Tests et qualite

```bash
# Lancer tous les tests
docker compose exec php bin/phpunit

# Lancer un test specifique
docker compose exec php bin/phpunit --filter=testName tests/Path/Class.php

# Verifications qualite (PHPStan + PHPUnit)
docker compose exec php vendor/bin/grumphp run
```

## URLs de developpement

| Service | URL |
|---|---|
| Application | http://localhost:8080 |
| MailHog | http://localhost:8025 |
| MySQL | localhost:3306 |
| MinIO Console | http://localhost:9001 |

## Build de production

Voir [README.build.md](./README.build.md) pour le guide complet de build des images Docker.

```bash
make build_app TAG=0.14.0
```

## Documentation

Pour une documentation complete, veuillez consulter le [dossier docs](./docs/).

## Licence

Ce logiciel est sous licence proprietaire. Voir le fichier [LICENSE](LICENSE.md) pour plus de details.

(c) 2025-2026 Kiora & GDB Consulting. Tous droits reserves.
