import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["input", "hiddenId", "results"]
    static values = { url: String }

    connect() {
        this.timeout = null
        this.createResultsContainer()
    }

    createResultsContainer() {
        if (!this.hasResultsTarget) {
            const results = document.createElement('div')
            results.className = 'autocomplete-results hidden absolute z-10 w-full bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto'
            results.setAttribute('data-autocomplete-target', 'results')
            this.inputTarget.parentNode.appendChild(results)
        }
    }

    search() {
        clearTimeout(this.timeout)
        
        const query = this.inputTarget.value.trim()
        
        if (query.length < 2) {
            this.hideResults()
            return
        }

        this.timeout = setTimeout(() => {
            this.performSearch(query)
        }, 300)
    }

    async performSearch(query) {
        try {
            const response = await fetch(`${this.urlValue}?q=${encodeURIComponent(query)}`)
            const data = await response.json()
            this.displayResults(data.items)
        } catch (error) {
            console.error('Erreur autocomplétion:', error)
        }
    }

    displayResults(items) {
        if (items.length === 0) {
            this.hideResults()
            return
        }

        const html = items.map(item => 
            `<div class="p-3 hover:bg-gray-100 cursor-pointer border-b" data-id="${item.id}" data-action="click->autocomplete#selectItem">
                <div class="font-medium">${item.name}</div>
                ${item.level ? `<div class="text-sm text-gray-500">Niveau ${item.level}</div>` : ''}
            </div>`
        ).join('')

        this.resultsTarget.innerHTML = html
        this.showResults()
    }

    selectItem(event) {
        const itemId = event.currentTarget.dataset.id
        const itemName = event.currentTarget.querySelector('.font-medium').textContent
        
        this.hiddenIdTarget.value = itemId
        this.inputTarget.value = itemName
        this.hideResults()
    }

    showResults() {
        this.resultsTarget.classList.remove('hidden')
    }

    hideResults() {
        this.resultsTarget.classList.add('hidden')
    }
}