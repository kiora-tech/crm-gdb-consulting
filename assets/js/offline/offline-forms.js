// Offline-aware form handling for CRM-GDB
// Intercepts form submissions and handles offline storage

import { dbManager } from './db-manager.js';

export class OfflineForms {
    constructor() {
        this.forms = new Map();
        this.pendingSubmissions = [];
        this.isOnline = navigator.onLine;
        this.init();
    }

    init() {
        // Setup event listeners
        this.setupOnlineListener();
        this.attachToForms();
        this.restorePendingSubmissions();
        
        console.log('Offline forms initialized');
    }

    setupOnlineListener() {
        window.addEventListener('online', () => {
            this.isOnline = true;
            this.processPendingSubmissions();
        });

        window.addEventListener('offline', () => {
            this.isOnline = false;
        });
    }

    attachToForms() {
        // Find all forms with data-offline attribute
        const forms = document.querySelectorAll('form[data-offline="true"]');
        
        forms.forEach(form => {
            this.attachToForm(form);
        });

        // Watch for dynamically added forms
        this.observeFormAdditions();
    }

    attachToForm(form) {
        const formId = form.id || this.generateFormId(form);
        
        if (this.forms.has(formId)) {
            return; // Already attached
        }

        // Store form configuration
        this.forms.set(formId, {
            element: form,
            entityType: form.dataset.entity || 'unknown',
            syncStrategy: form.dataset.syncStrategy || 'queue',
        });

        // Intercept form submission
        form.addEventListener('submit', (e) => this.handleFormSubmit(e, formId));
        
        // Add offline indicator
        this.addOfflineIndicator(form);
        
        // Setup autosave if enabled
        if (form.dataset.autosave === 'true') {
            this.setupAutosave(form, formId);
        }
    }

    handleFormSubmit(event, formId) {
        const form = this.forms.get(formId);
        
        if (!form) {
            return;
        }

        // Always prevent default to handle submission ourselves
        event.preventDefault();

        const formData = this.extractFormData(form.element);
        const entityType = form.entityType;

        if (this.isOnline) {
            // Online: submit normally but also cache
            this.submitOnline(form.element, formData, entityType);
        } else {
            // Offline: queue for later submission
            this.submitOffline(form.element, formData, entityType);
        }
    }

