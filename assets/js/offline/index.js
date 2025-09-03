// Main entry point for offline mode functionality
// Initializes all offline components and manages their lifecycle

import { dbManager } from './db-manager.js';
import { offlineForms } from './offline-forms.js';
import { syncClient } from './sync-client.js';

class OfflineMode {
    constructor() {
        this.initialized = false;
        this.components = {
            db: dbManager,
            forms: offlineForms,
            sync: syncClient
        };
    }

    async init() {
        if (this.initialized) {
            console.log('Offline mode already initialized');
            return;
        }

        console.log('Initializing offline mode...');

        try {
            // Initialize IndexedDB
            await this.components.db.init();
            console.log('IndexedDB initialized');

            // Check storage availability
            const storageInfo = await this.checkStorageAvailability();
            if (storageInfo) {
                console.log('Storage info:', storageInfo);
            }

            // Setup global offline/online handlers
            this.setupGlobalHandlers();

            // Add offline mode CSS
            this.addOfflineStyles();

            // Mark as initialized
            this.initialized = true;

            // Dispatch initialization event
            window.dispatchEvent(new CustomEvent('offline-mode-initialized', {
                detail: { components: Object.keys(this.components) }
            }));

            console.log('Offline mode initialized successfully');

            // Perform initial sync if online
            if (navigator.onLine) {
                setTimeout(() => {
                    console.log('Performing initial sync...');
                    this.components.sync.performSync();
                }, 3000);
            }

        } catch (error) {
            console.error('Failed to initialize offline mode:', error);
            throw error;
        }
    }

    setupGlobalHandlers() {
        // Handle browser back/forward with unsaved changes
        window.addEventListener('beforeunload', (e) => {
            if (this.hasUnsavedChanges()) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            }
        });

