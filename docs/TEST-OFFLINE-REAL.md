# Test réel du mode offline - Guide complet

## Comprendre le mode offline

### ❌ Ce qui NE fonctionne PAS :
- Accéder à http://localhost:8080 pour la première fois sans connexion
- Taper l'URL dans un nouvel onglet sans connexion
- Accéder depuis un nouveau navigateur sans connexion

### ✅ Ce qui FONCTIONNE :
- PWA installée sur l'appareil
- Page déjà ouverte dans un onglet
- Application en cache après première visite

## Méthode 1 : Installer la PWA (Recommandé)

### Sur Desktop (Chrome/Edge) :
1. Visiter http://localhost:8080 avec connexion active
2. Cliquer sur l'icône d'installation dans la barre d'adresse (⊕ ou ⬇)
3. Installer l'application
4. L'app s'ouvre dans sa propre fenêtre
5. Couper internet ou Docker → L'app continue de fonctionner !

### Sur Mobile :
1. Visiter le site sur Chrome mobile
2. Menu ⋮ → "Ajouter à l'écran d'accueil"
3. L'app s'installe comme une vraie app
4. Mode avion → L'app fonctionne toujours !

## Méthode 2 : Garder l'onglet ouvert

1. Ouvrir http://localhost:8080
2. Naviguer dans plusieurs pages (mise en cache)
3. NE PAS fermer l'onglet
4. Couper internet/Docker
5. Continuer à naviguer dans les pages en cache

## Méthode 3 : Test avec Chrome DevTools

1. Ouvrir l'application
2. F12 → Network → Throttling → Offline
3. Simule une perte de connexion
4. L'app utilise le cache

## Architecture du mode offline

```
┌─────────────────────────────────────┐
│         Première visite             │
│  (Nécessite connexion internet)     │
└─────────────┬───────────────────────┘
              │
              ▼
┌─────────────────────────────────────┐
│    Service Worker s'installe        │
│    + Cache les ressources           │
│    + Stocke données dans IndexedDB  │
└─────────────┬───────────────────────┘
              │
              ▼
┌─────────────────────────────────────┐
│      Visites suivantes              │
│  ┌─────────────────────────────┐   │
│  │ Si PWA installée:            │   │
│  │ → Lance depuis le cache      │   │
│  │ → Fonctionne sans internet   │   │
│  └─────────────────────────────┘   │
│  ┌─────────────────────────────┐   │
│  │ Si page en cache:            │   │
│  │ → Service Worker intercepte  │   │
│  │ → Sert depuis le cache       │   │
│  └─────────────────────────────┘   │
└─────────────────────────────────────┘
```

## Vérifier l'installation du Service Worker

### Chrome DevTools :
1. F12 → Application → Service Workers
2. Vérifier : "Activated and is running"
3. Cache Storage → Voir les ressources en cache
4. IndexedDB → Voir les données stockées

### Test de fonctionnement :
```javascript
// Dans la console du navigateur
navigator.serviceWorker.ready.then(() => {
    console.log('Service Worker prêt !');
});

// Vérifier le cache
caches.keys().then(keys => console.log('Caches:', keys));

// Vérifier IndexedDB
indexedDB.databases().then(dbs => console.log('Databases:', dbs));
```

## Scénarios d'usage réels

### Scénario 1 : Commercial terrain
1. Installe la PWA sur sa tablette
2. Synchronise les données clients le matin (WiFi bureau)
3. Part en rendez-vous sans connexion
4. Consulte/modifie les données clients
5. Retour au bureau → Synchronisation automatique

### Scénario 2 : Coupure réseau temporaire
1. Travaille sur l'application au bureau
2. Coupure internet (problème FAI)
3. Continue à travailler (données en cache)
4. Internet revient → Synchronisation transparente

### Scénario 3 : Zone sans couverture
1. PWA installée sur smartphone
2. Déplacement en zone rurale (pas de 4G)
3. Consultation des données clients
4. Ajout de commentaires (en queue)
5. Retour en zone couverte → Sync automatique

## Limitations actuelles

- **Première visite** : Nécessite toujours une connexion
- **Authentification** : Le login nécessite une connexion
- **Documents** : Téléchargement nécessite connexion
- **Nouvelles données** : Seules les données déjà consultées sont en cache

## Commandes utiles pour tester

```bash
# Voir les logs du Service Worker
docker-compose exec php tail -f var/log/dev.log

# Vérifier que le manifest est accessible
curl http://localhost:8080/manifest.json

# Vérifier le Service Worker
curl http://localhost:8080/sw.js

# Nettoyer le cache du navigateur (reset complet)
# Chrome : chrome://settings/content/all
# Supprimer les données de localhost:8080
```

## Indicateurs de succès

✅ Bannière d'installation PWA apparaît
✅ Service Worker "Activated and running"
✅ Cache Storage contient les ressources
✅ IndexedDB contient les données
✅ Indicateur online/offline fonctionne
✅ Navigation possible sans connexion
✅ Formulaires fonctionnent offline
✅ Synchronisation au retour en ligne