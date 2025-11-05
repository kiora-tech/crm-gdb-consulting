import { Controller } from '@hotwired/stimulus';

/*
 * Table selection controller
 * Manages checkbox selection in tables and creates email drafts with selected contacts
 */
export default class extends Controller {
    static targets = ['checkbox', 'selectAll', 'counter', 'emailButton'];

    connect() {
        console.log('Table selection controller connected');
        this.updateUI();
    }

    /**
     * Toggle all checkboxes when select-all is clicked
     */
    toggleAll(event) {
        const checked = event.target.checked;
        this.checkboxTargets.forEach(checkbox => {
            checkbox.checked = checked;
        });
        this.updateUI();
    }

    /**
     * Update selection UI when individual checkbox changes
     */
    updateSelection() {
        this.updateUI();
    }

    /**
     * Update UI elements based on selection state
     */
    updateUI() {
        const selected = this.getSelectedEmails();
        const count = selected.length;

        // Update counter
        this.counterTarget.textContent = count;

        // Update email button state
        this.emailButtonTarget.disabled = count === 0;

        // Update select-all checkbox state
        const total = this.checkboxTargets.length;
        if (this.hasSelectAllTarget) {
            this.selectAllTarget.checked = count > 0 && count === total;
            this.selectAllTarget.indeterminate = count > 0 && count < total;
        }
    }

    /**
     * Get array of selected email addresses
     * @returns {string[]}
     */
    getSelectedEmails() {
        return this.checkboxTargets
            .filter(checkbox => checkbox.checked)
            .map(checkbox => checkbox.dataset.email)
            .filter(email => email && email.length > 0);
    }

    /**
     * Create email draft with selected contacts in BCC
     */
    createEmailDraft() {
        const selectedEmails = this.getSelectedEmails();

        if (selectedEmails.length === 0) {
            alert('Veuillez sélectionner au moins un contact');
            return;
        }

        // Create mailto link with BCC
        // Format: mailto:?bcc=email1@example.com,email2@example.com
        const bccEmails = selectedEmails.join(',');
        const mailtoLink = `mailto:?bcc=${encodeURIComponent(bccEmails)}`;

        // Open default email client
        window.location.href = mailtoLink;

        console.log(`Creating email draft with ${selectedEmails.length} contacts in BCC:`, selectedEmails);
    }
}
