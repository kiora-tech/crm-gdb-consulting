import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["note"]; // Define the target

    appendDate(event) {
        const currentDate = new Date().toLocaleDateString("en-GB");
        if (this.hasNoteTarget) {
            if (!this.noteTarget.value.includes(currentDate)) { 
                this.noteTarget.value += ` - ${currentDate}`;
            }
        } else {
            console.error("Note target is not found");
        }
    }
}
