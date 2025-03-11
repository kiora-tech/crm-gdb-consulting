import { Controller } from '@hotwired/stimulus'
import { Modal } from 'bootstrap'

export default class extends Controller {
    static targets = ['modal', 'content']
    static values = {
        modalUrl: String
    }

    connect() {
        console.log('Modal controller connected')
    }

    async open(event) {
        event.preventDefault()

        const url = event.currentTarget.dataset.modalUrlValue
        console.log('Loading content from:', url)

        if (!url) {
            console.error('No URL found on button')
            return
        }

        try {
            const response = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`)
            }

            const html = await response.text()
            this.contentTarget.innerHTML = html

            // Attacher explicitement les gestionnaires CSRF aux formulaires de la modale
            this.attachCsrfHandlers()

            const modal = new Modal(this.modalTarget)
            modal.show()
        } catch (error) {
            console.error('Error:', error)
        }
    }

    attachCsrfHandlers() {
        console.log('Attaching CSRF handlers to modal forms')
        const forms = this.contentTarget.querySelectorAll('form')

        forms.forEach(form => {
            // Détacher d'abord les écouteurs existants pour éviter les doublons
            form.removeEventListener('submit', this.handleFormSubmit)

            // Ajouter un nouvel écouteur
            form.addEventListener('submit', this.handleFormSubmit)
            console.log('Attached CSRF handler to form:', form)
        })
    }

    handleFormSubmit(event) {
        console.log('Form submit intercepted:', event.target)

        // Récupérer le champ CSRF
        var csrfField = event.target.querySelector('input[data-controller="csrf-protection"]');

        if (!csrfField) {
            return;
        }

        // Répliquer la logique du contrôleur CSRF ici
        var nameCheck = /^[-_a-zA-Z0-9]{4,22}$/;
        var tokenCheck = /^[-_/+a-zA-Z0-9]{24,}$/;

        var csrfCookie = csrfField.getAttribute('data-csrf-protection-cookie-value');
        var csrfToken = csrfField.value;

        console.log('CSRF token:', csrfToken, 'CSRF cookie:', csrfCookie)

        if (!csrfCookie && nameCheck.test(csrfToken)) {
            csrfField.setAttribute('data-csrf-protection-cookie-value', csrfCookie = csrfToken);
            csrfField.value = csrfToken = btoa(String.fromCharCode.apply(null, (window.crypto || window.msCrypto).getRandomValues(new Uint8Array(18))));
        }

        if (csrfCookie && tokenCheck.test(csrfToken)) {
            var cookie = csrfCookie + '_' + csrfToken + '=' + csrfCookie + '; path=/; samesite=strict';
            document.cookie = window.location.protocol === 'https:' ? '__Host-' + cookie + '; secure' : cookie;
        }
    }
}