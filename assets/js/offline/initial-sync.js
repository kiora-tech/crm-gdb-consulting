/**
 * Initial Sync Manager
 * Handles the initial synchronization of all data to IndexedDB with progress tracking
 */

class InitialSyncManager {
    constructor() {
        this.isRunning = false;
        this.progress = 0;
        this.totalItems = 0;
        this.currentItems = 0;
        this.modal = null;
        this.init();
    }

    init() {
        // Create sync button in the interface
        this.createSyncButton();
        
        // Check if initial sync has been done
        this.checkInitialSyncStatus();
    }

    createSyncButton() {
        // Add button to header or sidebar
        const button = document.createElement('button');
        button.id = 'initial-sync-btn';
        button.className = 'btn btn-primary btn-sm';
        button.innerHTML = '<i class="bi bi-cloud-download"></i> Synchroniser tout';
        button.style.cssText = 'position: fixed; bottom: 20px; right: 20px; z-index: 1050;';
        button.onclick = () => this.startInitialSync();
        
        // Only show if not already synced
        if (!this.hasInitialSync()) {
            document.body.appendChild(button);
        }
    }

    hasInitialSync() {
        return localStorage.getItem('crm_initial_sync_completed') === 'true';
    }

    async checkInitialSyncStatus() {
        const hasSync = this.hasInitialSync();
        if (!hasSync) {
            // Show notification to user
            this.showSyncNotification();
        }
    }

