import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["input", "hiddenId", "results"]
    static values = { url: String }

    connect() {
        this.timeout = null
        // Pas besoin de createResultsContainer car la div existe déjà dans le template
    }

    search() {
        clearTimeout(this.timeout)
        
        const query = this.inputTarget.value.trim()
        
        // Arrêter si moins de 2 caractères
        if (query.length < 2) {
            this.hideResults()
            this.clearHiddenField() // Nettoyer la sélection précédente
            return
        }

        this.timeout = setTimeout(() => {
            this.performSearch(query)
        }, 300)
    }

    async performSearch(query) {
        try {
            const response = await fetch(`${this.urlValue}?q=${encodeURIComponent(query)}`)
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`)
            }
            
            const data = await response.json()
            this.displayResults(data.items, query)
        } catch (error) {
            console.error('Erreur autocomplétion:', error)
            this.showError()
        }
    }

    displayResults(items, query = '') {
        if (items.length === 0) {
            this.showNoResults()
            return
        }

        const html = items.map((item) => {
            const avatarBg = this.getAvatarColor(item.name)
            const typeIcon = this.getTypeIcon(item.type)
            
            // Utiliser l'image de l'item si disponible, sinon fallback sur l'initiale
            const avatarContent = item.img_url 
                ? `<img src="${item.img_url}" alt="${item.name}" class="w-full h-full object-cover rounded-lg" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="w-full h-full ${avatarBg} rounded-lg flex items-center justify-center text-white font-bold text-sm shadow-sm" style="display: none;">
                    ${item.name.charAt(0).toUpperCase()}
                </div>`
                : `<div class="w-full h-full ${avatarBg} rounded-lg flex items-center justify-center text-white font-bold text-sm shadow-sm">
                    ${item.name.charAt(0).toUpperCase()}
                </div>`
            
            return `
                <div class="autocomplete-item group" data-id="${item.id}" data-action="click->autocomplete#selectItem">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-lg overflow-hidden flex-shrink-0">
                            ${avatarContent}
                        </div>
                        
                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-white truncate group-hover:text-blue-300 transition-colors">
                                ${this.highlightMatch(item.name, query)}
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
            `
        }).join('')

        this.resultsTarget.innerHTML = html
        this.showResults()
    }

    showNoResults() {
        this.resultsTarget.innerHTML = `
            <div class="p-4 text-center text-gray-400">
                <div class="text-2xl mb-2">🔍</div>
                <div class="text-sm">Aucun résultat trouvé</div>
                <div class="text-xs text-gray-500 mt-1">Essayez avec d'autres mots-clés</div>
            </div>
        `
        this.showResults()
    }

    showError() {
        this.resultsTarget.innerHTML = `
            <div class="p-4 text-center text-red-400">
                <div class="text-2xl mb-2">⚠️</div>
                <div class="text-sm">Erreur de recherche</div>
                <div class="text-xs text-gray-500 mt-1">Veuillez réessayer</div>
            </div>
        `
        this.showResults()
    }

    highlightMatch(text, query) {
        if (!query) return text
        
        const regex = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi')
        return text.replace(regex, '<span class="bg-blue-600 text-blue-100 px-1 rounded">$1</span>')
    }

    getAvatarColor(name) {
        const colors = [
            'bg-gradient-to-br from-blue-500 to-blue-600',
            'bg-gradient-to-br from-purple-500 to-purple-600',
            'bg-gradient-to-br from-green-500 to-green-600',
            'bg-gradient-to-br from-orange-500 to-orange-600',
            'bg-gradient-to-br from-red-500 to-red-600',
            'bg-gradient-to-br from-indigo-500 to-indigo-600',
            'bg-gradient-to-br from-pink-500 to-pink-600',
            'bg-gradient-to-br from-teal-500 to-teal-600'
        ]
        
        let hash = 0
        for (let i = 0; i < name.length; i++) {
            hash = name.charCodeAt(i) + ((hash << 5) - hash)
        }
        
        return colors[Math.abs(hash) % colors.length]
    }

    getTypeIcon(type) {
        const icons = {
            'Ressource': '🌿',
            'Équipement': '⚔️',
            'Consommable': '🧪',
            'Divers': '📦'
        }
        
        return icons[type] || '📦'
    }

    selectItem(event) {
        const itemElement = event.currentTarget.closest('.autocomplete-item')
        const itemId = itemElement.dataset.id
        const itemName = itemElement.querySelector('.font-medium').textContent.trim()
        
        this.hiddenIdTarget.value = itemId
        this.inputTarget.value = itemName
        this.hideResults()
        
        // Animation de feedback
        itemElement.style.transform = 'scale(0.95)'
        setTimeout(() => {
            itemElement.style.transform = 'scale(1)'
        }, 150)
    }

    clearHiddenField() {
        this.hiddenIdTarget.value = ''
    }

    showResults() {
        this.resultsTarget.classList.remove('hidden')
        this.resultsTarget.style.opacity = '0'
        this.resultsTarget.style.transform = 'translateY(-10px)'
        
        requestAnimationFrame(() => {
            this.resultsTarget.style.transition = 'opacity 0.2s ease, transform 0.2s ease'
            this.resultsTarget.style.opacity = '1'
            this.resultsTarget.style.transform = 'translateY(0)'
        })
    }

    hideResults() {
        this.resultsTarget.style.transition = 'opacity 0.15s ease'
        this.resultsTarget.style.opacity = '0'
        
        setTimeout(() => {
            this.resultsTarget.classList.add('hidden')
            this.resultsTarget.style.transition = ''
        }, 150)
    }

    // Nettoyer en cas de déconnexion
    disconnect() {
        clearTimeout(this.timeout)
    }
}