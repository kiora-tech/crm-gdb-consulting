# 🚀 Guide d'installation de la PWA CRM-GDB

## Windows 10/11

### Avec Chrome :
1. Ouvrir Chrome et visiter http://localhost:8080
2. Cliquer sur l'icône ⊕ dans la barre d'adresse
3. Cliquer "Installer"
4. L'app apparaît dans :
   - Menu Démarrer
   - Bureau (raccourci optionnel)
   - Barre des tâches (épinglable)

### Avec Edge :
1. Ouvrir Edge et visiter http://localhost:8080
2. Menu ⋯ → Applications → Installer ce site
3. Ou cliquer l'icône d'installation dans la barre d'adresse
4. L'app s'installe comme une app Windows native

## macOS

### Avec Chrome :
1. Ouvrir Chrome et visiter http://localhost:8080
2. Cliquer sur l'icône ⊕ dans la barre d'adresse
3. Cliquer "Installer"
4. L'app apparaît dans :
   - Launchpad
   - Dossier Applications (Chrome Apps)
   - Dock (épinglable)

### Avec Edge :
1. Ouvrir Edge et visiter http://localhost:8080
2. Menu … → Applications → Installer ce site
3. L'app s'installe et peut être ajoutée au Dock

## Linux

### Avec Chrome/Chromium :
1. Ouvrir Chrome et visiter http://localhost:8080
2. Cliquer sur l'icône ⊕ dans la barre d'adresse
3. Cliquer "Installer"
4. L'app apparaît dans :
   - Menu Applications
   - Peut être épinglée à la barre de favoris

## 📱 Mobile (Android)

1. Ouvrir Chrome mobile
2. Visiter http://localhost:8080 (ou l'IP locale du serveur)
3. Menu ⋮ → "Ajouter à l'écran d'accueil"
4. Nommer l'app et confirmer
5. L'icône apparaît sur l'écran d'accueil

## 📱 Mobile (iOS/iPhone/iPad)

1. Ouvrir Safari
2. Visiter http://localhost:8080 (ou l'IP locale)
3. Bouton Partage ⬆ → "Sur l'écran d'accueil"
4. Nommer l'app et "Ajouter"
5. L'app apparaît comme une vraie app iOS

## ✅ Vérifier que la PWA est installée

### Sur PC :
- L'app s'ouvre dans sa propre fenêtre (sans barre d'adresse)
- Icône dans la barre des tâches/Dock
- Présente dans le menu Démarrer/Launchpad
- chrome://apps (Chrome) ou edge://apps (Edge) liste l'app

### Fonctionnalités après installation :
✅ Lancement comme app native (double-clic)
✅ Alt+Tab / Cmd+Tab pour switcher
✅ Notifications système (si activées)
✅ Mode plein écran possible
✅ Fonctionne hors ligne (données en cache)
✅ Synchronisation au retour en ligne

## 🔧 Dépannage

### L'icône d'installation n'apparaît pas ?
- Vérifier HTTPS ou localhost (requis pour PWA)
- Effacer cache et cookies
- Vérifier la console pour erreurs (F12)
- Le manifest.json doit être valide
- Le Service Worker doit être actif

### Commande pour vérifier :
```javascript
// Dans la console du navigateur (F12)
navigator.serviceWorker.getRegistration().then(reg => {
    console.log('Service Worker:', reg ? 'Installé ✅' : 'Non installé ❌');
});
```

### Désinstaller la PWA :

**Windows/Mac/Linux Chrome :**
- chrome://apps
- Clic droit sur l'app → Supprimer

**Windows/Mac Edge :**
- edge://apps
- Menu ⋯ sur l'app → Désinstaller

**Ou depuis l'app elle-même :**
- Ouvrir la PWA
- Menu ⋯ → Désinstaller CRM-GDB

## 🎯 Avantages de la PWA installée

1. **Lancement rapide** : Icône sur bureau/dock
2. **Mode offline** : Fonctionne sans connexion
3. **Plein écran** : Expérience immersive
4. **Notifications** : Alertes système natives
5. **Performance** : Plus rapide qu'un onglet browser
6. **Mises à jour** : Automatiques et transparentes

## 💡 Conseils d'utilisation

- **Épinglez** l'app à la barre des tâches pour accès rapide
- **Synchronisez** régulièrement en étant connecté
- **Vérifiez** l'indicateur online/offline dans le header
- **Utilisez** le bouton "Synchroniser" si besoin
- **Consultez** l'onglet Application dans DevTools (F12) pour debug