import { Controller } from '@hotwired/stimulus';
import { Modal } from 'bootstrap';

export default class extends Controller {
    static targets = [
        'dropZone',
        'overlay',
        'form',
        'modal',
        'fileContainer',
        'modalContent',
        'documentList'
    ];

    connect() {
        // Initialize properties
        this._droppedFile = null;
        
        // Initialize modal when controller connects
        if (this.hasModalTarget) {
            this.modal = new Modal(this.modalTarget);
            // Nettoyer l'overlay de la modal précédente
            document.body.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
            document.body.classList.remove('modal-open');
            
            // Add modal hidden event listener to clean up
            this.modalTarget.addEventListener('hidden.bs.modal', this.onModalHidden.bind(this));
        }
    }

    disconnect() {
        // Clean up when controller disconnects
        if (this.hasModalTarget && this.modal) {
            this.modalTarget.removeEventListener('hidden.bs.modal', this.onModalHidden.bind(this));
            
            // Try to dispose modal if possible
            if (typeof this.modal.dispose === 'function') {
                this.modal.dispose();
            }
        }
        
        document.body.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        document.body.classList.remove('modal-open');
    }
    
    // Method to handle modal hide event
    onModalHidden() {
        // Reset form when modal is hidden
        if (this.hasFormTarget) {
            this.formTarget.reset();
        }
        
        // Remove file display but preserve the file input element
        if (this.hasFileContainerTarget) {
            // Get all alert boxes inside the container (file displays) and remove them
            this.fileContainerTarget.querySelectorAll('.alert').forEach(el => el.remove());
            
            // Make sure we're not removing the file input itself
            const fileInput = this.fileContainerTarget.querySelector('input[type="file"]');
            if (fileInput && fileInput.files.length > 0) {
                // Clear the file selection but don't remove the input
                try {
                    fileInput.value = ''; // This works for most browsers
                } catch (e) {
                    console.log('Error clearing file input value');
                }
            }
        }
        
        // Clear any stored dropped file
        this._droppedFile = null;
    }

    dragOver(event) {
        event.preventDefault();
        this.overlayTarget.classList.remove('d-none');
    }

    dragLeave(event) {
        event.preventDefault();
        if (!event.currentTarget.contains(event.relatedTarget)) {
            this.overlayTarget.classList.add('d-none');
        }
    }

    drop(event) {
        event.preventDefault();
        this.overlayTarget.classList.add('d-none');

        const files = event.dataTransfer.files;
        if (files.length > 0) {
            this.handleDroppedFile(files[0]);
        }
    }

    openModal() {
        // Reset the form and clear any previous file
        if (this.hasFormTarget) {
            this.formTarget.reset();
            
            // Clear file alerts but preserve the input element
            if (this.hasFileContainerTarget) {
                // Remove any file display alerts but keep the input element
                this.fileContainerTarget.querySelectorAll('.alert').forEach(el => el.remove());
                
                // Find the file input
                const fileInput = this.formTarget.querySelector('input[type="file"]');
                if (fileInput) {
                    try {
                        // Clear the file input
                        fileInput.value = '';
                    } catch (e) {
                        // Silently handle error
                    }
                }
            }
        }
        
        // Show the modal
        if (this.hasModalTarget && this.modal) {
            this.modal.show();
        }
    }

    handleDroppedFile(file) {
        // Store the file for later use
        this._droppedFile = file;
        
        // Show the modal and wait for it to fully show
        if (this.hasModalTarget && this.modal) {
            try {
                // Show the modal first to ensure the DOM is updated
                this.modal.show();
                
                // Add a listener for when the modal is fully shown
                const boundProcess = this._processDroppedFile.bind(this);
                this.modalTarget.addEventListener('shown.bs.modal', boundProcess, { once: true });
                
                // As a fallback, try processing after a slight delay
                setTimeout(() => {
                    // Check if the file was processed
                    if (this._droppedFile === file) {
                        boundProcess();
                    }
                }, 500);
            } catch (e) {
                // Try to process the file anyway
                this._processDroppedFile();
            }
        } else {
            // Try to process the file directly
            this._processDroppedFile();
        }
    }
    
    _processDroppedFile() {
        // This method is called once the modal is fully shown and DOM is ready
        if (!this._droppedFile) {
            return;
        }
        
        // Get the file from our stored property
        const file = this._droppedFile;
        
        // First check if the form exists
        if (!this.hasFormTarget) {
            return;
        }
        
        // Try several strategies to find the file input
        let fileInput = null;
        
        // Strategy 1: Direct querySelector on form
        fileInput = this.formTarget.querySelector('input[type="file"]');
        
        // Strategy 2: Look for file input with specific name (common in Symfony forms)
        if (!fileInput) {
            fileInput = this.formTarget.querySelector('input[name$="[file]"]');
        }
        
        // Strategy 3: Look in the file container specifically
        if (!fileInput && this.hasFileContainerTarget) {
            fileInput = this.fileContainerTarget.querySelector('input[type="file"]');
        }
        
        // Strategy 4: Create a file input if none exists
        if (!fileInput) {
            // Check if file container exists
            if (!this.hasFileContainerTarget) {
                this.handleError('Erreur technique: Impossible de charger le fichier. Veuillez rafraîchir la page et réessayer.');
                return;
            }
            
            // Create temporary input element
            fileInput = document.createElement('input');
            fileInput.type = 'file';
            fileInput.name = 'dropzone_form[file]'; // Match the expected Symfony form name
            fileInput.style.display = 'none';
            this.fileContainerTarget.appendChild(fileInput);
        }
        
        // Final check
        if (!fileInput) {
            this.handleError('Erreur technique: Impossible de charger le fichier. Veuillez rafraîchir la page et réessayer.');
            return;
        }
        
        // Set the file to the input
        try {
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            fileInput.files = dataTransfer.files;
            
            // Afficher le nom du fichier - but don't empty the container first
            // to preserve the file input
            const fileNameDisplay = document.createElement('div');
            fileNameDisplay.className = 'alert alert-info';
            fileNameDisplay.innerHTML = `<i class="bi bi-file-earmark me-2"></i>${file.name}`;
            
            // Remove any existing alerts in the container
            this.fileContainerTarget.querySelectorAll('.alert').forEach(el => el.remove());
            
            // Add the new file display
            this.fileContainerTarget.appendChild(fileNameDisplay);
        } catch (error) {
            this.handleError('Erreur technique: Impossible de traiter le fichier. Veuillez réessayer.');
        }
        
        // Clear the stored file once we're done with it
        this._droppedFile = null;
    }

    async submitForm(event) {
        event.preventDefault();

        const form = this.formTarget;
        const formData = new FormData(form);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();

            if (data.success) {
                // Mettre à jour la liste des documents
                this.documentListTarget.innerHTML = data.html;

                // Fermer la modal
                this.modal.hide();

                // Réinitialiser le formulaire
                this.formTarget.reset();
            } else {
                // Afficher les erreurs dans la modal
                this.modalContentTarget.innerHTML = data.html;
            }
        } catch (error) {
            console.error('Error:', error);
            this.handleError('Une erreur est survenue lors de l\'upload');
        }
    }

    handleError(errorMessage) {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-danger mt-3';
        alertDiv.textContent = errorMessage;
        this.modalContentTarget.insertBefore(alertDiv, this.modalContentTarget.firstChild);
    }
}