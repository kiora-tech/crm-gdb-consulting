// Offline Status Manager - Comprehensive PWA status handling

export class OfflineStatusManager {
    constructor() {
        this.isOnline = navigator.onLine;
        this.statusIndicator = null;
        this.installPrompt = null;
        this.deferredInstallPrompt = null;
        this.syncStatus = {
            isRunning: false,
            queueLength: 0,
            lastSync: null
        };
        
        this.init();
    }

    init() {
        this.createStatusIndicator();
        this.setupEventListeners();
        this.checkPWAInstallability();
        this.updateStatusDisplay();
    }

    createStatusIndicator() {
        // Create main offline status bar
        this.statusIndicator = document.createElement('div');
        this.statusIndicator.id = 'offline-status-indicator';
        this.statusIndicator.className = 'offline-status-indicator';
        this.statusIndicator.innerHTML = `
            <div class="status-content">
                <div class="connection-status">
                    <i class="connection-icon"></i>
                    <span class="status-text">Checking connection...</span>
                    <div class="sync-info">
                        <span class="sync-text"></span>
                        <button class="sync-button btn btn-sm btn-outline-light d-none" type="button">
                            <i class="bi bi-arrow-clockwise"></i> Sync Now
                        </button>
                    </div>
                </div>
                <div class="status-actions">
                    <button class="install-button btn btn-sm btn-success d-none" type="button">
                        <i class="bi bi-download"></i> Install App
                    </button>
                    <button class="close-status btn btn-sm btn-link text-light" type="button">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            </div>
        `;

        // Add CSS styles
        this.addStatusStyles();

        // Insert into page
        document.body.appendChild(this.statusIndicator);

        // Get references to interactive elements
        this.syncButton = this.statusIndicator.querySelector('.sync-button');
        this.installButton = this.statusIndicator.querySelector('.install-button');
        this.closeButton = this.statusIndicator.querySelector('.close-status');

        // Add event listeners
        this.syncButton.addEventListener('click', () => this.forceSyncAction());
        this.installButton.addEventListener('click', () => this.promptInstall());
        this.closeButton.addEventListener('click', () => this.hideStatusBar());
    }

    addStatusStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .offline-status-indicator {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
                color: white;
                padding: 0.5rem;
                z-index: 9999;
                transform: translateY(-100%);
                transition: transform 0.3s ease;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }

            .offline-status-indicator.show {
                transform: translateY(0);
            }

            .offline-status-indicator.online {
                background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            }

            .offline-status-indicator.offline {
                background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
            }

