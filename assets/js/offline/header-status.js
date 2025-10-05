/**
 * Header Offline Status Indicator Manager
 * Manages the offline/online status indicator in the header
 */

class HeaderStatusIndicator {
    constructor() {
        this.statusBar = null;
        this.isOnline = navigator.onLine;
        this.isSyncing = false;
        this.queueLength = 0;
        this.init();
    }

    init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setup());
        } else {
            this.setup();
        }
    }

    setup() {
        this.statusBar = document.getElementById('offline-status-bar');
        if (!this.statusBar) return;

        // Set up event listeners
        window.addEventListener('online', () => this.handleOnline());
        window.addEventListener('offline', () => this.handleOffline());

        // Listen for sync events
        window.addEventListener('sync-started', () => this.handleSyncStart());
        window.addEventListener('sync-completed', () => this.handleSyncComplete());
        window.addEventListener('sync-queue-updated', (e) => this.handleQueueUpdate(e.detail));

        // Initial update
        this.updateStatus();
    }

    handleOnline() {
        this.isOnline = true;
        this.updateStatus();
        
        // Show success message briefly
        if (this.statusBar) {
            this.statusBar.classList.add('online');
            setTimeout(() => {
                if (this.queueLength === 0 && !this.isSyncing) {
                    this.statusBar.classList.remove('visible');
                }
            }, 3000);
        }
    }

    handleOffline() {
        this.isOnline = false;
        this.updateStatus();
        
        if (this.statusBar) {
            this.statusBar.classList.add('visible');
            this.statusBar.classList.remove('online', 'syncing');
        }
    }

    handleSyncStart() {
        this.isSyncing = true;
        this.updateStatus();
    }

    handleSyncComplete() {
        this.isSyncing = false;
        this.updateStatus();
    }

    handleQueueUpdate(detail) {
        this.queueLength = detail?.count || 0;
        this.updateStatus();
    }

    updateStatus() {
        if (!this.statusBar) return;

        const statusText = this.statusBar.querySelector('.offline-status-text');
        const queueCount = this.statusBar.querySelector('.queue-count');
        const queueInfo = this.statusBar.querySelector('.offline-status-queue');
        const syncBtn = this.statusBar.querySelector('.offline-status-sync-btn');
        const statusIcon = this.statusBar.querySelector('.offline-status-icon i');

        // Update visibility
        if (!this.isOnline || this.queueLength > 0 || this.isSyncing) {
            this.statusBar.classList.add('visible');
        } else {
            // Keep visible for 3 seconds after going back online
            setTimeout(() => {
                if (this.isOnline && this.queueLength === 0 && !this.isSyncing) {
                    this.statusBar.classList.remove('visible');
                }
            }, 3000);
        }

        // Update classes
        this.statusBar.classList.toggle('online', this.isOnline);
        this.statusBar.classList.toggle('syncing', this.isSyncing);

        // Update text
        if (statusText) {
            if (!this.isOnline) {
                statusText.textContent = 'Mode hors ligne';
            } else if (this.isSyncing) {
                statusText.textContent = 'Synchronisation en cours...';
            } else if (this.queueLength > 0) {
                statusText.textContent = 'En ligne - En attente de synchronisation';
            } else {
                statusText.textContent = 'Connexion rétablie';
            }
        }

        // Update icon
        if (statusIcon) {
            if (!this.isOnline) {
                statusIcon.className = 'bi bi-wifi-off';
            } else if (this.isSyncing) {
                statusIcon.className = 'bi bi-arrow-repeat';
            } else {
                statusIcon.className = 'bi bi-wifi';
            }
        }

        // Update queue count
        if (queueCount) {
            queueCount.textContent = this.queueLength;
        }

        // Show/hide queue info
        if (queueInfo) {
            queueInfo.style.display = this.queueLength > 0 ? 'inline-block' : 'none';
        }

        // Show/hide sync button
        if (syncBtn) {
            syncBtn.style.display = (this.isOnline && this.queueLength > 0 && !this.isSyncing) ? 'inline-block' : 'none';
        }
    }

    // Method to manually trigger sync
    triggerSync() {
        if (this.isOnline && !this.isSyncing && window.syncClient) {
            window.syncClient.syncNow();
        }
    }
}

// Initialize the header status indicator
const headerStatusIndicator = new HeaderStatusIndicator();

// Export for global access
window.headerStatusIndicator = headerStatusIndicator;

export default HeaderStatusIndicator;