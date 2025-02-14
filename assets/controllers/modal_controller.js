// assets/controllers/modal_controller.js
import { Controller } from '@hotwired/stimulus'
import { Modal } from 'bootstrap'  // Ajout de l'import de Bootstrap

export default class extends Controller {
    static targets = ['modal', 'content']
    static values = {
        modalUrl: String
    }

    connect() {
        console.log('Modal controller connected with targets:', this.hasModalTarget, this.hasContentTarget)
    }

    async open(event) {
        event.preventDefault()
        console.log('Open modal triggered', event.currentTarget)

        const url = event.currentTarget.dataset.modalUrlValue
        console.log('Loading content from:', url)

        if (!url) {
            console.error('No URL found on button')
            return
        }

        try {
            const response = await fetch(url, {
                headers: {
                    'Turbo-Frame': 'modal',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`)
            }

            const html = await response.text()
            this.contentTarget.innerHTML = html

            // Utilisation de la classe Modal importée
            const modal = new Modal(this.modalTarget)
            modal.show()
        } catch (error) {
            console.error('Error:', error)
        }
    }
}