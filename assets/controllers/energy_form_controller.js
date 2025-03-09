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
        const url = new URL(form.action);

        // Ajouter un paramètre pour indiquer le type d'énergie
        url.searchParams.append('energyType', type);

        // Faire une requête GET pour récupérer le formulaire actualisé
        fetch(url.toString(), {
            method: 'GET',
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

                // Remplacer le contenu du formulaire
                const newFormContent = tempDiv.querySelector('form');
                if (newFormContent) {
                    // Préserver l'action et les autres attributs du formulaire
                    const action = form.action;
                    const method = form.method;
                    const enctype = form.enctype;

                    // Mise à jour du contenu
                    form.innerHTML = newFormContent.innerHTML;

                    // Restauration des attributs
                    form.action = action;
                    form.method = method;
                    form.enctype = enctype;

                    // S'assurer que le type est toujours sélectionné
                    const typeSelect = form.querySelector('[name="energy[type]"]');
                    if (typeSelect) {
                        typeSelect.value = type;
                    }

                    // Réinitialiser les contrôleurs Stimulus sur le nouveau contenu
                    const stimulusApplication = this.application;
                    if (stimulusApplication) {
                        stimulusApplication.controllers.forEach(controller => {
                            if (controller.element.contains(form)) {
                                controller.disconnect();
                                controller.connect();
                            }
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Error updating form:', error);
            });
    }
}