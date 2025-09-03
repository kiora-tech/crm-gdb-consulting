// Service Worker Manager - Handles registration and communication with SW

export class ServiceWorkerManager {
    constructor() {
        this.swRegistration = null;
        this.isOnline = navigator.onLine;
        this.setupEventListeners();
    }

    async init() {
        if (!this.isServiceWorkerSupported()) {
            console.warn('Service Workers are not supported in this browser');
            return false;
        }

        try {
            await this.registerServiceWorker();
            this.setupPeriodicSync();
            return true;
        } catch (error) {
            console.error('Failed to initialize Service Worker:', error);
            return false;
        }
    }

    isServiceWorkerSupported() {
        return 'serviceWorker' in navigator;
    }

    async registerServiceWorker() {
        try {
            const registration = await navigator.serviceWorker.register('/sw.js', {
                scope: '/'
            });

            this.swRegistration = registration;

            console.log('Service Worker registered successfully:', registration.scope);

            // Handle updates
            registration.addEventListener('updatefound', () => {
                const newWorker = registration.installing;
                if (newWorker) {
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed') {
                            if (navigator.serviceWorker.controller) {
                                this.showUpdateAvailable();
                            } else {
                                console.log('Service Worker installed for the first time');
                            }
                        }
                    });
                }
            });

            // Listen for controlling worker changes
            navigator.serviceWorker.addEventListener('controllerchange', () => {
                console.log('New Service Worker took control');
                window.location.reload();
            });

            return registration;
        } catch (error) {
            console.error('Service Worker registration failed:', error);
            throw error;
        }
    }

    setupEventListeners() {
        // Online/offline status
        window.addEventListener('online', () => {
            this.isOnline = true;
            this.onOnline();
        });

        window.addEventListener('offline', () => {
            this.isOnline = false;
            this.onOffline();
        });

        // Before unload - trigger background sync if needed
        window.addEventListener('beforeunload', () => {
            if (this.swRegistration && this.swRegistration.sync) {
                this.swRegistration.sync.register('background-sync');
            }
        });
    }

    onOnline() {
        console.log('App is online');
        this.updateConnectionStatus(true);
        this.triggerSync();
        
        // Show online indicator
        this.showConnectionStatus('online', 'Connected to internet');
    }

    onOffline() {
        console.log('App is offline');
        this.updateConnectionStatus(false);
        
        // Show offline indicator
        this.showConnectionStatus('offline', 'Working offline - changes will sync when connected');
    }

    updateConnectionStatus(isOnline) {
        document.body.classList.toggle('app-offline', !isOnline);
        document.body.classList.toggle('app-online', isOnline);
    }

    showConnectionStatus(status, message) {
        // Remove existing status messages
        const existingStatus = document.querySelector('.connection-status');
        if (existingStatus) {
            existingStatus.remove();
        }

        // Create status element
        const statusEl = document.createElement('div');
        statusEl.className = `connection-status alert alert-${status === 'online' ? 'success' : 'warning'} alert-dismissible fade show`;
        statusEl.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="bi bi-${status === 'online' ? 'wifi' : 'wifi-off'} me-2"></i>
                <span>${message}</span>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;

        // Insert into page
        const container = document.querySelector('.container') || document.body;
        container.insertBefore(statusEl, container.firstChild);

        // Auto-hide after 3 seconds for online status
        if (status === 'online') {
            setTimeout(() => {
                if (statusEl && statusEl.parentNode) {
                    statusEl.remove();
                }
            }, 3000);
        }
    }

    showUpdateAvailable() {
        const updateEl = document.createElement('div');
        updateEl.className = 'update-available alert alert-info alert-dismissible fade show';
        updateEl.innerHTML = `
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <i class="bi bi-download me-2"></i>
                    <span>A new version is available!</span>
                </div>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-primary me-2" onclick="window.swManager.updateApp()">
                        Update Now
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        `;

        const container = document.querySelector('.container') || document.body;
        container.insertBefore(updateEl, container.firstChild);
    }

    async updateApp() {
        if (this.swRegistration && this.swRegistration.waiting) {
            // Tell the waiting service worker to skip waiting
            this.swRegistration.waiting.postMessage({ type: 'SKIP_WAITING' });
        }
    }

    async triggerSync() {
        if (this.swRegistration && 'sync' in this.swRegistration) {
            try {
                await this.swRegistration.sync.register('background-sync');
                console.log('Background sync registered');
            } catch (error) {
                console.error('Background sync registration failed:', error);
            }
        }
    }

    setupPeriodicSync() {
        // Trigger sync every 5 minutes when online
        setInterval(() => {
            if (this.isOnline) {
                this.triggerSync();
            }
        }, 5 * 60 * 1000); // 5 minutes
    }

    async clearCache() {
        if (this.swRegistration) {
            try {
                const messageChannel = new MessageChannel();
                
                const promise = new Promise((resolve) => {
                    messageChannel.port1.onmessage = (event) => {
                        resolve(event.data);
                    };
                });

                this.swRegistration.active?.postMessage(
                    { type: 'CLEAR_CACHE' }, 
                    [messageChannel.port2]
                );

                const result = await promise;
                console.log('Cache cleared:', result);
                return result.success;
            } catch (error) {
                console.error('Failed to clear cache:', error);
                return false;
            }
        }
        return false;
    }

    async getCacheInfo() {
        if (this.swRegistration) {
            try {
                const messageChannel = new MessageChannel();
                
                const promise = new Promise((resolve) => {
                    messageChannel.port1.onmessage = (event) => {
                        resolve(event.data);
                    };
                });

                this.swRegistration.active?.postMessage(
                    { type: 'GET_VERSION' }, 
                    [messageChannel.port2]
                );

                return await promise;
            } catch (error) {
                console.error('Failed to get cache info:', error);
                return null;
            }
        }
        return null;
    }

    isOnlineMode() {
        return this.isOnline;
    }

    getRegistration() {
        return this.swRegistration;
    }
}

// Initialize and expose globally
const swManager = new ServiceWorkerManager();
window.swManager = swManager;

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', async () => {
    const initialized = await swManager.init();
    if (initialized) {
        console.log('Offline mode enabled');
    } else {
        console.log('Running in online-only mode');
    }
});

export default swManager;