            .offline-status-indicator.syncing {
                background: linear-gradient(135deg, #007bff 0%, #6f42c1 100%);
            }

            .status-content {
                display: flex;
                align-items: center;
                justify-content: space-between;
                max-width: 1200px;
                margin: 0 auto;
                font-size: 0.875rem;
            }

            .connection-status {
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }

            .connection-icon {
                font-size: 1.1em;
            }

            .connection-icon:before {
                content: "\\F1C6"; /* bi-wifi-off */
                font-family: "Bootstrap Icons";
            }

            .offline-status-indicator.online .connection-icon:before {
                content: "\\F1C5"; /* bi-wifi */
            }

            .sync-info {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                margin-left: 1rem;
            }

            .sync-text {
                opacity: 0.9;
                font-size: 0.8em;
            }

            .status-actions {
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }

            .close-status {
                padding: 0.25rem;
                opacity: 0.7;
            }

            .close-status:hover {
                opacity: 1;
            }

            /* Pulse animation for syncing */
            @keyframes pulse {
                0% { opacity: 1; }
                50% { opacity: 0.7; }
                100% { opacity: 1; }
            }

            .offline-status-indicator.syncing .connection-icon {
                animation: pulse 1.5s ease-in-out infinite;
            }

            /* Responsive design */
            @media (max-width: 768px) {
                .status-content {
                    flex-direction: column;
                    gap: 0.5rem;
                }
                
                .connection-status {
                    flex-direction: column;
                    text-align: center;
                    gap: 0.25rem;
                }
                
                .sync-info {
                    margin-left: 0;
                }
            }
        `;
        document.head.appendChild(style);
    }

    setupEventListeners() {
        // Online/offline events
        window.addEventListener('online', () => {
            this.isOnline = true;
            this.onOnline();
        });

        window.addEventListener('offline', () => {
            this.isOnline = false;
            this.onOffline();
        });

        // Service Worker messages
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('message', (event) => {
                this.handleServiceWorkerMessage(event);
            });
        }

        // PWA install prompt
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            this.deferredInstallPrompt = e;
            this.showInstallButton();
        });

        // PWA installed
        window.addEventListener('appinstalled', () => {
            console.log('PWA was installed');
            this.hideInstallButton();
            this.showInstalledNotification();
        });
    }

    handleServiceWorkerMessage(event) {
        const { data } = event;
        
        if (!data) return;

        switch(data.type) {
            case 'SYNC_COMPLETE':
                this.onSyncComplete(data);
                break;
            case 'SYNC_ERROR':
                this.onSyncError(data);
                break;
            case 'SYNC_STARTED':
                this.onSyncStarted(data);
                break;
        }
    }

    onOnline() {
        console.log('Status Manager: App is online');
        this.updateStatusDisplay();
        this.showStatusBar('online', 'Connected to internet', 3000);
        
        // Trigger sync when coming back online
        this.requestBackgroundSync();
    }

    onOffline() {
        console.log('Status Manager: App is offline');
        this.updateStatusDisplay();
        this.showStatusBar('offline', 'Working offline - changes will sync when connected');
    }

    onSyncStarted(data) {
        this.syncStatus.isRunning = true;
        this.syncStatus.queueLength = data.queueLength || 0;
        this.updateSyncDisplay();
    }

    onSyncComplete(data) {
        this.syncStatus.isRunning = false;
        this.syncStatus.lastSync = new Date();
        this.syncStatus.queueLength = Math.max(0, this.syncStatus.queueLength - (data.synced || 0));
        
        this.updateSyncDisplay();
        
        if (data.synced > 0) {
            this.showStatusBar('online', `Synced ${data.synced} items successfully`, 3000);
        }
    }

    onSyncError(data) {
        this.syncStatus.isRunning = false;
        this.updateSyncDisplay();
        this.showStatusBar('offline', `Sync failed: ${data.error}`, 5000);
    }

    updateStatusDisplay() {
        const statusText = this.statusIndicator.querySelector('.status-text');
        
        if (this.isOnline) {
            statusText.textContent = 'Connected';
            this.statusIndicator.classList.add('online');
            this.statusIndicator.classList.remove('offline');
        } else {
            statusText.textContent = 'Offline';
            this.statusIndicator.classList.add('offline');
            this.statusIndicator.classList.remove('online');
        }
        
        this.updateSyncDisplay();
    }

    updateSyncDisplay() {
        const syncText = this.statusIndicator.querySelector('.sync-text');
        const syncButton = this.statusIndicator.querySelector('.sync-button');
        
        if (this.syncStatus.isRunning) {
            syncText.textContent = 'Syncing...';
            syncButton.classList.add('d-none');
            this.statusIndicator.classList.add('syncing');
        } else if (this.syncStatus.queueLength > 0) {
            syncText.textContent = `${this.syncStatus.queueLength} items pending`;
            syncButton.classList.toggle('d-none', !this.isOnline);
            this.statusIndicator.classList.remove('syncing');
        } else if (this.syncStatus.lastSync) {
            const timeSince = this.getTimeSince(this.syncStatus.lastSync);
            syncText.textContent = `Last sync: ${timeSince}`;
            syncButton.classList.add('d-none');
            this.statusIndicator.classList.remove('syncing');
        } else {
            syncText.textContent = '';
            syncButton.classList.add('d-none');
            this.statusIndicator.classList.remove('syncing');
        }
    }

    showStatusBar(type, message, autoHide = 0) {
        const statusText = this.statusIndicator.querySelector('.status-text');
        statusText.textContent = message;
        
        this.statusIndicator.className = `offline-status-indicator show ${type}`;
        
        if (autoHide > 0) {
            setTimeout(() => {
                if (!this.syncStatus.isRunning && this.syncStatus.queueLength === 0) {
                    this.hideStatusBar();
                }
            }, autoHide);
        }
    }

    hideStatusBar() {
        this.statusIndicator.classList.remove('show');
    }

    async forceSyncAction() {
        if (!this.isOnline) return;
        
        this.syncStatus.isRunning = true;
        this.updateSyncDisplay();
        
        try {
            if (window.swManager) {
                await window.swManager.triggerSync();
            }
        } catch (error) {
            console.error('Failed to trigger sync:', error);
            this.syncStatus.isRunning = false;
            this.updateSyncDisplay();
        }
    }

    async requestBackgroundSync() {
        if (window.swManager) {
            await window.swManager.triggerSync();
        }
    }

    checkPWAInstallability() {
        // Check if already installed
        if (window.matchMedia('(display-mode: standalone)').matches || 
            window.navigator.standalone) {
            console.log('PWA is already installed');
            return;
        }

        // Check if install prompt is available
        if (this.deferredInstallPrompt) {
            this.showInstallButton();
        }
    }

    showInstallButton() {
        this.installButton.classList.remove('d-none');
    }

    hideInstallButton() {
        this.installButton.classList.add('d-none');
    }

    async promptInstall() {
        if (!this.deferredInstallPrompt) return;
        
        try {
            this.deferredInstallPrompt.prompt();
            const { outcome } = await this.deferredInstallPrompt.userChoice;
            
            if (outcome === 'accepted') {
                console.log('User accepted the install prompt');
            } else {
                console.log('User dismissed the install prompt');
            }
            
            this.deferredInstallPrompt = null;
            this.hideInstallButton();
        } catch (error) {
            console.error('Install prompt failed:', error);
        }
    }

    showInstalledNotification() {
        this.showStatusBar('online', 'App installed successfully!', 3000);
    }

    getTimeSince(date) {
        const seconds = Math.floor((new Date() - date) / 1000);
        
        if (seconds < 60) return 'just now';
        if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
        if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
        return `${Math.floor(seconds / 86400)}d ago`;
    }
}

// Initialize and expose globally
const offlineStatusManager = new OfflineStatusManager();
window.offlineStatusManager = offlineStatusManager;

export default offlineStatusManager;