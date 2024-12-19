import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['modal'];

  connect() {
    console.log('Modal controller connected');
  }

  open() {
    this.modalTarget.classList.remove('hidden');
    this.modalTarget.classList.add('flex');
  }

  close() {
    this.modalTarget.classList.add('hidden');
    this.modalTarget.classList.remove('flex');
  }

  closeOnBackdrop(event) {
    if (event.target === this.modalTarget) {
      this.close();
    }
  }
}