        // Handle visibility change (tab switching)
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && navigator.onLine) {
                // Sync when tab becomes visible and online
                this.components.sync.performSync();
            }
        });

        // Handle storage quota exceeded
        window.addEventListener('storage', (e) => {
            if (e.key === 'quota-exceeded') {
                this.handleQuotaExceeded();
            }
        });
    }

    async checkStorageAvailability() {
        if (!navigator.storage || !navigator.storage.estimate) {
            console.warn('Storage estimation not available');
            return null;
        }

        try {
            const estimate = await navigator.storage.estimate();
            const percentUsed = (estimate.usage / estimate.quota) * 100;
            
            if (percentUsed > 90) {
                console.warn(`Storage usage high: ${percentUsed.toFixed(2)}%`);
                this.showStorageWarning(percentUsed);
            }

            return {
                quota: estimate.quota,
                usage: estimate.usage,
                percentUsed: percentUsed.toFixed(2)
            };
        } catch (error) {
            console.error('Failed to estimate storage:', error);
            return null;
        }
    }

    showStorageWarning(percentUsed) {
        const warning = document.createElement('div');
        warning.className = 'alert alert-warning alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3';
        warning.style.zIndex = '9999';
        warning.innerHTML = `
            <strong>Storage Warning!</strong> 
            You are using ${percentUsed.toFixed(0)}% of available offline storage. 
            Consider syncing and clearing old data.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(warning);
        
        // Auto-remove after 10 seconds
        setTimeout(() => {
            if (warning.parentNode) {
                warning.remove();
            }
        }, 10000);
    }

    async hasUnsavedChanges() {
        try {
            const status = await this.components.sync.getStatus();
            return status.pendingChanges > 0;
        } catch (error) {
            return false;
        }
    }

    async handleQuotaExceeded() {
        console.error('Storage quota exceeded');
        
        // Try to clear old cached data
        if (window.caches) {
            const cacheNames = await caches.keys();
            for (const cacheName of cacheNames) {
                if (cacheName.includes('dynamic')) {
                    await caches.delete(cacheName);
                    console.log('Cleared cache:', cacheName);
                }
            }
        }

        // Notify user
        this.showNotification(
            'Storage space is running low. Some offline features may be limited.',
            'warning'
        );
    }

    showNotification(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type} border-0`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;

        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            document.body.appendChild(container);
        }

        container.appendChild(toast);

        if (window.bootstrap && window.bootstrap.Toast) {
            const bsToast = new window.bootstrap.Toast(toast);
            bsToast.show();
        }
    }

    addOfflineStyles() {
        const style = document.createElement('style');
        style.textContent = `
            /* Offline mode indicator */
            body.app-offline::before {
                content: "Offline Mode";
                position: fixed;
                top: 10px;
                right: 10px;
                background: #ffc107;
                color: #000;
                padding: 5px 15px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: bold;
                z-index: 10000;
                animation: pulse 2s infinite;
            }

            @keyframes pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.6; }
            }

            /* Offline form styles */
            .offline-enabled-form {
                position: relative;
            }

            .offline-enabled-form.form-loading::after {
                content: "";
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(255, 255, 255, 0.8);
                z-index: 100;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            /* Sync indicator */
            .sync-indicator {
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: white;
                border-radius: 50%;
                width: 50px;
                height: 50px;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 2px 10px rgba(0,0,0,0.2);
                z-index: 1000;
            }

            .sync-indicator.syncing {
                animation: rotate 1s linear infinite;
            }

            @keyframes rotate {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }

            /* Offline banner */
            .offline-banner {
                background: linear-gradient(90deg, #ff6b6b, #feca57);
                color: white;
                padding: 10px;
                text-align: center;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 9999;
                transform: translateY(-100%);
                transition: transform 0.3s ease;
            }

            body.app-offline .offline-banner {
                transform: translateY(0);
            }

            /* Queue badge */
            .queue-badge {
                position: fixed;
                bottom: 70px;
                right: 30px;
                background: #dc3545;
                color: white;
                border-radius: 15px;
                padding: 5px 10px;
                font-size: 12px;
                min-width: 25px;
                text-align: center;
                z-index: 1001;
            }

            .queue-badge:empty {
                display: none;
            }
        `;
        
        document.head.appendChild(style);
    }

    // Public API methods
    async clearAllData() {
        if (!confirm('This will clear all offline data. Are you sure?')) {
            return false;
        }

        try {
            // Clear IndexedDB
            await this.components.db.clearStore('customers');
            await this.components.db.clearStore('energies');
            await this.components.db.clearStore('contacts');
            await this.components.db.clearStore('comments');
            await this.components.db.clearSyncQueue();

            // Clear localStorage
            localStorage.removeItem('offline_pending_submissions');
            localStorage.removeItem('lastSyncTime');

            // Clear caches
            if (window.caches) {
                const cacheNames = await caches.keys();
                await Promise.all(cacheNames.map(name => caches.delete(name)));
            }

            this.showNotification('All offline data cleared successfully', 'success');
            return true;
        } catch (error) {
            console.error('Failed to clear offline data:', error);
            this.showNotification('Failed to clear offline data', 'error');
            return false;
        }
    }

    async getStatistics() {
        const stats = {
            storage: await this.checkStorageAvailability(),
            sync: await this.components.sync.getStatus(),
            database: {
                customers: await this.components.db.getCustomers().then(c => c.length),
                energies: (await this.components.db.getAll('energies')).length,
                contacts: (await this.components.db.getAll('contacts')).length,
                comments: (await this.components.db.getAll('comments')).length,
                syncQueue: (await this.components.db.getSyncQueue()).length
            }
        };

        return stats;
    }

    // Debug utilities
    enableDebugMode() {
        window.offlineDebug = {
            db: this.components.db,
            forms: this.components.forms,
            sync: this.components.sync,
            stats: () => this.getStatistics(),
            clear: () => this.clearAllData()
        };
        
        console.log('Offline debug mode enabled. Access via window.offlineDebug');
    }
}

// Create and initialize singleton instance
const offlineMode = new OfflineMode();

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        offlineMode.init().catch(error => {
            console.error('Failed to initialize offline mode:', error);
        });
    });
} else {
    offlineMode.init().catch(error => {
        console.error('Failed to initialize offline mode:', error);
    });
}

// Export for use in other modules
export { offlineMode as default, offlineMode };

// Expose globally for debugging
window.offlineMode = offlineMode;