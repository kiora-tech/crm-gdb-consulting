// Sync client for handling bidirectional synchronization with the server

import { dbManager } from './db-manager.js';

export class SyncClient {
    constructor() {
        this.syncInProgress = false;
        this.lastSyncTime = null;
        this.syncInterval = null;
        this.conflictStrategy = 'server_wins'; // Default strategy
        this.init();
    }

    async init() {
        // Load last sync time from metadata
        this.lastSyncTime = await dbManager.getMetadata('lastSyncTime');
        
        // Setup periodic sync
        this.setupPeriodicSync();
        
        // Listen for online/offline events
        window.addEventListener('online', () => this.onOnline());
        window.addEventListener('offline', () => this.onOffline());
        
        console.log('Sync client initialized', { 
            lastSync: this.lastSyncTime,
            strategy: this.conflictStrategy 
        });
    }

    setupPeriodicSync() {
        // Sync every 5 minutes when online
        this.syncInterval = setInterval(() => {
            if (navigator.onLine && !this.syncInProgress) {
                this.performSync();
            }
        }, 5 * 60 * 1000);
    }

    onOnline() {
        console.log('Connection restored, starting sync...');
        // Delay sync slightly to ensure stable connection
        setTimeout(() => this.performSync(), 2000);
    }

    onOffline() {
        console.log('Connection lost, pausing sync');
    }

    async performSync() {
        if (this.syncInProgress) {
            console.log('Sync already in progress, skipping');
            return;
        }

        if (!navigator.onLine) {
            console.log('Cannot sync while offline');
            return;
        }

        this.syncInProgress = true;
        this.showSyncStatus('syncing');

        try {
            console.log('Starting sync operation...');
            
            // Step 1: Push local changes
            const pushResult = await this.pushChanges();
            console.log('Push completed:', pushResult);
            
            // Step 2: Pull server changes
            const pullResult = await this.pullChanges();
            console.log('Pull completed:', pullResult);
            
            // Step 3: Handle conflicts if any
            if (pushResult.conflicts && pushResult.conflicts.length > 0) {
                await this.handleConflicts(pushResult.conflicts);
            }
            
            // Update last sync time
            this.lastSyncTime = new Date().toISOString();
            await dbManager.setMetadata('lastSyncTime', this.lastSyncTime);
            
            // Clear sync queue
            await this.clearProcessedQueue();
            
            this.showSyncStatus('success', 'Sync completed successfully');
            
            // Dispatch sync complete event
            window.dispatchEvent(new CustomEvent('sync-complete', {
                detail: {
                    pushed: pushResult.pushed,
                    pulled: pullResult.pulled,
                    conflicts: pushResult.conflicts,
                    timestamp: this.lastSyncTime
                }
            }));
            
        } catch (error) {
            console.error('Sync failed:', error);
            this.showSyncStatus('error', 'Sync failed: ' + error.message);
            
            // Dispatch sync error event
            window.dispatchEvent(new CustomEvent('sync-error', {
                detail: { error: error.message }
            }));
            
        } finally {
            this.syncInProgress = false;
        }
    }

