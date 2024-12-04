import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['row'];

  initialize() {
    this.sortState = {}; 
    this.originalOrder = []; 
    console.log("initialized")
  }

  connect() {
    this.originalOrder = Array.from(this.rowTargets);
  }

  sort(event) {
    const columnIndex = event.currentTarget.dataset.index;
    const rows = Array.from(this.rowTargets);

    if (!this.sortState[columnIndex]) {
      this.sortState[columnIndex] = 'default';
    }

    switch (this.sortState[columnIndex]) {
      case 'default':
        this.sortState[columnIndex] = 'asc';
        this.sortAsc(rows, columnIndex);
        break;
      case 'asc':
        this.sortState[columnIndex] = 'desc';
        this.sortDesc(rows, columnIndex);
        break;
      case 'desc':
        this.sortState[columnIndex] = 'default';
        this.resetOrder();
        break;
    }
  }

  sortAsc(rows, columnIndex) {
    rows.sort((a, b) => {
      const aText = a.children[columnIndex]?.textContent.trim() || '';
      const bText = b.children[columnIndex]?.textContent.trim() || '';
      return aText.localeCompare(bText);
    });
    this.updateTable(rows);
  }

  sortDesc(rows, columnIndex) {
    rows.sort((a, b) => {
      const aText = a.children[columnIndex]?.textContent.trim() || '';
      const bText = b.children[columnIndex]?.textContent.trim() || '';
      return bText.localeCompare(aText);
    });
    this.updateTable(rows);
  }

  resetOrder() {
    this.updateTable(this.originalOrder);
  }

  updateTable(rows) {
    const tbody = this.element.querySelector('tbody');
    tbody.innerHTML = ''; 
    rows.forEach(row => tbody.appendChild(row));
  }
}