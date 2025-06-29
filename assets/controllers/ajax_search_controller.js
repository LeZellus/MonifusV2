// assets/controllers/ajax-search_controller.js
import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = [
        "input", 
        "loader", 
        "icon", 
        "results", 
        "count", 
        "clear",
        "container"
    ]
    
    static values = {
        url: String,
        debounce: { type: Number, default: 300 },
        minLength: { type: Number, default: 0 },
        placeholder: { type: String, default: "Rechercher..." },
        keyboardShortcut: { type: String, default: "k" },
        countTemplate: { type: String, default: "{{count}} résultat(s) pour \"{{query}}\"" },
        emptyMessage: { type: String, default: "Aucun résultat trouvé" }
    }

    connect() {
        console.log("🟢 AJAX Search Controller connecté !")
        console.log("URL de recherche:", this.urlValue)
        console.log("Placeholder:", this.placeholderValue)
        console.log("Debounce:", this.debounceValue)
        
        // Vérifier les targets
        console.log("Input target trouvé:", this.hasInputTarget)
        console.log("Container targets:", this.containerTargets.length)
        
        if (this.hasInputTarget) {
            this.inputTarget.placeholder = this.placeholderValue
            console.log("Placeholder appliqué à l'input")
        }

        // Sauvegarder le contenu original
        this.saveOriginalContent()
        
        // Ajouter le raccourci clavier
        this.boundKeydownHandler = this.handleKeydown.bind(this)
        document.addEventListener('keydown', this.boundKeydownHandler)

        console.log("✅ Initialisation terminée")
    }

    disconnect() {
        console.log("🔴 AJAX Search Controller déconnecté")
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout)
        }
        
        if (this.boundKeydownHandler) {
            document.removeEventListener('keydown', this.boundKeydownHandler)
        }
    }

    // Action principale de recherche
    search() {
        console.log("🔍 ACTION SEARCH DÉCLENCHÉE")
        const query = this.inputTarget.value.trim()
        console.log("Query:", `"${query}"`)
        console.log("Query length:", query.length)
        console.log("Min length:", this.minLengthValue)
        
        // Vérifier la longueur minimale
        if (query.length > 0 && query.length < this.minLengthValue) {
            console.log("❌ Query trop courte, abandon")
            return
        }
        
        // Annuler la recherche précédente
        if (this.searchTimeout) {
            console.log("⏹️ Annulation de la recherche précédente")
            clearTimeout(this.searchTimeout)
        }

        // Debounce
        console.log(`⏱️ Démarrage du debounce (${this.debounceValue}ms)`)
        this.searchTimeout = setTimeout(() => {
            console.log("🚀 Exécution de la recherche après debounce")
            this.performSearch(query)
        }, this.debounceValue)
    }

    // Effacer la recherche
    clear() {
        console.log("🧹 CLEAR ACTION")
        this.inputTarget.value = ''
        this.performSearch('')
        this.inputTarget.focus()
    }

    // Focus programmatique
    focus() {
        console.log("🎯 FOCUS ACTION")
        this.inputTarget.focus()
    }

    // Effectuer la recherche
    async performSearch(query) {
        console.log("🌐 DÉBUT PERFORM SEARCH")
        console.log("Query à rechercher:", `"${query}"`)
        console.log("URL:", this.urlValue)
        
        try {
            this.showLoader()
            
            if (query === '' || query.length < this.minLengthValue) {
                console.log("⚪ Query vide, restauration du contenu original")
                this.restoreOriginalContent()
                return
            }

            // Construire l'URL avec les paramètres
            const url = new URL(this.urlValue, window.location.origin)
            url.searchParams.set('q', query)
            console.log("URL finale:", url.toString())

            console.log("📡 Démarrage de la requête fetch...")
            const response = await fetch(url)
            console.log("📥 Réponse reçue:", response.status, response.statusText)
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`)
            }
            
            const data = await response.json()
            console.log("📊 Données reçues:", data)
            
            this.updateResults(data, query)
            console.log("✅ Mise à jour terminée")
            
        } catch (error) {
            console.error("❌ ERREUR lors de la recherche:", error)
            this.handleError(error, query)
        } finally {
            this.hideLoader()
        }
    }

    // Mettre à jour les résultats
    updateResults(data, query) {
        console.log("🔄 UPDATE RESULTS")
        console.log("Data reçue:", data)
        console.log("Query:", query)
        
        // Mettre à jour le contenu des containers
        this.updateContainers(data)
        
        // Afficher les informations de recherche
        this.updateSearchInfo(data, query)
    }

    // Mettre à jour les containers de contenu
    updateContainers(data) {
        console.log("📦 UPDATE CONTAINERS")
        console.log("Nombre de containers:", this.containerTargets.length)
        
        this.containerTargets.forEach((container, index) => {
            const targetName = container.dataset.searchContainer
            console.log(`Container ${index}: targetName="${targetName}"`)
            
            if (targetName && data[targetName]) {
                console.log(`✅ Mise à jour du container ${targetName}`)
                console.log("Ancien contenu length:", container.innerHTML.length)
                container.innerHTML = data[targetName]
                console.log("Nouveau contenu length:", container.innerHTML.length)
            } else {
                console.log(`❌ Pas de données pour le container ${targetName}`)
            }
        })
    }

    // Mettre à jour les informations de recherche
    updateSearchInfo(data, query) {
        console.log("ℹ️ UPDATE SEARCH INFO")
        
        if (this.hasCountTarget) {
            const count = data.count !== undefined ? data.count : 0
            const countText = this.countTemplateValue
                .replace('{{count}}', count)
                .replace('{{query}}', query)
            
            console.log("Mise à jour du compteur:", countText)
            this.countTarget.textContent = countText
        } else {
            console.log("❌ Pas de count target")
        }

        if (this.hasResultsTarget) {
            console.log("Affichage de la section résultats")
            this.resultsTarget.classList.remove('hidden')
        } else {
            console.log("❌ Pas de results target")
        }
    }

    // Restaurer le contenu original
    restoreOriginalContent() {
        console.log("♻️ RESTORE ORIGINAL CONTENT")
        
        this.containerTargets.forEach(container => {
            const targetName = container.dataset.searchContainer
            const originalKey = `original_${targetName}`
            if (this[originalKey]) {
                console.log(`Restauration du container ${targetName}`)
                container.innerHTML = this[originalKey]
            }
        })

        if (this.hasResultsTarget) {
            this.resultsTarget.classList.add('hidden')
        }

        // Restaurer l'affichage normal des sections
        this.restoreSectionVisibility()
    }

    // Restaurer la visibilité normale des sections
    restoreSectionVisibility() {
        const desktopTable = document.getElementById('desktop-table')
        const mobileCards = this.element.querySelector('[data-search-container="mobile_cards"]')
        const emptyState = this.element.querySelector('[data-search-container="empty_state"]')
        
        desktopTable?.classList.remove('hidden')
        mobileCards?.classList.remove('hidden')
        emptyState?.classList.add('hidden')
    }

    // Sauvegarder le contenu original
    saveOriginalContent() {
        console.log("💾 SAVE ORIGINAL CONTENT")
        
        this.containerTargets.forEach(container => {
            const targetName = container.dataset.searchContainer
            if (targetName) {
                const originalKey = `original_${targetName}`
                this[originalKey] = container.innerHTML
                console.log(`Sauvegarde du container ${targetName} (${container.innerHTML.length} chars)`)
            }
        })
    }

    // Afficher le loader
    showLoader() {
        console.log("⏳ SHOW LOADER")
        if (this.hasLoaderTarget) {
            this.loaderTarget.classList.remove('hidden')
        }
        if (this.hasIconTarget) {
            this.iconTarget.classList.add('hidden')
        }
    }

    // Masquer le loader
    hideLoader() {
        console.log("⏸️ HIDE LOADER")
        if (this.hasLoaderTarget) {
            this.loaderTarget.classList.add('hidden')
        }
        if (this.hasIconTarget) {
            this.iconTarget.classList.remove('hidden')
        }
    }

    // Gérer les erreurs
    handleError(error, query) {
        console.log("💥 HANDLE ERROR")
        this.restoreOriginalContent()
        
        if (this.hasCountTarget) {
            this.countTarget.textContent = `Erreur lors de la recherche de "${query}"`
        }
    }

    // Gestion du raccourci clavier
    handleKeydown(event) {
        const shortcut = this.keyboardShortcutValue.toLowerCase()
        
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === shortcut) {
            console.log("⌨️ Raccourci clavier détecté")
            event.preventDefault()
            this.focus()
        }
        
        // Échapper pour effacer
        if (event.key === 'Escape' && document.activeElement === this.inputTarget) {
            console.log("⎋ Escape détecté")
            this.clear()
        }
    }

    // Getters pour les états
    get isSearching() {
        return this.hasLoaderTarget && !this.loaderTarget.classList.contains('hidden')
    }

    get hasQuery() {
        return this.inputTarget.value.trim().length > 0
    }

    get query() {
        return this.inputTarget.value.trim()
    }
}