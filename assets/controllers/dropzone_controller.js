// dropzone_controller.js
import { Controller } from "@hotwired/stimulus"
import { Modal } from 'bootstrap'

export default class extends Controller {
    static targets = ["dropZone", "fileInput", "modal"]

    // Déclaration de la modal en propriété de classe
    modal = null

    connect() {
        // Initialisation de la modal dans le connect
        if (this.hasModalTarget) {
            this.modal = new Modal(this.modalTarget)
        }
    }

    dragOver(event) {
        event.preventDefault()
        event.stopPropagation()
        this.dropZoneTarget.classList.add('dragover')
    }

    dragLeave(event) {
        event.preventDefault()
        event.stopPropagation()
        this.dropZoneTarget.classList.remove('dragover')
    }

    drop(event) {
        event.preventDefault()
        event.stopPropagation()
        this.dropZoneTarget.classList.remove('dragover')

        const files = event.dataTransfer.files
        if (files.length) {
            this.fileInputTarget.files = files
            if (this.modal) {
                this.modal.show()
            }
        }
    }

    openFileSelector() {
        this.fileInputTarget.click()
    }

    handleFileSelect() {
        if (this.fileInputTarget.files.length && this.modal) {
            this.modal.show()
        }
    }

    handleSubmitEnd(event) {
        // Nettoyer la modal et le backdrop
        if (this.modal) {
            this.modal.hide()
            // Supprimer manuellement le backdrop
            const backdrop = document.querySelector('.modal-backdrop')
            if (backdrop) {
                backdrop.remove()
            }
            // Retirer la classe modal-open du body
            document.body.classList.remove('modal-open')
        }
    }

    // Méthode pour la destruction propre
    disconnect() {
        document.removeEventListener('turbo:submit-end', this.handleSubmitEnd.bind(this))
        if (this.modal) {
            this.modal.dispose()
            this.modal = null
        }
    }
}