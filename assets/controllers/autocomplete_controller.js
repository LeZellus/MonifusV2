import { Controller } from "@hotwired/stimulus"

/**
 * Autocomplete optimisé et corrigé - Version safe
 */
export default class extends Controller {
    static targets = ["input", "hiddenId", "results"]
    static values = { url: String }

    connect() {
        this.timeout = null;
        this.cache = new Map();
        this.lastQuery = '';
        
        // Configuration
        this.minLength = 2;
        this.debounceDelay = 250;
        this.cacheTimeout = 300000; // 5 minutes
        
        // Couleurs pré-calculées
        this.colors = [
            'bg-gradient-to-br from-blue-500 to-blue-600',
            'bg-gradient-to-br from-purple-500 to-purple-600', 
            'bg-gradient-to-br from-green-500 to-green-600',
            'bg-gradient-to-br from-orange-500 to-orange-600',
            'bg-gradient-to-br from-red-500 to-red-600',
            'bg-gradient-to-br from-indigo-500 to-indigo-600',
            'bg-gradient-to-br from-pink-500 to-pink-600',
            'bg-gradient-to-br from-teal-500 to-teal-600'
        ];
        
        this.typeIcons = {
            'Ressource': '🌿',
            'Équipement': '⚔️', 
            'Consommable': '🧪',
            'Divers': '📦'
        };
    }

    search() {
        clearTimeout(this.timeout);
        
        const query = this.inputTarget.value.trim();
        
        if (query.length < this.minLength) {
            this.hideResults();
            this.clearHiddenField();
            return;
        }

        if (query === this.lastQuery) return;
        
        this.timeout = setTimeout(() => {
            this.performSearch(query);
        }, this.debounceDelay);
    }

    async performSearch(query) {
        this.lastQuery = query;
        
        // Vérifier le cache
        if (this.cache.has(query)) {
            const cached = this.cache.get(query);
            if (Date.now() - cached.timestamp < this.cacheTimeout) {
                this.displayResults(cached.data, query);
                return;
            }
        }

        try {
            const response = await fetch(`${this.urlValue}?q=${encodeURIComponent(query)}`);
            
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            
            const data = await response.json();
            
            // Mettre en cache
            this.cache.set(query, {
                data: data.items,
                timestamp: Date.now()
            });
            
            this.displayResults(data.items, query);
            
        } catch (error) {
            console.error('Autocomplétion:', error);
            this.showError();
        }
    }

    displayResults(items, query = '') {
        if (items.length === 0) {
            this.showNoResults();
            return;
        }

        const html = items.map(item => this.createItemHTML(item, query)).join('');
        this.resultsTarget.innerHTML = html;
        this.showResults();
    }

    createItemHTML(item, query) {
        const avatarContent = this.getAvatarHTML(item);
        const typeIcon = this.typeIcons[item.type] || '📦';
        const highlightedName = this.highlightMatch(item.name, query);
        
        return `
            <div class="autocomplete-item group" data-id="${item.id}">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-lg overflow-hidden flex-shrink-0">
                        ${avatarContent}
                    </div>
                    
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-white truncate group-hover:text-blue-300 transition-colors">
                            ${highlightedName}
                        </div>
                        ${item.level ? `<div class="text-gray-400 text-sm">Niveau ${item.level}</div>` : ''}
                    </div>
                    
                    ${item.type ? `
                        <div class="text-xs text-gray-300 bg-gray-700 px-2 py-1 rounded-full flex items-center space-x-1">
                            <span>${typeIcon}</span>
                            <span>${item.type}</span>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    }

    getAvatarHTML(item) {
        const colorIndex = this.hashString(item.name) % this.colors.length;
        const bgClass = this.colors[colorIndex];
        const initial = item.name.charAt(0).toUpperCase();
        
        if (item.img_url) {
            return `
                <img src="${item.img_url}" alt="${item.name}" 
                     class="w-full h-full object-cover rounded-lg" 
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="w-full h-full ${bgClass} rounded-lg flex items-center justify-center text-white font-bold text-sm shadow-sm" style="display: none;">
                    ${initial}
                </div>
            `;
        }
        
        return `
            <div class="w-full h-full ${bgClass} rounded-lg flex items-center justify-center text-white font-bold text-sm shadow-sm">
                ${initial}
            </div>
        `;
    }

    hashString(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            hash = ((hash << 5) - hash + str.charCodeAt(i)) & 0xffffffff;
        }
        return Math.abs(hash);
    }

    selectItem(event) {
        // L'événement vient du conteneur, on cherche l'item cliqué
        const itemElement = event.target.closest('.autocomplete-item');
        
        if (!itemElement) {
            return; // Clic à côté d'un item
        }
        
        const itemId = itemElement.dataset.id;
        const nameElement = itemElement.querySelector('.font-medium');
        
        if (!nameElement) {
            console.error('Element .font-medium non trouvé');
            return;
        }
        
        const itemName = nameElement.textContent.trim();
        
        this.hiddenIdTarget.value = itemId;
        this.inputTarget.value = itemName;
        this.hideResults();
        
        // Animation de feedback comme dans l'original
        itemElement.style.transform = 'scale(0.95)';
        setTimeout(() => {
            itemElement.style.transform = 'scale(1)';
        }, 150);
    }

    showNoResults() {
        this.resultsTarget.innerHTML = `
            <div class="p-4 text-center text-gray-400">
                <div class="text-2xl mb-2">🔍</div>
                <div class="text-sm">Aucun résultat trouvé</div>
            </div>
        `;
        this.showResults();
    }

    showError() {
        this.resultsTarget.innerHTML = `
            <div class="p-4 text-center text-red-400">
                <div class="text-2xl mb-2">⚠️</div>
                <div class="text-sm">Erreur de recherche</div>
            </div>
        `;
        this.showResults();
    }

    highlightMatch(text, query) {
        if (!query) return text;
        const regex = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
        return text.replace(regex, '<span class="bg-blue-600 text-blue-100 px-1 rounded">$1</span>');
    }

    showResults() {
        this.resultsTarget.classList.remove('hidden');
        this.resultsTarget.style.opacity = '0';
        this.resultsTarget.style.transform = 'translateY(-10px)';
        
        requestAnimationFrame(() => {
            this.resultsTarget.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
            this.resultsTarget.style.opacity = '1';
            this.resultsTarget.style.transform = 'translateY(0)';
        });
    }

    hideResults() {
        this.resultsTarget.style.transition = 'opacity 0.15s ease';
        this.resultsTarget.style.opacity = '0';
        
        setTimeout(() => {
            this.resultsTarget.classList.add('hidden');
            this.resultsTarget.style.transition = '';
        }, 150);
    }

    clearHiddenField() {
        this.hiddenIdTarget.value = '';
    }

    disconnect() {
        clearTimeout(this.timeout);
        this.cache.clear();
    }
}