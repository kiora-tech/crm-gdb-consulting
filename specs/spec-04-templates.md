# Spécification Fonctionnelle - Configuration et Utilisation des Templates

## Contexte
Le système de templates permet de générer automatiquement des documents (Word/Excel) pré-remplis avec les données du CRM. L'objectif est de gagner du temps et d'éviter les erreurs de saisie manuelle.

## Besoins identifiés

### 1. Contact principal
**Problème actuel** : Un client peut avoir plusieurs contacts mais les templates ne savent pas lequel utiliser par défaut.

**Solution** : Ajouter la notion de "contact principal" sur chaque fiche client.

**Justification** :
- Évite l'ambiguïté lors de la génération de documents
- Permet d'adresser automatiquement les courriers à la bonne personne
- Facilite les relances et communications automatiques

**Impact** :
- Ajouter un indicateur "principal" sur les contacts
- Un seul contact principal par client
- Interface pour désigner/changer le contact principal
- Utiliser ce contact par défaut dans tous les templates

### 2. Variables de date et heure séparées

**Besoin** : Avoir des variables distinctes pour la date et l'heure actuelles

**Variables à créer** :
- `${date.today}` : Date du jour au format JJ/MM/AAAA
- `${date.todayLong}` : Date longue (ex: "19 juin 2025")
- `${time.now}` : Heure actuelle au format HH:MM
- `${date.dayName}` : Nom du jour (ex: "Jeudi")
- `${date.month}` : Mois en cours (ex: "Juin")
- `${date.year}` : Année en cours (ex: "2025")

**Justification** :
- Flexibilité dans la mise en forme des documents
- Certains documents nécessitent uniquement la date
- D'autres nécessitent l'horodatage complet
- Permet des formulations personnalisées

### 3. Variables utilisateur connecté

**Variables à ajouter** :
- `${user.name}` : Nom complet de l'utilisateur qui génère
- `${user.firstName}` : Prénom seul
- `${user.lastName}` : Nom seul
- `${user.email}` : Email professionnel
- `${user.phone}` : Téléphone professionnel
- `${user.title}` : Fonction/titre (ex: "Conseiller énergie")
- `${user.signature}` : Bloc signature complet

**Justification** :
- Personnalisation automatique des documents selon qui les génère
- Signature appropriée sans intervention manuelle
- Traçabilité (qui a généré quel document)

### 4. Nouvelles données sur les entités

#### Sur le Client
**À ajouter** :
- `addressFull` : Adresse complète sur une ligne
- `addressMultiline` : Adresse formatée sur plusieurs lignes
- `legalForm` : Forme juridique (SARL, SAS, etc.)

**Justification** :
- Documents officiels nécessitent ces informations
- Évite la ressaisie manuelle
- Conformité légale des documents générés

## État actuel et modifications nécessaires

### 1. Entité Contact
**État actuel** :
- Possède : firstName, lastName, email, phone, mobilePhone, address, position
- **Manque** : champ `isPrimary` (boolean) pour identifier le contact principal

**Modifications nécessaires** :
- Ajouter propriété `isPrimary` (boolean, nullable: false, default: false)
- Ajouter méthode dans Customer pour récupérer/définir le contact principal
- **Important** : Garantir qu'il y ait toujours exactement un contact principal par client
- **Logique métier automatique** :
  - Si un client n'a qu'un seul contact, il est automatiquement principal
  - Lors de l'ajout du premier contact à un client, il devient automatiquement principal
  - Lors de la suppression du contact principal, le premier des contacts restants devient automatiquement principal
  - Lors du changement de contact principal, l'ancien perd automatiquement son statut
- **Migration** : Définir automatiquement le premier contact de chaque client comme principal pour éviter au client de devoir le faire manuellement

### 2. Entité User
**État actuel** :
- Possède : email, name, lastName, profilePicture
- **Manque** : phone, title (fonction), firstName séparé, signature

**Modifications nécessaires** :
- Ajouter propriété `firstName` (string, nullable: true)
- Ajouter propriété `phone` (string, nullable: true)
- Ajouter propriété `title` (string, nullable: true) pour la fonction
- Ajouter propriété `signature` (text, nullable: true) pour bloc signature

### 3. Entité Customer
**État actuel** :
- Possède : name, address, siret, leadOrigin, origin, status, etc.
- **Manque** : legalForm, méthodes pour formatter l'adresse

**Modifications nécessaires** :
- Ajouter propriété `legalForm` (string, nullable: true)
- Ajouter méthode `getAddressFull()` : retourne l'adresse sur une ligne
- Ajouter méthode `getAddressMultiline()` : retourne l'adresse formatée sur plusieurs lignes
- Ajouter méthode `getPrimaryContact()` : retourne le contact principal ou null

### 4. Service TemplateProcessor
**Modifications nécessaires** :
- Implémenter les nouvelles variables de date/heure
- Ajouter les variables utilisateur connecté
- Utiliser le contact principal dans les templates
- Supporter les nouvelles propriétés des entités

## Plan d'implémentation

### Phase 1 : Modifications des entités
1. Migration pour ajouter `isPrimary` sur Contact avec initialisation automatique
2. Migration pour ajouter les champs manquants sur User
3. Migration pour ajouter `legalForm` sur Customer
4. Mise à jour des entités avec les nouvelles propriétés et méthodes

### Phase 2 : Logique métier
1. Implémentation de la logique automatique pour maintenir un contact principal
2. Interface de gestion du contact principal (bouton radio ou switch)
3. Méthodes de formatage d'adresse sur Customer
4. Validation côté serveur pour garantir l'intégrité des données

### Phase 3 : Intégration templates
1. Mise à jour du TemplateProcessor
2. Ajout des nouvelles variables
3. Documentation des variables disponibles
4. Tests des templates avec les nouvelles données

## Contraintes techniques

### Contact principal
- Un et un seul contact principal par client (contrainte base de données)
- Gestion automatique pour éviter les états incohérents
- Migration qui initialise les données existantes
- Interface utilisateur intuitive (radio button ou toggle)

### Performance
- Les méthodes de formatage d'adresse doivent être optimisées
- Cache des données utilisateur pour éviter les requêtes multiples
- Lazy loading des relations quand possible