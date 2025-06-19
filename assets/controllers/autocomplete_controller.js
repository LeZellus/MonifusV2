import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["input", "results", "hiddenId"]
    static values = { url: String }

    connect() {
        console.log('Autocomplete controller connected');
        console.log('Available targets:', this.targets);
        console.log('Input target exists:', this.hasInputTarget);
        console.log('Results target exists:', this.hasResultsTarget);
        console.log('HiddenId target exists:', this.hasHiddenIdTarget);
        
        this.timeout = null;
        this.hideResultsOnClick = this.hideResultsOnClick.bind(this);
        document.addEventListener('click', this.hideResultsOnClick);
    }

    disconnect() {
        document.removeEventListener('click', this.hideResultsOnClick);
        if (this.timeout) {
            clearTimeout(this.timeout);
        }
    }

    search() {
        console.log('Search triggered');
        
        if (!this.hasInputTarget) {
            console.error('Input target not found');
            return;
        }
        
        if (!this.hasResultsTarget) {
            console.error('Results target not found');
            return;
        }
        
        clearTimeout(this.timeout);
        const query = this.inputTarget.value.trim();
        console.log('Query:', query);
        
        if (query.length < 2) {
            this.hideResults();
            return;
        }

        this.timeout = setTimeout(() => {
            this.fetchResults(query);
        }, 300);
    }

    async fetchResults(query) {
        try {
            const url = `${this.urlValue}?q=${encodeURIComponent(query)}&limit=10`;
            console.log('Fetching:', url);
            
            const response = await fetch(url);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            console.log('API Response:', data);
            this.displayResults(data.items || []);
        } catch (error) {
            console.error('Search error:', error);
            this.hideResults();
        }
    }

    displayResults(items) {
        console.log('Displaying results:', items);
        
        if (items.length === 0) {
            this.resultsTarget.innerHTML = '<div class="p-3 text-gray-400 text-center">Aucun item trouvé</div>';
            this.resultsTarget.classList.remove('hidden');
            return;
        }

        this.resultsTarget.innerHTML = items.map(item => `
            <div class="autocomplete-item p-3 hover:bg-gray-700 cursor-pointer border-b border-gray-600 last:border-b-0" 
                 data-item-id="${item.id}" 
                 data-item-name="${item.name}">
                <div class="text-white font-medium">${this.escapeHtml(item.name)}</div>
                ${item.level ? `<div class="text-gray-400 text-sm">Niveau ${item.level}</div>` : ''}
                ${item.type ? `<div class="text-blue-400 text-xs">${this.escapeHtml(item.type)}</div>` : ''}
            </div>
        `).join('');

        this.resultsTarget.classList.remove('hidden');
    }

    selectItem(event) {
        console.log('Item selection triggered');
        const item = event.target.closest('.autocomplete-item');
        if (!item) return;

        const itemId = item.dataset.itemId;
        const itemName = item.dataset.itemName;
        console.log('Selected:', itemId, itemName);

        // Mettre à jour le champ de recherche
        this.inputTarget.value = itemName;
        
        // Mettre à jour le select caché
        if (this.hasHiddenIdTarget) {
            this.hiddenIdTarget.value = itemId;
            console.log('Updated hidden field to:', itemId);
            
            // Déclencher l'événement change
            this.hiddenIdTarget.dispatchEvent(new Event('change', { bubbles: true }));
        }
        
        this.hideResults();
    }

    hideResults() {
        if (this.hasResultsTarget) {
            this.resultsTarget.classList.add('hidden');
            this.resultsTarget.innerHTML = '';
        }
    }

    hideResultsOnClick(event) {
        if (!this.element.contains(event.target)) {
            this.hideResults();
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}