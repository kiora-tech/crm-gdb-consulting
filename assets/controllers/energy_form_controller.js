import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['type', 'form']

    typeChange(event) {
        if (event.target.value) {
            this.updateFormFields(event.target.value);
        }
    }

    updateFormFields(type) {
        const form = this.formTarget;
        const formData = new FormData(form);

        // Conserver la valeur du type sélectionné pour le réappliquer après le rechargement
        formData.set('energy[type]', type);

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            }
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text();
            })
            .then(html => {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = html;

                // Ne remplacer que le contenu du formulaire, pas tout le formulaire
                const newForm = tempDiv.querySelector('form');
                if (newForm) {
                    form.innerHTML = newForm.innerHTML;

                    // Réappliquer la valeur du type
                    const typeSelect = form.querySelector('[name="energy[type]"]');
                    if (typeSelect) {
                        typeSelect.value = type;
                    }
                }
            })
            .catch(error => {
                console.error('Error updating form:', error);
            });
    }
}