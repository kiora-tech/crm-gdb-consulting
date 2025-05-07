# Guide de build des images Docker pour la production

Ce guide explique comment construire les images Docker du projet CRM GDB Consulting pour la production sans affecter votre environnement de développement local.

## Problèmes résolus

1. **Préservation de l'environnement local** : Les dépendances de développement ne sont plus supprimées de votre environnement de travail pendant le build.

2. **Stabilité des images de base** : Le système utilise une version fixe des images de base (0.4.1) pour la construction des images finales.

## Prérequis

- Docker installé et configuré
- Docker Buildx pour les builds multi-architecture
- Accès au registre Docker (`registry.kiora.tech`)

## Système de build à deux niveaux

### 1. Images de base (déjà construites, version 0.4.1)

Les images de base contiennent l'environnement PHP configuré mais pas le code de l'application :
- `registry.kiora.tech/kiora/crm-gdb_php_base:0.4.1`
- `registry.kiora.tech/kiora/crm-gdb_php_build:0.4.1`

Ces images sont fixes et ne doivent normalement pas être reconstruites. Si vous devez les reconstruire pour une raison exceptionnelle :

```bash
make docker_publish IMAGE=php TAG=0.4.1
```

### 2. Construction des images de l'application

Pour construire les images finales avec votre code source :

```bash
make build_app TAG=x.y.z
```

Cette commande va :
1. Créer un environnement temporaire sans toucher à votre environnement de développement
2. Utiliser les images de base en version 0.4.1
3. Construire et publier les images finales avec votre code :
   - `registry.kiora.tech/kiora/crm-gdb_php:x.y.z`
   - `registry.kiora.tech/kiora/crm-gdb_php:x.y.z-supervisor`
   - `registry.kiora.tech/kiora/crm-gdb_nginx:x.y.z`
4. Mettre à jour les références dans compose.yaml

## Fonctionnement du script de build

1. **Isolation** : Création d'un dossier temporaire pour isoler le processus de build de votre environnement local.

2. **Vérification des images de base** : Le script vérifie si les images de base en version 0.4.1 sont disponibles et les télécharge si nécessaire.

3. **Installation des dépendances** : Installation des dépendances de production dans le dossier temporaire.

4. **Compilation des assets** : Compilation des assets dans le dossier temporaire.

5. **Construction des images finales** : Construction et publication des images finales avec votre code.

6. **Mise à jour du fichier compose.yaml** : Mise à jour des références aux images dans votre fichier compose.yaml.

7. **Nettoyage** : Suppression du dossier temporaire.

## Avantages

- **Préservation** : Votre environnement de développement reste intact, avec toutes ses dépendances
- **Rapidité** : Le processus évite de reconstruire les images de base à chaque fois
- **Stabilité** : Utilisation d'une version fixe et stable des images de base
- **Simplicité** : Un seul paramètre à spécifier (le tag de version pour vos images finales)

## Diagnostic

Si le build échoue avec une erreur concernant les images de base, vérifiez qu'elles existent :

```bash
docker image ls | grep crm-gdb_php_base
```

Si elles n'existent pas, vous pouvez les reconstruire avec :

```bash
make docker_publish IMAGE=php TAG=0.4.1
```