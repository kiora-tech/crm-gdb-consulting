# Guide de test du mode offline

## Option 1: Simuler une perte de connexion API (Recommandé)

```bash
# Arrêter seulement PHP et la base de données (garde nginx actif)
docker-compose stop php database

# L'application reste accessible mais l'API ne répond plus
# Le Service Worker intercepte les requêtes et utilise le cache
```

## Option 2: Utiliser Chrome DevTools

1. Ouvrir l'application : http://localhost:8080
2. Ouvrir Chrome DevTools (F12)
3. Aller dans l'onglet "Network"
4. Activer "Offline" dans le menu throttling
5. L'app passe en mode offline mais reste accessible

## Option 3: Modifier le Service Worker pour forcer le mode offline

Dans `/public/sw.js`, ajouter temporairement :
```javascript
// Force offline mode for testing
self.addEventListener('fetch', (event) => {
    // Simuler offline pour les requêtes API
    if (event.request.url.includes('/api/')) {
        event.respondWith(
            new Response('', {
                status: 503,
                statusText: 'Service Unavailable'
            })
        );
        return;
    }
    // Comportement normal pour les autres requêtes
});
```

## Option 4: Couper la connexion internet (garde le serveur local)

1. Désactiver le WiFi/Ethernet de votre machine
2. L'application locale reste accessible via localhost
3. Les requêtes externes échouent

## Ce qui fonctionne en mode offline

✅ Navigation dans les pages déjà visitées (cache HTML)
✅ Affichage des données déjà chargées (IndexedDB)
✅ Création/modification de données (mise en queue)
✅ Assets statiques (CSS, JS, images)
✅ Indicateur visuel du statut

## Ce qui ne fonctionne PAS en mode offline

❌ Première visite (pas encore en cache)
❌ Téléchargement de documents
❌ Authentification (login/logout)
❌ Données jamais consultées auparavant

## Vérifier que le Service Worker fonctionne

1. Chrome DevTools > Application > Service Workers
2. Vérifier que le SW est "Activated and running"
3. Onglet Cache Storage pour voir les ressources en cache
4. Onglet IndexedDB pour voir les données stockées