// assets/controllers/customer-status-controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['statusButton']

    connect() {
        console.log("Customer status controller connected");
    }

    async changeStatus(event) {
        // Empêcher la navigation par défaut
        event.preventDefault();

        const button = event.currentTarget;
        const url = button.getAttribute('href');
        const status = button.dataset.status;
        const modalUrl = button.dataset.modalUrlValue;

        console.log("Changing status with URL:", url);
        console.log("Modal URL:", modalUrl);

        try {
            // Mettre à jour le statut via AJAX
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            console.log("Status update result:", result);

            if (result.success && status === 'lost') {
                console.log("Opening modal with URL:", modalUrl);

                // Trouver tous les éléments avec le contrôleur modal
                const modalElement = document.querySelector('[data-controller~="modal"]');
                console.log("Modal element:", modalElement);

                if (modalElement) {
                    // Créer un nouvel événement pour déclencher l'ouverture de la modal
                    const fakeEvent = {
                        preventDefault: () => {},
                        currentTarget: {
                            dataset: {
                                modalUrlValue: modalUrl
                            }
                        }
                    };

                    // Obtenir le contrôleur modal et appeler sa méthode open
                    const modalController = this.application.getControllerForElementAndIdentifier(
                        modalElement,
                        'modal'
                    );

                    if (modalController) {
                        console.log("Found modal controller, opening modal");
                        modalController.open(fakeEvent);
                    } else {
                        console.error("Modal controller not found");
                        window.location.href = modalUrl;
                    }
                } else {
                    console.error("No modal element found");
                    window.location.href = modalUrl;
                }
            } else {
                // Pour les autres statuts, on reload simplement la page
                window.location.reload();
            }
        } catch (error) {
            console.error("Error changing status:", error);
            window.location.reload();
        }
    }
}