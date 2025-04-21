# Les Composants du CRM-GDB

Ce document décrit les principaux composants du CRM-GDB, leur fonctionnement et comment les utiliser.

## Table des matières

1. [Composants Twig](#composants-twig)
2. [Contrôleurs principaux](#contrôleurs-principaux)
3. [Entités principales](#entités-principales)
4. [Services](#services)
5. [Système d'import](#système-dimport)
6. [Génération de documents](#génération-de-documents)

## Composants Twig

Le CRM-GDB utilise intensivement les composants Twig pour créer une interface utilisateur réutilisable et maintenable.

### Button

Un composant pour créer des boutons cohérents dans toute l'application.

```twig
{# Utilisation de base #}
<twig:Button
    theme="primary"
    label="Enregistrer"
    icon="save"
/>

{# Avec un lien #}
<twig:Button
    link="{{ path('app_route') }}"
    theme="secondary"
    label="Retour"
    icon="arrow-left"
/>

{# Bouton de soumission de formulaire #}
<twig:Button
    type="submit"
    theme="success"
    label="Valider"
    icon="check"
/>
```

### Table

Composant pour afficher des données tabulaires avec fonctionnalités de tri et pagination.

```twig
<twig:Table
    :paginator="entities"
    :columns="[
        {field: 'name', label: 'Nom', sortable: true},
        {field: 'email', label: 'Email', sortable: true},
        {field: 'phone', label: 'Téléphone'}
    ]"
    :options="{
        routes: {
            show: 'app_entity_show',
            edit: 'app_entity_edit',
            delete: 'app_entity_delete'
        }
    }"
/>
```

### DeleteButton

Bouton de suppression avec confirmation.

```twig
<twig:DeleteButton
    deleteRoute="app_entity_delete"
    :deleteRouteParams="{'id': entity.id}"
    :entityId="entity.id"
    showLabel="true"
/>
```

### FormActions

Actions standard de formulaire (enregistrer, retour, supprimer).

```twig
<twig:FormActions
    backRoute="app_entity_index"
    :showDelete="true"
    deleteRoute="app_entity_delete"
    :deleteRouteParams="{'id': entity.id}"
    :entityId="entity.id"
    :isAdmin="is_granted('ROLE_ADMIN')"
/>
```

### ClientSearch

Recherche de clients avec autocomplétion.

```twig
<twig:ClientSearch />
```

## Contrôleurs principaux

### BaseCrudController

Controller abstrait qui implémente les opérations CRUD de base.

```php
class EntityController extends BaseCrudController
{
    protected function getEntityClass(): string
    {
        return Entity::class;
    }
    
    // Personnalisation optionnelle
    protected function getColumns(): array
    {
        return [
            ['field' => 'name', 'label' => 'entity.name', 'sortable' => true],
            ['field' => 'email', 'label' => 'entity.email', 'sortable' => true],
        ];
    }
}
```

### CustomerInfoController

Controller pour toutes les entités liées aux clients (contacts, énergies, commentaires, etc.).

```php
class ContactController extends CustomerInfoController
{
    public function getEntityClass(): string
    {
        return Contact::class;
    }
}
```

## Entités principales

### Customer

L'entité centrale qui représente un client ou prospect.

Relations :
- One-to-Many avec Contact
- One-to-Many avec Energy
- One-to-Many avec Comment
- One-to-Many avec Document
- Many-to-One avec User

### Energy

Représente un contrat d'énergie (électricité ou gaz).

Propriétés importantes :
- `type` : Type d'énergie (ELEC ou GAZ)
- `contractEnd` : Date de fin de contrat
- Propriétés spécifiques selon le type (puissance, consommation, etc.)

### Document

Gestion des documents associés à un client.

Fonctionnalités :
- Upload de documents
- Téléchargement
- Génération depuis des templates
- Signature électronique (intégration Yousign)

## Services

### ImportService

Service pour importer des données clients depuis des fichiers Excel.

```php
// Dans un contrôleur
public function upload(Request $request, ImportService $importService): Response
{
    $file = $request->files->get('file');
    $importService->importFromUpload($file, $this->getUser()->getId());
    // ...
}
```

### PaginationService

Service pour paginer les résultats de requêtes.

```php
$query = $repository->createQueryBuilder('e');
$pagination = $paginationService->paginate($query, $request);
```

### TemplateProcessor

Service pour générer des documents à partir de templates.

```php
$document = $templateProcessor->processTemplate($template, $customer);
```

## Système d'import

Le système d'import permet d'importer des données clients depuis des fichiers Excel.

### Processus d'import

1. Le fichier est téléchargé et enregistré temporairement
2. Un message `StartImportMessage` est envoyé à la file d'attente
3. Le processus de traitement divise le fichier en lots
4. Chaque lot est traité par un worker via `ProcessExcelBatchMessage`
5. Les erreurs sont collectées et un rapport d'erreur est généré

### Suivi des erreurs

Le service `ImportErrorTracker` enregistre les erreurs d'import et génère un rapport Excel contenant :
- Les lignes en erreur
- Le message d'erreur
- Le type d'erreur

## Génération de documents

Le système permet de générer des documents à partir de templates Word (.docx) ou Excel (.xlsx).

### Variables disponibles

Les variables suivantes sont disponibles dans les templates :

```
${name}             - Nom du client
${siret}            - Numéro SIRET
${contacts[0].firstName} - Prénom du premier contact
${energies[0].type}      - Type d'énergie du premier contrat
...
```

### Processus de génération

1. Sélection d'un template et d'un client
2. Remplacement des variables dans le template
3. Génération du document final
4. Possibilité de signer électroniquement via Yousign
