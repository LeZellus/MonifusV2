import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["table", "tbody", "pagination", "info", "search", "lengthSelect", "loading"]
    static values = {
        ajaxUrl: String,
        columns: Array,
        pageLength: Number
    }

    connect() {
        this.currentPage = 1
        this.currentLength = this.pageLengthValue || 25
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

        // Column sorting
        this.element.querySelectorAll('th[data-sortable]').forEach((th, index) => {
            th.addEventListener('click', () => {
                this.sort(index)
            })
            th.style.cursor = 'pointer'
            th.classList.add('hover:bg-gray-600')
        })
    }

    debounceLoadData() {
        clearTimeout(this.searchTimeout)
        this.searchTimeout = setTimeout(() => {
            this.loadData()
        }, 300)
    }

    sort(columnIndex) {
        if (this.currentSort.column === columnIndex) {
            this.currentSort.direction = this.currentSort.direction === 'asc' ? 'desc' : 'asc'
        } else {
            this.currentSort = { column: columnIndex, direction: 'desc' }
        }

        this.updateSortIndicators()
        this.loadData()
    }

    updateSortIndicators() {
        // Remove all sort indicators
        this.element.querySelectorAll('th[data-sortable]').forEach(th => {
            th.classList.remove('sort-asc', 'sort-desc')
        })

        // Add current sort indicator
        const currentHeader = this.element.querySelectorAll('th[data-sortable]')[this.currentSort.column]
        if (currentHeader) {
            currentHeader.classList.add(`sort-${this.currentSort.direction}`)
        }
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

    goToPage(page) {
        const totalPages = Math.ceil(this.filteredRecords / this.currentLength)
        if (page >= 1 && page <= totalPages) {
            this.currentPage = page
            this.loadData()
        }
    }

    showLoading() {
        if (this.hasLoadingTarget) {
            this.loadingTarget.classList.remove('hidden')
        }
        this.tbodyTarget.innerHTML = '<tr><td colspan="100%" class="text-center py-8 text-gray-400">Chargement...</td></tr>'
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

        // Vérifier si l'URL contient déjà des paramètres (?)
        const separator = this.ajaxUrlValue.includes('?') ? '&' : '?'
        const url = `${this.ajaxUrlValue}${separator}${params}`
        console.log('🔍 Chargement des données depuis:', url)

        try {
            const response = await fetch(url)
            console.log('📡 Réponse reçue:', response.status, response.statusText)

            if (!response.ok) {
                const errorText = await response.text()
                console.error('❌ Erreur HTTP:', response.status, errorText)
                throw new Error(`HTTP error! status: ${response.status}`)
            }

            const data = await response.json()
            console.log('📊 Données reçues:', data)

            this.renderTable(data.data)
            this.updateInfo(data)
            this.renderPagination(data)

            this.totalRecords = data.recordsTotal
            this.filteredRecords = data.recordsFiltered

        } catch (error) {
            console.error('💥 Erreur lors du chargement des données:', error)
            this.tbodyTarget.innerHTML = '<tr><td colspan="100%" class="text-center py-8 text-red-400">Erreur lors du chargement des données: ' + error.message + '</td></tr>'
        } finally {
            this.hideLoading()
        }
    }

    renderTable(data) {
        this.tbodyTarget.innerHTML = ''

        if (data.length === 0) {
            this.tbodyTarget.innerHTML = '<tr><td colspan="100%" class="text-center py-8 text-gray-400">Aucune donnée trouvée</td></tr>'
            return
        }

        data.forEach(row => {
            const tr = document.createElement('tr')
            tr.className = 'hover:bg-gray-700 transition-colors'
            tr.innerHTML = row.map(cell => `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">${cell}</td>`).join('')
            this.tbodyTarget.appendChild(tr)
        })

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
        const prevDisabled = this.currentPage === 1 ? 'disabled' : ''
        paginationHTML += `
            <button class="pagination-btn ${prevDisabled}" data-nav="prev" ${prevDisabled ? 'disabled' : ''}>
                Précédent
            </button>
        `

        // Page numbers
        const startPage = Math.max(1, this.currentPage - 2)
        const endPage = Math.min(totalPages, this.currentPage + 2)

        if (startPage > 1) {
            paginationHTML += `<button class="pagination-btn" data-page="1">1</button>`
            if (startPage > 2) {
                paginationHTML += `<span class="pagination-ellipsis">...</span>`
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            const active = i === this.currentPage ? 'active' : ''
            paginationHTML += `
                <button class="pagination-btn ${active}" data-page="${i}">
                    ${i}
                </button>
            `
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                paginationHTML += `<span class="pagination-ellipsis">...</span>`
            }
            paginationHTML += `<button class="pagination-btn" data-page="${totalPages}">${totalPages}</button>`
        }

        // Next button
        const nextDisabled = this.currentPage === totalPages ? 'disabled' : ''
        paginationHTML += `
            <button class="pagination-btn ${nextDisabled}" data-nav="next" ${nextDisabled ? 'disabled' : ''}>
                Suivant
            </button>
        `

        this.paginationTarget.innerHTML = paginationHTML

        // Ajouter les event listeners pour tous les boutons de pagination
        this.paginationTarget.querySelectorAll('.pagination-btn').forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault()

                if (button.disabled) return

                const page = button.getAttribute('data-page')
                const nav = button.getAttribute('data-nav')

                if (page) {
                    console.log('📄 Clic sur page:', page)
                    this.goToPage(parseInt(page))
                } else if (nav === 'prev') {
                    console.log('📄 Clic sur précédent')
                    this.previousPage()
                } else if (nav === 'next') {
                    console.log('📄 Clic sur suivant')
                    this.nextPage()
                }
            })
        })
    }

}