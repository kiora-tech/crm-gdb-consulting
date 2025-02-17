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
        this.modal = new Modal(this.modalTarget);
        // Nettoyer l'overlay de la modal précédente
        document.body.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        document.body.classList.remove('modal-open');
    }

    disconnect() {
        document.body.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        document.body.classList.remove('modal-open');
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
        // Reset le formulaire
        this.formTarget.reset();
        this.modal.show();
    }

    handleDroppedFile(file) {
        const fileInput = this.formTarget.querySelector('input[type="file"]');
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        fileInput.files = dataTransfer.files;

        // Afficher le nom du fichier
        const fileNameDisplay = document.createElement('div');
        fileNameDisplay.className = 'alert alert-info';
        fileNameDisplay.innerHTML = `<i class="bi bi-file-earmark me-2"></i>${file.name}`;

        this.fileContainerTarget.innerHTML = '';
        this.fileContainerTarget.appendChild(fileNameDisplay);

        this.modal.show();
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