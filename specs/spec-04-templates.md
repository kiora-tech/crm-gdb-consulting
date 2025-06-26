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
- `siretFormatted` : SIRET avec espaces pour meilleure lisibilité
- `addressFull` : Adresse complète sur une ligne
- `addressMultiline` : Adresse formatée sur plusieurs lignes
- `legalForm` : Forme juridique (SARL, SAS, etc.)
- `capitalSocial` : Capital social de l'entreprise

**Justification** :
- Documents officiels nécessitent ces informations
- Évite la ressaisie manuelle
- Conformité légale des documents générés

#### Sur le Contrat
**À ajouter** :
- `daysUntilEnd` : Nombre de jours avant échéance
- `monthsUntilEnd` : Nombre de mois avant échéance
- `endDateFormatted` : Date de fin formatée en français
- `isExpiringSoon` : Booléen si expire dans moins de 3 mois
- `renewalDeadline` : Date limite pour renouveler sans interruption

**Justification** :
- Facilite la création de courriers de relance pertinents
- Permet des messages adaptés selon l'urgence
- Calculs automatiques évitent les erreurs

### 5. Variables calculées pour les offres

**À ajouter** :
- `${contract.monthlyAmount}` : Montant mensuel estimé
- `${contract.dailyCost}` : Coût journalier
- `${contract.savingsAmount}` : Économie réalisée vs tarif réglementé
- `${contract.savingsPercentage}` : Pourcentage d'économie

**Justification** :
- Arguments commerciaux dans les propositions
- Aide à la décision pour le client
- Comparaisons facilitées

### 6. Impact sur la liste des templates

**Filtrage intelligent des templates** :

Les templates affichés doivent être filtrés selon le contexte :

1. **Si le contrat expire dans moins de 3 mois** :
   - Afficher en priorité : "Proposition de renouvellement", "Comparatif tarifs", "Alerte fin de contrat"
   
2. **Si nouveau client (sans contrat)** :
   - Afficher : "Proposition commerciale", "Présentation services"
   
3. **Si contrat récent (moins de 6 mois)** :
   - Masquer les templates de renouvellement
   - Afficher : "Bilan consommation", "Optimisation"

4. **Selon le type d'énergie** :
   - Filtrer les templates électricité/gaz selon le client

**Justification** :
- Évite les erreurs (envoyer un renouvellement à un nouveau client)
- Guide l'utilisateur vers le bon document
- Gain de temps dans la sélection

### 7. Catégorisation des templates

**Catégories à créer** :
1. **Acquisition** : Nouveaux clients
2. **Renouvellement** : Clients en fin de contrat
3. **Gestion courante** : Suivi, modifications
4. **Administratif** : Autorisations, RGPD
5. **Facturation** : Devis, factures

**Organisation** :
- Icône distinctive par catégorie
- Tri par pertinence selon le contexte client
- Favoris personnels par utilisateur

### 8. Validation et aperçu

**Fonctionnalités nécessaires** :
1. **Aperçu avant génération** :
   - Voir le document avec les vraies données
   - Identifier les variables manquantes en rouge
   - Suggérer des valeurs par défaut

2. **Gestion des données manquantes** :
   - Si contact principal absent → utiliser le premier contact
   - Si date échéance absente → afficher "[Date à définir]"
   - Formulaire pour compléter les données manquantes

3. **Historique de génération** :
   - Qui a généré quoi et quand
   - Possibilité de regénérer avec les mêmes paramètres
   - Archivage automatique

### 9. Templates intelligents

**Adaptations automatiques** :

1. **Selon la période** :
   - Décembre : inclure automatiquement les vœux
   - Été : adapter le ton (période creuse)
   
2. **Selon l'historique client** :
   - Client fidèle : mentionner l'ancienneté
   - Nouveau client : ton plus pédagogique

3. **Selon les performances** :
   - Si économies réalisées : les mettre en avant
   - Si consommation optimisée : le souligner

### 10. Formation et documentation

**Éléments à fournir** :
1. **Guide des variables** :
   - Liste exhaustive avec exemples
   - Cas d'usage pour chaque variable
   - Bonnes pratiques de rédaction

2. **Templates exemples** :
   - Un template par catégorie pré-configuré
   - Commentaires explicatifs inclus
   - Versions Word et Excel

3. **Vidéos tutorielles** :
   - Créer son premier template (5 min)
   - Variables avancées (5 min)
   - Gestion des cas particuliers (3 min)

## Processus de mise en œuvre

### Phase 1 : Préparation des données (1 semaine)
- Audit des données existantes
- Ajout des champs manquants
- Migration des contacts (désigner les principaux)

### Phase 2 : Développement (2-3 semaines)
- Système de templates
- Interface de gestion
- Moteur de génération

### Phase 3 : Formation (1 semaine)
- Création des templates standards
- Formation des utilisateurs
- Documentation

### Phase 4 : Déploiement progressif
- Test avec un groupe pilote
- Ajustements selon retours
- Déploiement général

## Indicateurs de succès

1. **Gain de temps** : -80% sur la génération de documents
2. **Taux d'erreur** : <1% sur les données dans les documents
3. **Adoption** : 100% des utilisateurs après 1 mois
4. **Satisfaction** : Note >4/5 sur l'utilité de l'outil

## Points d'attention

1. **RGPD** : S'assurer que les données personnelles sont utilisées conformément
2. **Archivage** : Tous les documents générés doivent être conservés
3. **Versioning** : Garder l'historique des modifications de templates
4. **Sécurité** : Qui peut créer/modifier/utiliser quels templates