    async pushChanges() {
        const syncQueue = await dbManager.getSyncQueue();
        
        if (syncQueue.length === 0) {
            console.log('No changes to push');
            return { pushed: [], conflicts: [] };
        }

        console.log(`Pushing ${syncQueue.length} changes to server`);

        const changes = syncQueue.map(item => ({
            operation: item.operation,
            entity: item.entityType,
            data: item.data,
            clientId: item.clientId,
            timestamp: item.timestamp
        }));

        try {
            const response = await fetch('/api/sync/push', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    changes: changes,
                    conflictStrategy: this.conflictStrategy,
                    clientTime: new Date().toISOString()
                })
            });

            if (!response.ok) {
                throw new Error(`Server responded with ${response.status}`);
            }

            const result = await response.json();
            
            // Process results
            if (result.success) {
                // Update local entities with server IDs
                for (const processed of result.results || []) {
                    await this.updateLocalEntity(processed);
                }
            }

            return {
                pushed: result.results || [],
                conflicts: result.conflicts || [],
                errors: result.errors || []
            };

        } catch (error) {
            console.error('Push failed:', error);
            throw error;
        }
    }

    async pullChanges() {
        console.log('Pulling changes from server...');
        
        try {
            const response = await fetch('/api/sync/pull?' + new URLSearchParams({
                lastSync: this.lastSyncTime || '',
                entities: ['customers', 'energies', 'contacts', 'comments'].join(','),
                limit: '1000'
            }), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error(`Server responded with ${response.status}`);
            }

            const result = await response.json();
            
            // Update local database with pulled data
            const pulled = {
                customers: 0,
                energies: 0,
                contacts: 0,
                comments: 0
            };

            for (const [entityType, entities] of Object.entries(result.data || {})) {
                for (const entity of entities) {
                    await this.saveServerEntity(entityType, entity);
                    pulled[entityType]++;
                }
            }

            console.log('Pulled entities:', pulled);
            
            return { 
                pulled: pulled,
                hasMore: result.has_more || false,
                serverTime: result.server_time
            };

        } catch (error) {
            console.error('Pull failed:', error);
            throw error;
        }
    }

    async handleConflicts(conflicts) {
        console.log(`Handling ${conflicts.length} conflicts`);
        
        for (const conflict of conflicts) {
            console.log('Resolving conflict:', conflict);
            
            // Check if manual resolution is needed
            if (this.conflictStrategy === 'manual') {
                await this.showConflictDialog(conflict);
            } else {
                // Auto-resolve based on strategy
                await this.autoResolveConflict(conflict);
            }
        }
    }

    async autoResolveConflict(conflict) {
        try {
            const response = await fetch('/api/sync/resolve-conflict', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    entity: conflict.entity,
                    entityId: conflict.id,
                    resolution: this.conflictStrategy,
                    clientData: conflict.clientData,
                    serverData: conflict.serverData
                })
            });

            if (!response.ok) {
                throw new Error('Failed to resolve conflict');
            }

            const result = await response.json();
            
            if (result.success) {
                // Update local entity with resolved data
                await this.saveServerEntity(conflict.entity, result.entity);
            }
            
        } catch (error) {
            console.error('Failed to resolve conflict:', error);
        }
    }

    async showConflictDialog(conflict) {
        // Create and show conflict resolution modal
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Resolve Sync Conflict</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>A conflict was detected for ${conflict.entity} (ID: ${conflict.id})</p>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Server Version</h6>
                                <pre>${JSON.stringify(conflict.serverData, null, 2)}</pre>
                            </div>
                            <div class="col-md-6">
                                <h6>Your Version</h6>
                                <pre>${JSON.stringify(conflict.clientData, null, 2)}</pre>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-resolution="server_wins">
                            Use Server Version
                        </button>
                        <button type="button" class="btn btn-primary" data-resolution="client_wins">
                            Use My Version
                        </button>
                        <button type="button" class="btn btn-success" data-resolution="merge">
                            Merge Changes
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Show modal using Bootstrap
        const bsModal = new window.bootstrap.Modal(modal);
        bsModal.show();
        
        // Handle resolution buttons
        return new Promise(resolve => {
            modal.querySelectorAll('[data-resolution]').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const resolution = btn.dataset.resolution;
                    conflict.resolution = resolution;
                    await this.autoResolveConflict(conflict);
                    bsModal.hide();
                    modal.remove();
                    resolve(resolution);
                });
            });
        });
    }

    async updateLocalEntity(processed) {
        const { entity, operation, id, server_id, client_id } = processed;
        
        if (operation === 'create' && server_id && client_id) {
            // Update local entity with server-assigned ID
            const stores = {
                'customers': 'customers',
                'energies': 'energies',
                'contacts': 'contacts',
                'comments': 'comments'
            };
            
            const storeName = stores[entity];
            if (storeName) {
                // Find entity by client ID and update with server ID
                const entities = await dbManager.getAll(storeName, 'clientId', client_id);
                if (entities.length > 0) {
                    const localEntity = entities[0];
                    localEntity.id = server_id;
                    await dbManager.update(storeName, localEntity);
                }
            }
        }
    }

    async saveServerEntity(entityType, entity) {
        const stores = {
            'customers': 'customers',
            'energies': 'energies',
            'contacts': 'contacts',
            'comments': 'comments'
        };
        
        const storeName = stores[entityType];
        if (!storeName) {
            console.warn('Unknown entity type:', entityType);
            return;
        }

        // Mark as synced
        entity.syncedAt = new Date().toISOString();
        entity._offline = false;
        entity._syncStatus = 'synced';
        
        // Save or update in local database
        if (entity.id) {
            const existing = await dbManager.read(storeName, entity.id);
            if (existing) {
                await dbManager.update(storeName, entity);
            } else {
                await dbManager.create(storeName, entity);
            }
        }
    }

    async clearProcessedQueue() {
        // Clear successfully processed items from sync queue
        const queue = await dbManager.getSyncQueue();
        
        for (const item of queue) {
            if (item._processed) {
                await dbManager.removeSyncQueueItem(item.id);
            }
        }
    }

    showSyncStatus(status, message = '') {
        // Create or update sync status indicator
        let statusEl = document.getElementById('sync-status');
        
        if (!statusEl) {
            statusEl = document.createElement('div');
            statusEl.id = 'sync-status';
            statusEl.className = 'sync-status position-fixed bottom-0 end-0 m-3';
            document.body.appendChild(statusEl);
        }
        
        const icons = {
            syncing: 'bi-arrow-repeat spin',
            success: 'bi-check-circle text-success',
            error: 'bi-exclamation-circle text-danger',
            offline: 'bi-wifi-off text-warning'
        };
        
        const messages = {
            syncing: 'Synchronizing...',
            success: message || 'Synchronized',
            error: message || 'Sync failed',
            offline: 'Offline'
        };
        
        statusEl.innerHTML = `
            <div class="badge bg-light text-dark p-2">
                <i class="bi ${icons[status]} me-2"></i>
                <span>${messages[status]}</span>
                ${this.lastSyncTime ? `<small class="ms-2">(${this.formatRelativeTime(this.lastSyncTime)})</small>` : ''}
            </div>
        `;
        
        // Auto-hide success message
        if (status === 'success') {
            setTimeout(() => {
                if (statusEl) {
                    statusEl.style.opacity = '0';
                    setTimeout(() => {
                        if (statusEl) {
                            statusEl.style.opacity = '1';
                            statusEl.innerHTML = '';
                        }
                    }, 300);
                }
            }, 3000);
        }
    }

    formatRelativeTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = Math.floor((now - date) / 1000); // seconds
        
        if (diff < 60) return 'just now';
        if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
        if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
        return `${Math.floor(diff / 86400)}d ago`;
    }

    setConflictStrategy(strategy) {
        const validStrategies = ['server_wins', 'client_wins', 'merge', 'newest_wins', 'manual'];
        
        if (validStrategies.includes(strategy)) {
            this.conflictStrategy = strategy;
            console.log('Conflict strategy set to:', strategy);
        } else {
            console.error('Invalid conflict strategy:', strategy);
        }
    }

    async forceSync() {
        console.log('Forcing sync...');
        return this.performSync();
    }

    async getStatus() {
        const queue = await dbManager.getSyncQueue();
        const unsyncedCustomers = await dbManager.getUnsyncedEntities('customers');
        const unsyncedEnergies = await dbManager.getUnsyncedEntities('energies');
        const unsyncedContacts = await dbManager.getUnsyncedEntities('contacts');
        const unsyncedComments = await dbManager.getUnsyncedEntities('comments');
        
        return {
            isOnline: navigator.onLine,
            syncInProgress: this.syncInProgress,
            lastSync: this.lastSyncTime,
            conflictStrategy: this.conflictStrategy,
            pendingChanges: queue.length,
            unsyncedEntities: {
                customers: unsyncedCustomers.length,
                energies: unsyncedEnergies.length,
                contacts: unsyncedContacts.length,
                comments: unsyncedComments.length
            }
        };
    }

    destroy() {
        if (this.syncInterval) {
            clearInterval(this.syncInterval);
            this.syncInterval = null;
        }
    }
}

// Create and export singleton
export const syncClient = new SyncClient();

// Add to window for debugging
window.syncClient = syncClient;

// Add CSS for sync status
const style = document.createElement('style');
style.textContent = `
    .sync-status .spin {
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    .sync-status {
        z-index: 9999;
        transition: opacity 0.3s;
    }
`;
document.head.appendChild(style);