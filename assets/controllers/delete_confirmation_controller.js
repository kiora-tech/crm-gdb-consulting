import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        console.log('Delete confirmation controller connected');
    }

    confirm(event) {
        const message = this.element.dataset.deleteConfirmationMessage;
        console.log('Confirmation triggered with message:', message);
        if (!confirm(message)) {
            event.preventDefault();
        }
    }
}