    async submitOnline(formElement, formData, entityType) {
        try {
            // Show loading state
            this.setFormLoading(formElement, true);

            // Submit to server
            const response = await fetch(formElement.action, {
                method: formElement.method || 'POST',
                body: this.prepareFormData(formData),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (response.ok) {
                const result = await response.json();
                
                // Cache in IndexedDB for offline access
                await this.cacheFormData(entityType, result.data);
                
                // Show success message
                this.showFormMessage(formElement, 'success', 'Data saved successfully');
                
                // Handle redirect if needed
                if (result.redirect) {
                    window.location.href = result.redirect;
                }
                
                // Clear form if configured
                if (formElement.dataset.clearOnSuccess === 'true') {
                    formElement.reset();
                }
            } else {
                // Server error, queue for retry
                this.queueSubmission(formElement, formData, entityType);
                this.showFormMessage(formElement, 'warning', 'Saved locally, will sync when connection improves');
            }
        } catch (error) {
            console.error('Form submission error:', error);
            
            // Network error, save offline
            this.submitOffline(formElement, formData, entityType);
        } finally {
            this.setFormLoading(formElement, false);
        }
    }

    async submitOffline(formElement, formData, entityType) {
        try {
            // Save to IndexedDB
            const savedData = await this.saveOfflineData(entityType, formData);
            
            // Queue for synchronization
            await this.queueSubmission(formElement, formData, entityType);
            
            // Show offline success message
            this.showFormMessage(
                formElement, 
                'info', 
                'Saved offline. Data will be synchronized when connection is restored.'
            );
            
            // Clear form if configured
            if (formElement.dataset.clearOnSuccess === 'true') {
                formElement.reset();
            }
            
            // Trigger custom event
            formElement.dispatchEvent(new CustomEvent('offline-saved', {
                detail: { entityType, data: savedData }
            }));
            
        } catch (error) {
            console.error('Failed to save offline:', error);
            this.showFormMessage(formElement, 'error', 'Failed to save data offline');
        }
    }

    async saveOfflineData(entityType, formData) {
        // Determine the correct store and method
        const storeMapping = {
            'customer': 'customers',
            'energy': 'energies',
            'contact': 'contacts',
            'comment': 'comments',
        };

        const storeName = storeMapping[entityType] || entityType;
        
        // Add client-side metadata
        const dataToSave = {
            ...formData,
            _offline: true,
            _timestamp: new Date().toISOString(),
            _syncStatus: 'pending',
        };

        // Save to IndexedDB
        if (formData.id) {
            // Update existing
            return await dbManager.update(storeName, dataToSave);
        } else {
            // Create new
            return await dbManager.create(storeName, dataToSave);
        }
    }

    async queueSubmission(formElement, formData, entityType) {
        const submission = {
            url: formElement.action,
            method: formElement.method || 'POST',
            entityType: entityType,
            data: formData,
            timestamp: new Date().toISOString(),
            retryCount: 0,
        };

        // Save to sync queue
        await dbManager.addToSyncQueue(
            entityType,
            formData.id || null,
            formData.id ? 'update' : 'create',
            formData
        );

        // Add to memory queue
        this.pendingSubmissions.push(submission);

        // Store in localStorage for persistence
        this.savePendingSubmissions();
    }

    async processPendingSubmissions() {
        if (!this.isOnline || this.pendingSubmissions.length === 0) {
            return;
        }

        console.log(`Processing ${this.pendingSubmissions.length} pending submissions`);

        const processed = [];
        
        for (const submission of this.pendingSubmissions) {
            try {
                const response = await fetch(submission.url, {
                    method: submission.method,
                    body: this.prepareFormData(submission.data),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-Offline-Submission': 'true',
                    },
                });

                if (response.ok) {
                    processed.push(submission);
                    console.log('Successfully synced offline submission:', submission.entityType);
                } else {
                    submission.retryCount++;
                    
                    if (submission.retryCount > 3) {
                        console.error('Max retries exceeded for submission:', submission);
                        processed.push(submission); // Remove from queue
                    }
                }
            } catch (error) {
                console.error('Failed to process pending submission:', error);
                submission.retryCount++;
            }
        }

        // Remove processed submissions
        this.pendingSubmissions = this.pendingSubmissions.filter(
            sub => !processed.includes(sub)
        );
        
        this.savePendingSubmissions();

        // Show notification if all processed
        if (this.pendingSubmissions.length === 0) {
            this.showNotification('All offline data has been synchronized');
        }
    }

    setupAutosave(form, formId) {
        let autosaveTimer = null;
        const autosaveDelay = parseInt(form.dataset.autosaveDelay) || 30000; // 30 seconds default

        const autosave = () => {
            const formData = this.extractFormData(form);
            const entityType = this.forms.get(formId).entityType;
            
            // Save draft to IndexedDB
            this.saveDraft(formId, entityType, formData);
        };

        // Autosave on input change with debounce
        form.addEventListener('input', () => {
            clearTimeout(autosaveTimer);
            autosaveTimer = setTimeout(autosave, autosaveDelay);
        });

        // Save immediately on blur
        form.addEventListener('blur', autosave, true);
        
        // Restore draft on load
        this.restoreDraft(form, formId);
    }

    async saveDraft(formId, entityType, formData) {
        const draft = {
            formId,
            entityType,
            data: formData,
            timestamp: new Date().toISOString(),
        };

        try {
            await dbManager.setMetadata(`draft_${formId}`, draft);
            this.showFormMessage(
                this.forms.get(formId).element,
                'info',
                'Draft saved',
                2000 // Short duration
            );
        } catch (error) {
            console.error('Failed to save draft:', error);
        }
    }

    async restoreDraft(form, formId) {
        try {
            const draft = await dbManager.getMetadata(`draft_${formId}`);
            
            if (draft && draft.data) {
                // Check if draft is recent (less than 24 hours old)
                const draftAge = Date.now() - new Date(draft.timestamp).getTime();
                const maxAge = 24 * 60 * 60 * 1000; // 24 hours
                
                if (draftAge < maxAge) {
                    // Ask user if they want to restore
                    if (confirm('A draft was found. Would you like to restore it?')) {
                        this.populateForm(form, draft.data);
                        this.showFormMessage(form, 'info', 'Draft restored');
                    } else {
                        // Clear old draft
                        await dbManager.setMetadata(`draft_${formId}`, null);
                    }
                }
            }
        } catch (error) {
            console.error('Failed to restore draft:', error);
        }
    }

    extractFormData(form) {
        const formData = new FormData(form);
        const data = {};
        
        for (const [key, value] of formData.entries()) {
            // Handle multiple values (like checkboxes)
            if (key.endsWith('[]')) {
                const cleanKey = key.slice(0, -2);
                if (!data[cleanKey]) {
                    data[cleanKey] = [];
                }
                data[cleanKey].push(value);
            } else {
                data[key] = value;
            }
        }
        
        return data;
    }

    prepareFormData(data) {
        const formData = new FormData();
        
        for (const [key, value] of Object.entries(data)) {
            if (Array.isArray(value)) {
                value.forEach(v => formData.append(`${key}[]`, v));
            } else if (value !== null && value !== undefined) {
                formData.append(key, value);
            }
        }
        
        return formData;
    }

    populateForm(form, data) {
        for (const [key, value] of Object.entries(data)) {
            const input = form.elements[key];
            
            if (input) {
                if (input.type === 'checkbox' || input.type === 'radio') {
                    input.checked = value === 'on' || value === true || value === input.value;
                } else if (input.tagName === 'SELECT') {
                    // Handle select elements
                    Array.from(input.options).forEach(option => {
                        option.selected = Array.isArray(value) 
                            ? value.includes(option.value)
                            : option.value === value;
                    });
                } else {
                    input.value = value;
                }
                
                // Trigger change event for Stimulus controllers
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
    }

    addOfflineIndicator(form) {
        const indicator = document.createElement('div');
        indicator.className = 'offline-indicator';
        indicator.innerHTML = `
            <span class="status-online" style="display: ${this.isOnline ? 'inline' : 'none'}">
                <i class="bi bi-wifi"></i> Online
            </span>
            <span class="status-offline" style="display: ${this.isOnline ? 'none' : 'inline'}">
                <i class="bi bi-wifi-off"></i> Offline Mode
            </span>
        `;
        
        // Insert at the beginning of the form
        form.insertBefore(indicator, form.firstChild);
        
        // Update indicator on connection change
        window.addEventListener('online', () => {
            indicator.querySelector('.status-online').style.display = 'inline';
            indicator.querySelector('.status-offline').style.display = 'none';
        });
        
        window.addEventListener('offline', () => {
            indicator.querySelector('.status-online').style.display = 'none';
            indicator.querySelector('.status-offline').style.display = 'inline';
        });
    }

    showFormMessage(form, type, message, duration = 5000) {
        // Remove existing messages
        const existingMessage = form.querySelector('.form-message');
        if (existingMessage) {
            existingMessage.remove();
        }

        const messageEl = document.createElement('div');
        messageEl.className = `form-message alert alert-${type} alert-dismissible fade show`;
        messageEl.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        // Insert after the offline indicator or at the beginning
        const indicator = form.querySelector('.offline-indicator');
        if (indicator) {
            indicator.after(messageEl);
        } else {
            form.insertBefore(messageEl, form.firstChild);
        }
        
        // Auto-remove after duration
        if (duration > 0) {
            setTimeout(() => {
                if (messageEl.parentNode) {
                    messageEl.remove();
                }
            }, duration);
        }
    }

    setFormLoading(form, loading) {
        const submitBtn = form.querySelector('[type="submit"]');
        
        if (submitBtn) {
            submitBtn.disabled = loading;
            
            if (loading) {
                submitBtn.dataset.originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
            } else {
                if (submitBtn.dataset.originalText) {
                    submitBtn.innerHTML = submitBtn.dataset.originalText;
                }
            }
        }
        
        // Add/remove loading class to form
        form.classList.toggle('form-loading', loading);
    }

    showNotification(message, type = 'success') {
        // Create toast notification
        const toastHtml = `
            <div class="toast align-items-center text-white bg-${type} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        
        // Get or create toast container
        let toastContainer = document.getElementById('toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toast-container';
            toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
            document.body.appendChild(toastContainer);
        }
        
        // Add toast
        const toastEl = document.createElement('div');
        toastEl.innerHTML = toastHtml;
        const toast = toastEl.firstElementChild;
        toastContainer.appendChild(toast);
        
        // Show toast using Bootstrap
        if (window.bootstrap && window.bootstrap.Toast) {
            const bsToast = new window.bootstrap.Toast(toast);
            bsToast.show();
        }
    }

    observeFormAdditions() {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1) { // Element node
                        if (node.tagName === 'FORM' && node.dataset.offline === 'true') {
                            this.attachToForm(node);
                        }
                        
                        // Also check for forms within added elements
                        const forms = node.querySelectorAll('form[data-offline="true"]');
                        forms.forEach(form => this.attachToForm(form));
                    }
                });
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true,
        });
    }

    generateFormId(form) {
        return 'form_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    savePendingSubmissions() {
        localStorage.setItem('offline_pending_submissions', JSON.stringify(this.pendingSubmissions));
    }

    restorePendingSubmissions() {
        const saved = localStorage.getItem('offline_pending_submissions');
        if (saved) {
            try {
                this.pendingSubmissions = JSON.parse(saved);
                console.log(`Restored ${this.pendingSubmissions.length} pending submissions`);
                
                // Try to process them if online
                if (this.isOnline) {
                    setTimeout(() => this.processPendingSubmissions(), 5000);
                }
            } catch (error) {
                console.error('Failed to restore pending submissions:', error);
                this.pendingSubmissions = [];
            }
        }
    }

    async cacheFormData(entityType, data) {
        try {
            const storeMapping = {
                'customer': 'customers',
                'energy': 'energies',
                'contact': 'contacts',
                'comment': 'comments',
            };

            const storeName = storeMapping[entityType] || entityType;
            
            if (data.id) {
                await dbManager.update(storeName, data);
            } else {
                await dbManager.create(storeName, data);
            }
        } catch (error) {
            console.error('Failed to cache form data:', error);
        }
    }
}

// Initialize and export singleton
export const offlineForms = new OfflineForms();

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.offlineForms = offlineForms;
    });
} else {
    window.offlineForms = offlineForms;
}