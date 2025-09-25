import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["search", "lengthSelect", "loading", "cards", "noData", "info", "pagination"]
    static values = {
        ajaxUrl: String,
        pageLength: Number
    }

    connect() {
        this.currentPage = 1
        this.currentLength = this.pageLengthValue || 10
        this.currentSearch = ""
        this.currentSort = { column: 0, direction: 'desc' }
        this.totalRecords = 0
        this.filteredRecords = 0

        this.loadData()
        this.setupEventListeners()
    }

    setupEventListeners() {
        // Search
        if (this.hasSearchTarget) {
            this.searchTarget.addEventListener('input', (e) => {
                this.currentSearch = e.target.value
                this.currentPage = 1
                this.debounceLoadData()
            })
        }

        // Length selector
        if (this.hasLengthSelectTarget) {
            this.lengthSelectTarget.addEventListener('change', (e) => {
                this.currentLength = parseInt(e.target.value)
                this.currentPage = 1
                this.loadData()
            })
        }
    }

    debounceLoadData() {
        clearTimeout(this.searchTimeout)
        this.searchTimeout = setTimeout(() => {
            this.loadData()
        }, 300)
    }

    showLoading() {
        if (this.hasLoadingTarget) {
            this.loadingTarget.classList.remove('hidden')
        }
        if (this.hasCardsTarget) {
            this.cardsTarget.innerHTML = ''
        }
        if (this.hasNoDataTarget) {
            this.noDataTarget.classList.add('hidden')
        }
    }

    hideLoading() {
        if (this.hasLoadingTarget) {
            this.loadingTarget.classList.add('hidden')
        }
    }

    async loadData() {
        this.showLoading()

        const params = new URLSearchParams({
            page: this.currentPage,
            length: this.currentLength,
            search: this.currentSearch,
            sortColumn: this.currentSort.column,
            sortDirection: this.currentSort.direction
        })

        const url = `${this.ajaxUrlValue}?${params}`
        console.log('📱 Chargement mobile des données depuis:', url)

        try {
            const response = await fetch(url)
            console.log('📡 Réponse mobile reçue:', response.status, response.statusText)

            if (!response.ok) {
                const errorText = await response.text()
                console.error('❌ Erreur HTTP mobile:', response.status, errorText)
                throw new Error(`HTTP error! status: ${response.status}`)
            }

            const data = await response.json()
            console.log('📊 Données mobiles reçues:', data)

            this.renderCards(data.mobile_cards || '')
            this.updateInfo(data)
            this.renderPagination(data)

            this.totalRecords = data.recordsTotal
            this.filteredRecords = data.recordsFiltered

        } catch (error) {
            console.error('💥 Erreur lors du chargement mobile:', error)
            this.renderError('Erreur lors du chargement: ' + error.message)
        } finally {
            this.hideLoading()
        }
    }

    renderCards(cardsHtml) {
        if (this.hasCardsTarget) {
            this.cardsTarget.innerHTML = cardsHtml

            if (cardsHtml.trim() === '') {
                this.showNoData()
            } else {
                this.hideNoData()
            }
        }
    }

    renderError(message) {
        if (this.hasCardsTarget) {
            this.cardsTarget.innerHTML = `
                <div class="wrapper-background text-center py-8">
                    <div class="text-red-400">
                        <span class="text-4xl block mb-4">⚠️</span>
                        <p class="text-lg mb-2">Erreur</p>
                        <p class="text-sm">${message}</p>
                    </div>
                </div>
            `
        }
    }

    showNoData() {
        if (this.hasNoDataTarget) {
            this.noDataTarget.classList.remove('hidden')
        }
    }

    hideNoData() {
        if (this.hasNoDataTarget) {
            this.noDataTarget.classList.add('hidden')
        }
    }

    updateInfo(data) {
        if (this.hasInfoTarget) {
            const start = ((this.currentPage - 1) * this.currentLength) + 1
            const end = Math.min(this.currentPage * this.currentLength, data.recordsFiltered)
            this.infoTarget.textContent = `Affichage de ${start} à ${end} sur ${data.recordsFiltered} entrées`

            if (data.recordsFiltered < data.recordsTotal) {
                this.infoTarget.textContent += ` (filtré à partir de ${data.recordsTotal} entrées totales)`
            }
        }
    }

    renderPagination(data) {
        if (!this.hasPaginationTarget) return

        const totalPages = Math.ceil(data.recordsFiltered / this.currentLength)

        if (totalPages <= 1) {
            this.paginationTarget.innerHTML = ''
            return
        }

        let paginationHTML = ''

        // Previous button
        const prevDisabled = this.currentPage === 1
        paginationHTML += `
            <button class="px-3 py-2 bg-gray-600 text-white rounded hover:bg-gray-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed text-sm"
                    data-action="click->market-watch-mobile#previousPage"
                    ${prevDisabled ? 'disabled' : ''}>
                Précédent
            </button>
        `

        // Page info
        paginationHTML += `
            <span class="px-3 py-2 text-gray-300 text-sm">
                Page ${this.currentPage} / ${totalPages}
            </span>
        `

        // Next button
        const nextDisabled = this.currentPage === totalPages
        paginationHTML += `
            <button class="px-3 py-2 bg-gray-600 text-white rounded hover:bg-gray-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed text-sm"
                    data-action="click->market-watch-mobile#nextPage"
                    ${nextDisabled ? 'disabled' : ''}>
                Suivant
            </button>
        `

        this.paginationTarget.innerHTML = paginationHTML
    }

    previousPage() {
        if (this.currentPage > 1) {
            this.currentPage--
            this.loadData()
        }
    }

    nextPage() {
        const totalPages = Math.ceil(this.filteredRecords / this.currentLength)
        if (this.currentPage < totalPages) {
            this.currentPage++
            this.loadData()
        }
    }
}