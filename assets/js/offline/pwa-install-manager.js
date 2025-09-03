// PWA Installation Manager - Handles app installation prompts and updates

export class PWAInstallManager {
    constructor() {
        this.deferredPrompt = null;
        this.isInstalled = false;
        this.updateAvailable = false;
        this.newWorker = null;
        
        this.init();
    }

    init() {
        this.checkInstallationStatus();
        this.setupEventListeners();
        this.checkForUpdates();
    }

    checkInstallationStatus() {
        // Check if running as PWA
        this.isInstalled = window.matchMedia('(display-mode: standalone)').matches || 
                          window.navigator.standalone === true;
        
        if (this.isInstalled) {
            console.log('PWA Install Manager: App is installed');
            this.handleInstalledState();
        }
    }

    setupEventListeners() {
        // Install prompt event
        window.addEventListener('beforeinstallprompt', (e) => {
            console.log('PWA Install Manager: Install prompt available');
            e.preventDefault();
            this.deferredPrompt = e;
            this.showInstallPrompt();
        });

        // App installed event
        window.addEventListener('appinstalled', () => {
            console.log('PWA Install Manager: App was installed');
            this.isInstalled = true;
            this.deferredPrompt = null;
            this.hideInstallPrompt();
            this.showInstallSuccessMessage();
        });

        // Service worker update event
        if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
            navigator.serviceWorker.addEventListener('message', (event) => {
                this.handleServiceWorkerMessage(event);
            });
        }
    }

    handleServiceWorkerMessage(event) {
        const { data } = event;
        
        if (!data) return;

        switch(data.type) {
            case 'UPDATE_AVAILABLE':
                this.handleUpdateAvailable(data);
                break;
            case 'UPDATE_INSTALLED':
                this.handleUpdateInstalled(data);
                break;
        }
    }

    showInstallPrompt() {
        if (this.isInstalled || !this.deferredPrompt) return;

        // Create install banner
        const installBanner = this.createInstallBanner();
        document.body.appendChild(installBanner);

        // Auto-hide after 10 seconds if not interacted with
        setTimeout(() => {
            if (installBanner && installBanner.parentNode) {
                this.hideInstallBanner(installBanner);
            }
        }, 10000);
    }

    createInstallBanner() {
        const banner = document.createElement('div');
        banner.id = 'pwa-install-banner';
        banner.className = 'pwa-install-banner';
        banner.innerHTML = `
            <div class="install-content">
                <div class="install-icon">
                    <img src="/icons/pwa-64x64.png" alt="CRM-GDB" width="48" height="48">
                </div>
                <div class="install-text">
                    <h6 class="install-title">Install CRM-GDB</h6>
                    <p class="install-description">Install the app for a better experience with offline access</p>
                </div>
                <div class="install-actions">
                    <button class="btn btn-primary btn-sm install-btn">
                        <i class="bi bi-download"></i> Install
                    </button>
                    <button class="btn btn-link btn-sm text-muted dismiss-btn">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            </div>
        `;

        // Add styles
        this.addInstallBannerStyles();

        // Add event listeners
        const installBtn = banner.querySelector('.install-btn');
        const dismissBtn = banner.querySelector('.dismiss-btn');

        installBtn.addEventListener('click', () => this.handleInstallClick(banner));
        dismissBtn.addEventListener('click', () => this.hideInstallBanner(banner));

        return banner;
    }

    addInstallBannerStyles() {
        if (document.getElementById('pwa-install-styles')) return;

        const style = document.createElement('style');
        style.id = 'pwa-install-styles';
        style.textContent = `
            .pwa-install-banner {
                position: fixed;
                bottom: 1rem;
                left: 1rem;
                right: 1rem;
                max-width: 400px;
                margin: 0 auto;
                background: white;
                border-radius: 0.5rem;
                box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                border: 1px solid #dee2e6;
                z-index: 1050;
                animation: slideUp 0.3s ease-out;
            }

            @keyframes slideUp {
                from {
                    transform: translateY(100%);
                    opacity: 0;
                }
                to {
                    transform: translateY(0);
                    opacity: 1;
                }
            }

            .install-content {
                display: flex;
                align-items: center;
                padding: 1rem;
                gap: 1rem;
            }

            .install-icon img {
                border-radius: 0.375rem;
            }

            .install-text {
                flex: 1;
            }

            .install-title {
                margin: 0 0 0.25rem 0;
                font-size: 1rem;
                font-weight: 600;
                color: #212529;
            }

            .install-description {
                margin: 0;
                font-size: 0.875rem;
                color: #6c757d;
                line-height: 1.4;
            }

            .install-actions {
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }

            .dismiss-btn {
                padding: 0.25rem;
            }

            /* Mobile responsive */
            @media (max-width: 576px) {
                .pwa-install-banner {
                    left: 0.5rem;
                    right: 0.5rem;
                }
                
                .install-content {
                    padding: 0.75rem;
                    gap: 0.75rem;
                }
                
                .install-title {
                    font-size: 0.9rem;
                }
                
                .install-description {
                    font-size: 0.8rem;
                }
            }
        `;
        document.head.appendChild(style);
    }

    async handleInstallClick(banner) {
        if (!this.deferredPrompt) return;

        try {
            // Show the install prompt
            this.deferredPrompt.prompt();

            // Wait for the user to respond to the prompt
            const { outcome } = await this.deferredPrompt.userChoice;

            if (outcome === 'accepted') {
                console.log('PWA Install Manager: User accepted the install prompt');
                this.trackInstallAccepted();
            } else {
                console.log('PWA Install Manager: User dismissed the install prompt');
                this.trackInstallDismissed();
            }

            // Clear the deferredPrompt
            this.deferredPrompt = null;
            this.hideInstallBanner(banner);
        } catch (error) {
            console.error('PWA Install Manager: Install failed:', error);
            this.showInstallErrorMessage();
        }
    }

    hideInstallBanner(banner) {
        if (banner && banner.parentNode) {
            banner.style.animation = 'slideDown 0.3s ease-in forwards';
            setTimeout(() => {
                if (banner.parentNode) {
                    banner.parentNode.removeChild(banner);
                }
            }, 300);
        }
    }

    hideInstallPrompt() {
        const banner = document.getElementById('pwa-install-banner');
        if (banner) {
            this.hideInstallBanner(banner);
        }
    }

    showInstallSuccessMessage() {
        this.showMessage('success', 'App installed successfully!', 'The CRM-GDB app is now available on your home screen.');
    }

    showInstallErrorMessage() {
        this.showMessage('error', 'Installation failed', 'There was a problem installing the app. Please try again.');
    }

    handleInstalledState() {
        // App is already installed, check for updates
        this.checkForUpdates();
    }

    async checkForUpdates() {
        if (!('serviceWorker' in navigator)) return;

        try {
            const registration = await navigator.serviceWorker.getRegistration();
            if (!registration) return;

            // Check for waiting service worker
            if (registration.waiting) {
                this.handleUpdateAvailable({ newWorker: registration.waiting });
            }

            // Listen for new installations
            registration.addEventListener('updatefound', () => {
                const newWorker = registration.installing;
                if (newWorker) {
                    this.newWorker = newWorker;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            this.handleUpdateAvailable({ newWorker });
                        }
                    });
                }
            });
        } catch (error) {
            console.error('PWA Install Manager: Update check failed:', error);
        }
    }

    handleUpdateAvailable(data) {
        console.log('PWA Install Manager: Update available');
        this.updateAvailable = true;
        this.newWorker = data.newWorker;
        this.showUpdatePrompt();
    }

    handleUpdateInstalled(data) {
        console.log('PWA Install Manager: Update installed');
        this.showUpdateSuccessMessage();
    }

    showUpdatePrompt() {
        const updateBanner = this.createUpdateBanner();
        document.body.appendChild(updateBanner);
    }

    createUpdateBanner() {
        const banner = document.createElement('div');
        banner.id = 'pwa-update-banner';
        banner.className = 'pwa-update-banner';
        banner.innerHTML = `
            <div class="update-content">
                <div class="update-icon">
                    <i class="bi bi-arrow-up-circle text-primary" style="font-size: 2rem;"></i>
                </div>
                <div class="update-text">
                    <h6 class="update-title">Update Available</h6>
                    <p class="update-description">A new version of CRM-GDB is ready to install</p>
                </div>
                <div class="update-actions">
                    <button class="btn btn-primary btn-sm update-btn">
                        <i class="bi bi-arrow-clockwise"></i> Update Now
                    </button>
                    <button class="btn btn-link btn-sm text-muted dismiss-update-btn">
                        Later
                    </button>
                </div>
            </div>
        `;

        // Add styles for update banner
        this.addUpdateBannerStyles();

        // Add event listeners
        const updateBtn = banner.querySelector('.update-btn');
        const dismissBtn = banner.querySelector('.dismiss-update-btn');

        updateBtn.addEventListener('click', () => this.handleUpdateClick(banner));
        dismissBtn.addEventListener('click', () => this.hideUpdateBanner(banner));

        return banner;
    }

    addUpdateBannerStyles() {
        if (document.getElementById('pwa-update-styles')) return;

        const style = document.createElement('style');
        style.id = 'pwa-update-styles';
        style.textContent = `
            .pwa-update-banner {
                position: fixed;
                top: 1rem;
                left: 1rem;
                right: 1rem;
                max-width: 400px;
                margin: 0 auto;
                background: #f8f9fa;
                border-radius: 0.5rem;
                box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                border: 1px solid #007bff;
                z-index: 1060;
                animation: slideDown 0.3s ease-out;
            }

            @keyframes slideDown {
                from {
                    transform: translateY(-100%);
                    opacity: 0;
                }
                to {
                    transform: translateY(0);
                    opacity: 1;
                }
            }

            .update-content {
                display: flex;
                align-items: center;
                padding: 1rem;
                gap: 1rem;
            }

            .update-text {
                flex: 1;
            }

            .update-title {
                margin: 0 0 0.25rem 0;
                font-size: 1rem;
                font-weight: 600;
                color: #212529;
            }

            .update-description {
                margin: 0;
                font-size: 0.875rem;
                color: #6c757d;
                line-height: 1.4;
            }

            .update-actions {
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }
        `;
        document.head.appendChild(style);
    }

    async handleUpdateClick(banner) {
        if (!this.newWorker) return;

        try {
            // Tell the new service worker to skip waiting
            this.newWorker.postMessage({ type: 'SKIP_WAITING' });
            
            // Listen for the controller change
            navigator.serviceWorker.addEventListener('controllerchange', () => {
                window.location.reload();
            });

            this.hideUpdateBanner(banner);
            this.showMessage('info', 'Updating...', 'The app will refresh automatically.');
        } catch (error) {
            console.error('PWA Install Manager: Update failed:', error);
            this.showMessage('error', 'Update failed', 'Please refresh the page manually.');
        }
    }

    hideUpdateBanner(banner) {
        if (banner && banner.parentNode) {
            banner.style.animation = 'slideUp 0.3s ease-in forwards';
            setTimeout(() => {
                if (banner.parentNode) {
                    banner.parentNode.removeChild(banner);
                }
            }, 300);
        }
    }

    showUpdateSuccessMessage() {
        this.showMessage('success', 'Update successful!', 'The app has been updated to the latest version.');
    }

    showMessage(type, title, message, duration = 4000) {
        // Create toast-style message
        const toast = document.createElement('div');
        toast.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
        toast.style.cssText = `
            top: 1rem;
            right: 1rem;
            z-index: 1070;
            max-width: 300px;
            animation: slideInRight 0.3s ease-out;
        `;
        toast.innerHTML = `
            <strong>${title}</strong><br>
            <small>${message}</small>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        document.body.appendChild(toast);

        // Auto-remove after duration
        setTimeout(() => {
            if (toast && toast.parentNode) {
                toast.remove();
            }
        }, duration);
    }

    // Analytics/tracking methods
    trackInstallAccepted() {
        console.log('Analytics: PWA install accepted');
        // Send to analytics service if needed
    }

    trackInstallDismissed() {
        console.log('Analytics: PWA install dismissed');
        // Send to analytics service if needed
    }

    // Public API methods
    isAppInstalled() {
        return this.isInstalled;
    }

    hasUpdateAvailable() {
        return this.updateAvailable;
    }

    canInstall() {
        return !!this.deferredPrompt;
    }
}

// Initialize and expose globally
const pwaInstallManager = new PWAInstallManager();
window.pwaInstallManager = pwaInstallManager;

export default pwaInstallManager;