    showSyncNotification() {
        const notification = document.createElement('div');
        notification.className = 'alert alert-info alert-dismissible fade show';
        notification.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 1040; max-width: 400px;';
        notification.innerHTML = `
            <h6><i class="bi bi-info-circle"></i> Mode hors ligne disponible</h6>
            <p>Pour utiliser l'application hors ligne, synchronisez d'abord toutes les données.</p>
            <button type="button" class="btn btn-sm btn-primary" onclick="window.initialSyncManager.startInitialSync()">
                <i class="bi bi-cloud-download"></i> Synchroniser maintenant
            </button>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        document.body.appendChild(notification);
    }

    async startInitialSync() {
        if (this.isRunning) {
            alert('Une synchronisation est déjà en cours');
            return;
        }

        this.isRunning = true;
        this.progress = 0;
        this.currentItems = 0;
        
        // Create and show progress modal
        this.createProgressModal();
        this.showModal();

        try {
            // Step 1: Get data count
            await this.updateProgress('Préparation...', 0);
            const counts = await this.getDataCounts();
            this.totalItems = counts.total;

            // Step 2: Sync customers
            await this.updateProgress('Synchronisation des clients...', 5);
            await this.syncCustomers(counts.customers);

            // Step 3: Sync energies
            await this.updateProgress('Synchronisation des énergies...', 30);
            await this.syncEnergies(counts.energies);

            // Step 4: Sync contacts
            await this.updateProgress('Synchronisation des contacts...', 50);
            await this.syncContacts(counts.contacts);

            // Step 5: Sync comments
            await this.updateProgress('Synchronisation des commentaires...', 70);
            await this.syncComments(counts.comments);

            // Step 6: Sync documents metadata
            await this.updateProgress('Synchronisation des métadonnées documents...', 85);
            await this.syncDocuments(counts.documents);

            // Complete
            await this.updateProgress('Synchronisation terminée !', 100);
            localStorage.setItem('crm_initial_sync_completed', 'true');
            localStorage.setItem('crm_initial_sync_date', new Date().toISOString());

            // Success message
            setTimeout(() => {
                this.hideModal();
                this.showSuccessMessage();
                
                // Remove sync button
                const btn = document.getElementById('initial-sync-btn');
                if (btn) btn.remove();
            }, 1500);

        } catch (error) {
            console.error('Initial sync failed:', error);
            this.showErrorMessage(error.message);
        } finally {
            this.isRunning = false;
        }
    }

    async getDataCounts() {
        const response = await fetch('/api/sync/counts');
        if (!response.ok) throw new Error('Failed to get data counts');
        return response.json();
    }

    async syncCustomers(count) {
        const batchSize = 50;
        const batches = Math.ceil(count / batchSize);
        
        for (let i = 0; i < batches; i++) {
            const offset = i * batchSize;
            const response = await fetch(`/api/sync/customers?limit=${batchSize}&offset=${offset}`);
            if (!response.ok) throw new Error('Failed to sync customers');
            
            const customers = await response.json();
            await this.saveToIndexedDB('customers', customers);
            
            this.currentItems += customers.length;
            const progress = 5 + (25 * (i + 1) / batches);
            await this.updateProgress(`Clients: ${this.currentItems}/${count}`, progress);
        }
    }

    async syncEnergies(count) {
        const batchSize = 100;
        const batches = Math.ceil(count / batchSize);
        
        for (let i = 0; i < batches; i++) {
            const offset = i * batchSize;
            const response = await fetch(`/api/sync/energies?limit=${batchSize}&offset=${offset}`);
            if (!response.ok) throw new Error('Failed to sync energies');
            
            const energies = await response.json();
            await this.saveToIndexedDB('energies', energies);
            
            const progress = 30 + (20 * (i + 1) / batches);
            await this.updateProgress(`Énergies: ${i * batchSize + energies.length}/${count}`, progress);
        }
    }

    async syncContacts(count) {
        const batchSize = 100;
        const batches = Math.ceil(count / batchSize);
        
        for (let i = 0; i < batches; i++) {
            const offset = i * batchSize;
            const response = await fetch(`/api/sync/contacts?limit=${batchSize}&offset=${offset}`);
            if (!response.ok) throw new Error('Failed to sync contacts');
            
            const contacts = await response.json();
            await this.saveToIndexedDB('contacts', contacts);
            
            const progress = 50 + (20 * (i + 1) / batches);
            await this.updateProgress(`Contacts: ${i * batchSize + contacts.length}/${count}`, progress);
        }
    }

    async syncComments(count) {
        const batchSize = 200;
        const batches = Math.ceil(count / batchSize);
        
        for (let i = 0; i < batches; i++) {
            const offset = i * batchSize;
            const response = await fetch(`/api/sync/comments?limit=${batchSize}&offset=${offset}`);
            if (!response.ok) throw new Error('Failed to sync comments');
            
            const comments = await response.json();
            await this.saveToIndexedDB('comments', comments);
            
            const progress = 70 + (15 * (i + 1) / batches);
            await this.updateProgress(`Commentaires: ${i * batchSize + comments.length}/${count}`, progress);
        }
    }

    async syncDocuments(count) {
        // Only sync metadata, not actual files
        const response = await fetch('/api/sync/documents-metadata');
        if (!response.ok) throw new Error('Failed to sync documents metadata');
        
        const documents = await response.json();
        await this.saveToIndexedDB('documents', documents);
        
        await this.updateProgress(`Documents: ${documents.length} métadonnées`, 95);
    }

    async saveToIndexedDB(storeName, data) {
        if (!window.crmDB) {
            // Initialize DB if not ready
            window.crmDB = new CRMOfflineDB();
            await window.crmDB.init();
        }

        // Batch save to IndexedDB
        const db = await window.crmDB.getDB();
        const tx = db.transaction([storeName], 'readwrite');
        const store = tx.objectStore(storeName);

        for (const item of data) {
            await store.put(item);
        }

        await tx.complete;
    }

    createProgressModal() {
        this.modal = document.createElement('div');
        this.modal.className = 'modal fade';
        this.modal.id = 'syncProgressModal';
        this.modal.setAttribute('data-bs-backdrop', 'static');
        this.modal.setAttribute('data-bs-keyboard', 'false');
        this.modal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-cloud-download"></i> Synchronisation initiale
                        </h5>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">Téléchargement de toutes les données pour le mode hors ligne...</p>
                        <div class="progress mb-3" style="height: 25px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                 role="progressbar" 
                                 style="width: 0%"
                                 aria-valuenow="0" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                                0%
                            </div>
                        </div>
                        <p class="sync-status text-muted">Préparation...</p>
                        <div class="alert alert-info mt-3">
                            <i class="bi bi-info-circle"></i> 
                            Cette opération peut prendre quelques minutes selon le volume de données.
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(this.modal);
    }

    showModal() {
        const modalElement = document.getElementById('syncProgressModal');
        if (modalElement && window.bootstrap) {
            const modal = new window.bootstrap.Modal(modalElement);
            modal.show();
        }
    }

    hideModal() {
        const modalElement = document.getElementById('syncProgressModal');
        if (modalElement && window.bootstrap) {
            const modal = window.bootstrap.Modal.getInstance(modalElement);
            if (modal) {
                modal.hide();
            }
        }
        // Remove modal from DOM after animation
        setTimeout(() => {
            if (this.modal && this.modal.parentNode) {
                this.modal.parentNode.removeChild(this.modal);
            }
        }, 500);
    }

    async updateProgress(status, percentage) {
        const progressBar = document.querySelector('#syncProgressModal .progress-bar');
        const statusText = document.querySelector('#syncProgressModal .sync-status');
        
        if (progressBar) {
            progressBar.style.width = percentage + '%';
            progressBar.setAttribute('aria-valuenow', percentage);
            progressBar.textContent = Math.round(percentage) + '%';
        }
        
        if (statusText) {
            statusText.textContent = status;
        }

        // Small delay for visual feedback
        await new Promise(resolve => setTimeout(resolve, 100));
    }

    showSuccessMessage() {
        const alert = document.createElement('div');
        alert.className = 'alert alert-success alert-dismissible fade show';
        alert.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 1050;';
        alert.innerHTML = `
            <h6><i class="bi bi-check-circle"></i> Synchronisation réussie !</h6>
            <p>Toutes les données ont été téléchargées. L'application peut maintenant fonctionner hors ligne.</p>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(alert);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.parentNode.removeChild(alert);
            }
        }, 5000);
    }

    showErrorMessage(error) {
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger alert-dismissible fade show';
        alert.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 1050;';
        alert.innerHTML = `
            <h6><i class="bi bi-exclamation-triangle"></i> Erreur de synchronisation</h6>
            <p>${error}</p>
            <button type="button" class="btn btn-sm btn-danger" onclick="window.initialSyncManager.startInitialSync()">
                Réessayer
            </button>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(alert);
        
        this.hideModal();
    }
}

// Initialize the sync manager
const initialSyncManager = new InitialSyncManager();
window.initialSyncManager = initialSyncManager;

export default InitialSyncManager;