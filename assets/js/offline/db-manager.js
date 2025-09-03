// IndexedDB wrapper for CRM-GDB offline functionality
// Handles local data storage and synchronization

export class OfflineDBManager {
    constructor(dbName = 'CRM_GDB_Offline', version = 1) {
        this.dbName = dbName;
        this.version = version;
        this.db = null;
        this.isInitialized = false;
    }

    async init() {
        if (this.isInitialized) {
            return this.db;
        }

        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.version);

            request.onerror = () => {
                console.error('Failed to open IndexedDB:', request.error);
                reject(request.error);
            };

            request.onsuccess = () => {
                this.db = request.result;
                this.isInitialized = true;
                console.log('IndexedDB initialized successfully');
                resolve(this.db);
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                this.createObjectStores(db);
            };
        });
    }

    createObjectStores(db) {
        // Customer store
        if (!db.objectStoreNames.contains('customers')) {
            const customerStore = db.createObjectStore('customers', { keyPath: 'id' });
            customerStore.createIndex('name', 'name', { unique: false });
            customerStore.createIndex('siret', 'siret', { unique: false });
            customerStore.createIndex('syncedAt', 'syncedAt', { unique: false });
            customerStore.createIndex('version', 'version', { unique: false });
            customerStore.createIndex('clientId', 'clientId', { unique: false });
        }

        // Energy store
        if (!db.objectStoreNames.contains('energies')) {
            const energyStore = db.createObjectStore('energies', { keyPath: 'id' });
            energyStore.createIndex('customerId', 'customerId', { unique: false });
            energyStore.createIndex('code', 'code', { unique: false });
            energyStore.createIndex('type', 'type', { unique: false });
            energyStore.createIndex('syncedAt', 'syncedAt', { unique: false });
            energyStore.createIndex('version', 'version', { unique: false });
            energyStore.createIndex('clientId', 'clientId', { unique: false });
        }

        // Contact store
        if (!db.objectStoreNames.contains('contacts')) {
            const contactStore = db.createObjectStore('contacts', { keyPath: 'id' });
            contactStore.createIndex('customerId', 'customerId', { unique: false });
            contactStore.createIndex('email', 'email', { unique: false });
            contactStore.createIndex('firstName', 'firstName', { unique: false });
            contactStore.createIndex('lastName', 'lastName', { unique: false });
            contactStore.createIndex('syncedAt', 'syncedAt', { unique: false });
            contactStore.createIndex('version', 'version', { unique: false });
            contactStore.createIndex('clientId', 'clientId', { unique: false });
        }

        // Comment store
        if (!db.objectStoreNames.contains('comments')) {
            const commentStore = db.createObjectStore('comments', { keyPath: 'id' });
            commentStore.createIndex('customerId', 'customerId', { unique: false });
            commentStore.createIndex('createdAt', 'createdAt', { unique: false });
            commentStore.createIndex('syncedAt', 'syncedAt', { unique: false });
            commentStore.createIndex('version', 'version', { unique: false });
            commentStore.createIndex('clientId', 'clientId', { unique: false });
        }

        // Sync queue store for offline changes
        if (!db.objectStoreNames.contains('syncQueue')) {
            const syncStore = db.createObjectStore('syncQueue', { keyPath: 'id', autoIncrement: true });
            syncStore.createIndex('entityType', 'entityType', { unique: false });
            syncStore.createIndex('entityId', 'entityId', { unique: false });
            syncStore.createIndex('operation', 'operation', { unique: false });
            syncStore.createIndex('timestamp', 'timestamp', { unique: false });
            syncStore.createIndex('clientId', 'clientId', { unique: false });
        }

        // Metadata store for sync information
        if (!db.objectStoreNames.contains('metadata')) {
            const metaStore = db.createObjectStore('metadata', { keyPath: 'key' });
        }

        console.log('IndexedDB object stores created');
    }

    async ensureInit() {
        if (!this.isInitialized) {
            await this.init();
        }
        return this.db;
    }

    // Generic CRUD operations
    async create(storeName, data) {
        await this.ensureInit();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            
            // Add sync metadata
            const now = new Date().toISOString();
            const syncData = {
                ...data,
                syncedAt: null, // Not synced yet
                version: 1,
                clientId: this.generateClientId(),
                createdAt: data.createdAt || now,
                updatedAt: now
            };
            
            const request = store.add(syncData);
            
            request.onerror = () => reject(request.error);
            request.onsuccess = () => resolve(request.result);
        });
    }

    async read(storeName, id) {
        await this.ensureInit();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readonly');
            const store = transaction.objectStore(storeName);
            const request = store.get(id);
            
            request.onerror = () => reject(request.error);
            request.onsuccess = () => resolve(request.result);
        });
    }

    async update(storeName, data) {
        await this.ensureInit();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            
            // Update sync metadata
            const updatedData = {
                ...data,
                syncedAt: null, // Mark as needing sync
                version: (data.version || 0) + 1,
                updatedAt: new Date().toISOString()
            };
            
            const request = store.put(updatedData);
            
            request.onerror = () => reject(request.error);
            request.onsuccess = () => resolve(request.result);
        });
    }

    async delete(storeName, id) {
        await this.ensureInit();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.delete(id);
            
            request.onerror = () => reject(request.error);
            request.onsuccess = () => resolve(request.result);
        });
    }

    async getAll(storeName, indexName = null, query = null) {
        await this.ensureInit();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readonly');
            const store = transaction.objectStore(storeName);
            
            let source = store;
            if (indexName) {
                source = store.index(indexName);
            }
            
            const request = query ? source.getAll(query) : source.getAll();
            
            request.onerror = () => reject(request.error);
            request.onsuccess = () => resolve(request.result);
        });
    }

    // Entity-specific methods
    async getCustomers(limit = null) {
        const customers = await this.getAll('customers');
        return limit ? customers.slice(0, limit) : customers;
    }

    async getCustomerById(id) {
        return this.read('customers', id);
    }

    async createCustomer(customerData) {
        return this.create('customers', customerData);
    }

    async updateCustomer(customerData) {
        return this.update('customers', customerData);
    }

    async getEnergiesByCustomer(customerId) {
        return this.getAll('energies', 'customerId', customerId);
    }

    async createEnergy(energyData) {
        return this.create('energies', energyData);
    }

    async updateEnergy(energyData) {
        return this.update('energies', energyData);
    }

    async getContactsByCustomer(customerId) {
        return this.getAll('contacts', 'customerId', customerId);
    }

    async createContact(contactData) {
        return this.create('contacts', contactData);
    }

    async updateContact(contactData) {
        return this.update('contacts', contactData);
    }

    async getCommentsByCustomer(customerId) {
        return this.getAll('comments', 'customerId', customerId);
    }

    async createComment(commentData) {
        return this.create('comments', commentData);
    }

    async updateComment(commentData) {
        return this.update('comments', commentData);
    }

    // Sync queue operations
    async addToSyncQueue(entityType, entityId, operation, data) {
        await this.ensureInit();
        
        const syncItem = {
            entityType,
            entityId,
            operation, // 'create', 'update', 'delete'
            data,
            timestamp: new Date().toISOString(),
            clientId: this.generateClientId(),
            retryCount: 0
        };
        
        return this.create('syncQueue', syncItem);
    }

    async getSyncQueue() {
        return this.getAll('syncQueue');
    }

    async removeSyncQueueItem(id) {
        return this.delete('syncQueue', id);
    }

    async clearSyncQueue() {
        await this.ensureInit();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['syncQueue'], 'readwrite');
            const store = transaction.objectStore('syncQueue');
            const request = store.clear();
            
            request.onerror = () => reject(request.error);
            request.onsuccess = () => resolve(request.result);
        });
    }

    // Metadata operations
    async setMetadata(key, value) {
        await this.ensureInit();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['metadata'], 'readwrite');
            const store = transaction.objectStore('metadata');
            const request = store.put({ key, value, updatedAt: new Date().toISOString() });
            
            request.onerror = () => reject(request.error);
            request.onsuccess = () => resolve(request.result);
        });
    }

    async getMetadata(key) {
        await this.ensureInit();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['metadata'], 'readonly');
            const store = transaction.objectStore('metadata');
            const request = store.get(key);
            
            request.onerror = () => reject(request.error);
            request.onsuccess = () => resolve(request.result?.value || null);
        });
    }

    // Utility methods
    generateClientId() {
        return 'client_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    async getUnsyncedEntities(storeName) {
        await this.ensureInit();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readonly');
            const store = transaction.objectStore(storeName);
            const index = store.index('syncedAt');
            const request = index.getAll(null); // null values (unsynced)
            
            request.onerror = () => reject(request.error);
            request.onsuccess = () => resolve(request.result);
        });
    }

    async markAsSynced(storeName, id, serverData = {}) {
        await this.ensureInit();
        
        const entity = await this.read(storeName, id);
        if (!entity) return null;
        
        const syncedEntity = {
            ...entity,
            ...serverData,
            syncedAt: new Date().toISOString()
        };
        
        return this.update(storeName, syncedEntity);
    }

    // Bulk operations for initial data sync
    async bulkInsert(storeName, entities) {
        await this.ensureInit();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore('syncQueue');
            
            let completed = 0;
            const total = entities.length;
            
            if (total === 0) {
                resolve([]);
                return;
            }
            
            entities.forEach(entity => {
                const syncedEntity = {
                    ...entity,
                    syncedAt: new Date().toISOString(),
                    version: entity.version || 1,
                    clientId: entity.clientId || this.generateClientId()
                };
                
                const request = store.put(syncedEntity);
                
                request.onerror = () => reject(request.error);
                request.onsuccess = () => {
                    completed++;
                    if (completed === total) {
                        resolve(entities);
                    }
                };
            });
        });
    }

    async clearStore(storeName) {
        await this.ensureInit();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.clear();
            
            request.onerror = () => reject(request.error);
            request.onsuccess = () => resolve(request.result);
        });
    }

    // Database management
    async getStorageUsage() {
        if (!navigator.storage || !navigator.storage.estimate) {
            return null;
        }
        
        try {
            const estimate = await navigator.storage.estimate();
            return {
                quota: estimate.quota,
                usage: estimate.usage,
                usagePercentage: estimate.usage / estimate.quota * 100
            };
        } catch (error) {
            console.error('Failed to get storage estimate:', error);
            return null;
        }
    }

    async close() {
        if (this.db) {
            this.db.close();
            this.db = null;
            this.isInitialized = false;
        }
    }
}

// Singleton instance
export const dbManager = new OfflineDBManager();