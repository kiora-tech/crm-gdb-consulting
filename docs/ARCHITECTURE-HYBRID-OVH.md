# Architecture Hybrid - Résilience avec OVH et Backup 4G

## Contexte

Suite à 2 coupures de fibre rendant l'application indisponible, mise en place d'une architecture hybrid pour assurer la continuité de service.

**Infrastructure actuelle** :
- Serveur local (primary)
- Freebox Pro avec backup 4G automatique
- Branche `feature/offline-mode` avec PWA et Service Workers

**Objectif** : Héberger un serveur OVH en parallèle pour éliminer le point de défaillance unique.

---

## Avantages d'un serveur OVH

- **Haute disponibilité** : datacenter avec SLA > 99,9%
- **Bande passante redondante** : pas de point de défaillance unique
- **Coût raisonnable** : VPS à partir de ~5-10€/mois
- **Backup automatisé** : snapshots inclus

---

## Problèmes potentiels à considérer

### 1. Sécurité & Données personnelles
- Données clients hébergées hors de vos locaux
- Conformité RGPD : assurer que les données restent en UE
- Besoin de chiffrement des backups

### 2. Point de défaillance unique reste possible
- Si OVH a une panne (rare mais arrive : incendie Strasbourg 2021)
- Dépendance à un seul fournisseur

### 3. Coûts cachés
- Bande passante sortante selon usage
- Backups/snapshots supplémentaires
- Monitoring/alerting

### 4. Complexité opérationnelle
- Maintenance serveur Linux (mises à jour sécurité)
- Monitoring à mettre en place
- Gestion des certificats SSL

---

## Solutions alternatives/complémentaires

1. **Hybrid** : serveur principal local + réplication OVH (failover)
2. **Multi-cloud** : OVH + backup sur autre provider (Scaleway, Hetzner)
3. **Connexion fibre redondante** : 2 FAI différents (souvent moins cher long terme)

---

## Architecture Hybrid Recommandée

```
Internet ──┬─→ Fibre principale ──→ Serveur local (primary)
           │                          ↓ réplication async
           └─→ 4G backup ─────────→ OVH (secondary/failover)
```

### Stratégie de basculement

**Mode normal (fibre OK)** :
- Serveur local = primary
- Sync asynchrone vers OVH (réplication données)
- Utilisateurs → serveur local

**Mode dégradé (coupure fibre)** :
- Basculement automatique 4G
- OVH prend le relais pour utilisateurs externes
- App locale fonctionne en mode offline/cache
- Upload différé des modifications

---

## Optimisations pour backup 4G

### Problématique
**Upload 4G = goulot d'étranglement majeur**
- Débit upload limité (~5-10 Mbps vs 100+ Mbps en fibre)
- Latence plus élevée
- Documents/Excel = fichiers lourds

### Optimisations réseau essentielles

#### 1. Cache & Offline-first
✅ Déjà en place dans `feature/offline-mode` :
- Service Workers (`public/sw.js`)
- Sync initial (`assets/js/offline/initial-sync.js`)
- BulkSyncController pour synchro bulk

#### 2. Compression & Delta Sync
- ✅ Compresser uploads (gzip/brotli)
- ✅ Delta sync : envoyer uniquement les changements
- ✅ Queue uploads en background
- ✅ Batch API calls

#### 3. Priorisation uploads
- **Haute priorité** : données clients, commandes
- **Basse priorité** : documents, templates Excel
- **Différé** : fichiers > 5MB si 4G détecté

#### 4. Détection qualité réseau
Implémenter détection fibre vs 4G pour adapter comportement :
- API Network Information
- Mesure latence/bande passante
- Mode dégradé automatique

---

## Points d'attention spécifiques

### Documents & MinIO
- Upload de templates/documents Excel = lourd
- **Solution** : lazy upload (différer si 4G, priorité aux données critiques)
- Stocker localement en attendant retour fibre

### Sync bidirectionnelle
- **Risque** : conflits si modifs locales pendant coupure + modifs sur OVH
- **Besoin** : stratégie de résolution de conflits
  - Timestamp-based
  - Last-write-wins
  - Merge intelligent selon type données

### MinIO réplication
- Configurer réplication MinIO → S3 OVH
- Ou utiliser rsync pour fichiers
- Compression avant upload

---

## Roadmap d'implémentation

### Phase 1 : Setup infrastructure OVH
- [ ] Provisionner VPS OVH (VPS SSD 2 recommandé)
- [ ] Configurer Docker + docker-compose identique
- [ ] Setup MySQL réplication master-slave
- [ ] Configurer MinIO réplication

### Phase 2 : Réplication données
- [ ] Script de sync initial (dump MySQL)
- [ ] Réplication continue (binlog MySQL)
- [ ] Sync fichiers MinIO
- [ ] Monitoring réplication lag

### Phase 3 : Détection et failover
- [ ] Healthcheck serveur local
- [ ] DNS failover automatique (ou load balancer)
- [ ] Détection qualité réseau côté client
- [ ] Mode dégradé automatique si 4G

### Phase 4 : Optimisations 4G
- [ ] Queue uploads background
- [ ] Priorisation par type de données
- [ ] Delta sync pour modifications
- [ ] Compression uploads

### Phase 5 : Gestion conflits
- [ ] Stratégie résolution conflits
- [ ] UI pour afficher/résoudre conflits manuellement
- [ ] Logs conflits pour audit

---

## Monitoring & Alerting

### Métriques à surveiller
- Lag réplication MySQL
- Lag réplication MinIO
- Qualité lien fibre/4G
- Temps réponse serveurs
- Espace disque

### Alertes critiques
- Réplication stoppée > 5min
- Serveur local injoignable
- Bascule sur 4G
- Conflits non résolus

---

## Coûts estimés

### VPS OVH
- **VPS SSD 2** : ~7€/mois (2 vCores, 4GB RAM, 40GB SSD)
- **VPS SSD 3** : ~14€/mois (4 vCores, 8GB RAM, 80GB SSD)

### Services additionnels
- Snapshot backup : ~2€/mois
- Object Storage (alternative MinIO) : ~0.01€/GB/mois
- Bande passante : généralement incluse (quota large)

**Total estimé** : 10-20€/mois selon configuration

---

## Prochaines étapes

1. **Court terme** : Optimiser mode offline actuel pour mieux gérer latence 4G
2. **Moyen terme** : Setup VPS OVH + réplication base
3. **Long terme** : Failover automatique + résolution conflits

---

## Références techniques

- PWA offline mode : `feature/offline-mode` branch
- Service Worker : `public/sw.js`
- Bulk sync : `src/Controller/BulkSyncController.php`
- Initial sync : `assets/js/offline/initial-sync.js`
