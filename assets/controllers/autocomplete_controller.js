import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["input", "results", "hiddenId"]
    static values = { url: String }

    connect() {
        this.timeout = null;
        this.cache = new Map();
        this.abortController = null;
        this.hideResultsOnClick = this.hideResultsOnClick.bind(this);
        document.addEventListener('click', this.hideResultsOnClick);
    }

    disconnect() {
        document.removeEventListener('click', this.hideResultsOnClick);
        if (this.timeout) {
            clearTimeout(this.timeout);
        }
        if (this.abortController) {
            this.abortController.abort();
        }
    }

    search() {
        clearTimeout(this.timeout);
        
        if (this.abortController) {
            this.abortController.abort();
        }
        
        const query = this.inputTarget.value.trim().toLowerCase();
        
        if (query.length < 2) {
            this.hideResults();
            return;
        }

        if (this.cache.has(query)) {
            this.displayResults(this.cache.get(query));
            return;
        }

        this.timeout = setTimeout(() => {
            this.fetchResults(query);
        }, 150);
    }

    async fetchResults(query) {
        try {
            this.abortController = new AbortController();
            
            const url = `${this.urlValue}?q=${encodeURIComponent(query)}&limit=10`;
            
            const response = await fetch(url, {
                signal: this.abortController.signal,
                headers: {
                    'Accept': 'application/json',
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const data = await response.json();
            const items = data.items || [];
            
            this.cache.set(query, items);
            
            if (this.cache.size > 50) {
                const firstKey = this.cache.keys().next().value;
                this.cache.delete(firstKey);
            }
            
            this.displayResults(items);
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('Search error:', error);
                this.hideResults();
            }
        }
    }

    displayResults(items) {
        if (items.length === 0) {
            this.resultsTarget.innerHTML = '<div class="p-3 text-gray-400 text-center">Aucun item trouvé</div>';
            this.resultsTarget.classList.remove('hidden');
            return;
        }

        const fragment = document.createDocumentFragment();
        
        items.forEach(item => {
            const div = document.createElement('div');
            div.className = 'autocomplete-item p-3 hover:bg-gray-700 cursor-pointer border-b border-gray-600 last:border-b-0';
            div.dataset.itemId = item.id;
            div.dataset.itemName = item.name;
            
            div.innerHTML = `
                <div class="text-white font-medium">${this.escapeHtml(item.name)}</div>
                ${item.level ? `<div class="text-gray-400 text-sm">Niveau ${item.level}</div>` : ''}
                ${item.type ? `<div class="text-blue-400 text-xs">${this.escapeHtml(item.type)}</div>` : ''}
            `;
            
            fragment.appendChild(div);
        });

        this.resultsTarget.innerHTML = '';
        this.resultsTarget.appendChild(fragment);
        this.resultsTarget.classList.remove('hidden');
    }

    selectItem(event) {
        const item = event.target.closest('.autocomplete-item');
        if (!item) return;

        const itemId = item.dataset.itemId;
        const itemName = item.dataset.itemName;

        this.inputTarget.value = itemName;
        
        if (this.hasHiddenIdTarget) {
            this.hiddenIdTarget.value = itemId;
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