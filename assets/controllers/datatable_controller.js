import { Controller } from "@hotwired/stimulus"
import $ from "jquery"
import DataTable from "datatables.net-dt"

// Make jQuery global for DataTables
window.$ = $
window.jQuery = $

// Make DataTable available globally
window.DataTable = DataTable

export default class extends Controller {
    static targets = ["table"]
    static values = {
        ajaxUrl: String,
        columns: Array,
        serverSide: { type: Boolean, default: true },
        pageLength: { type: Number, default: 25 },
        language: { type: Object, default: {} }
    }

    connect() {
        console.log("🟢 DataTable Controller connecté")
        console.log("URL Ajax:", this.ajaxUrlValue)
        console.log("Server-side:", this.serverSideValue)

        this.initializeDataTable()
    }

    disconnect() {
        console.log("🔴 DataTable Controller déconnecté")
        if (this.dataTable) {
            this.dataTable.destroy()
        }
    }

    initializeDataTable() {
        const defaultLanguage = {
            "sProcessing": "Traitement en cours...",
            "sSearch": "Rechercher :",
            "sLengthMenu": "Afficher _MENU_ entrées",
            "sInfo": "Affichage de _START_ à _END_ sur _TOTAL_ entrées",
            "sInfoEmpty": "Affichage de 0 à 0 sur 0 entrée",
            "sInfoFiltered": "(filtré de _MAX_ entrées au total)",
            "sInfoPostFix": "",
            "sLoadingRecords": "Chargement en cours...",
            "sZeroRecords": "Aucune entrée à afficher",
            "sEmptyTable": "Aucune donnée disponible dans ce tableau",
            "oPaginate": {
                "sFirst": "Premier",
                "sPrevious": "Précédent",
                "sNext": "Suivant",
                "sLast": "Dernier"
            },
            "oAria": {
                "sSortAscending": ": activer pour trier la colonne par ordre croissant",
                "sSortDescending": ": activer pour trier la colonne par ordre décroissant"
            },
            "select": {
                "rows": {
                    "_": "%d lignes sélectionnées",
                    "0": "Aucune ligne sélectionnée",
                    "1": "1 ligne sélectionnée"
                }
            }
        }

        // Fonctions de rendu personnalisées
        const customRenderers = {
            renderItem: (data, type, row) => {
                if (type === 'display') {
                    const img = row.itemImage ? `<img src="${row.itemImage}" alt="${data}" class="w-6 h-6 inline-block mr-2">` : ''
                    return `${img}<span class="text-white font-medium">${data}</span>`
                }
                return data
            },

            renderPrice: (data, type, row) => {
                if (type === 'display') {
                    const formatted = new Intl.NumberFormat('fr-FR').format(data)
                    return `<span class="text-yellow-400 font-mono">${formatted}</span>`
                }
                return data
            },

            renderProfit: (data, type, row) => {
                if (type === 'display') {
                    const formatted = new Intl.NumberFormat('fr-FR', { signDisplay: 'always' }).format(data)
                    const colorClass = data >= 0 ? 'text-green-400' : 'text-red-400'
                    return `<span class="${colorClass} font-mono font-bold">${formatted}</span>`
                }
                return data
            },

            renderPerformance: (data, type, row) => {
                if (type === 'display') {
                    const percentage = Math.round(data * 100) / 100
                    const colorClass = percentage >= 100 ? 'text-green-400' : 'text-orange-400'
                    return `<span class="${colorClass} font-mono">${percentage.toFixed(1)}%</span>`
                }
                return data
            },

            renderActions: (data, type, row) => {
                if (type === 'display') {
                    return `
                        <div class="action-group">
                            <a href="/lot-unit/${data}/edit" class="text-blue-400 hover:text-blue-300 text-sm">
                                Modifier
                            </a>
                        </div>
                    `
                }
                return data
            }
        }

        // Traiter les colonnes pour appliquer les renderers
        const processedColumns = this.columnsValue.map(col => {
            if (col.render && customRenderers[col.render]) {
                col.render = customRenderers[col.render]
            }
            return col
        })

        const config = {
            processing: true,
            serverSide: this.serverSideValue,
            ajax: {
                url: this.ajaxUrlValue,
                type: 'GET',
                dataSrc: (json) => {
                    console.log('DataTables response:', json)
                    if (json.error) {
                        console.error('Server error:', json.error)
                        // Afficher l'erreur dans l'interface
                        return []
                    }
                    return json.data
                },
                error: (xhr, error, code) => {
                    console.error('DataTable AJAX Error:', error, code)
                    console.error('Status:', xhr.status)
                    console.error('Response Text:', xhr.responseText)

                    // Essayer de parser la réponse pour plus de détails
                    try {
                        const response = JSON.parse(xhr.responseText)
                        console.error('Parsed error response:', response)
                    } catch (e) {
                        console.error('Could not parse error response')
                    }
                }
            },
            columns: processedColumns,
            pageLength: this.pageLengthValue,
            language: { ...defaultLanguage, ...this.languageValue },
            responsive: true,
            stateSave: true,
            stateDuration: 300, // 5 minutes
            dom: '<"datatable-top"<"datatable-length"l><"datatable-filter"f>>rtip',
            drawCallback: () => {
                // Callback après chaque redraw
                this.dispatch("draw", { detail: { dataTable: this.dataTable } })
            },
            initComplete: (settings, json) => {
                console.log("✅ DataTable initialisé")
                this.dispatch("initialized", { detail: { dataTable: this.dataTable, settings, json } })
            }
        }

        // Initialiser DataTable
        this.dataTable = new DataTable(this.tableTarget, config)

        // Stocker la référence globalement pour debug
        window.currentDataTable = this.dataTable

        console.log("DataTable créé:", this.dataTable)
    }

    // Actions publiques
    reload() {
        if (this.dataTable) {
            console.log("🔄 Rechargement DataTable")
            this.dataTable.ajax.reload()
        }
    }

    search(query) {
        if (this.dataTable) {
            console.log("🔍 Recherche DataTable:", query)
            this.dataTable.search(query).draw()
        }
    }

    // Getters
    get api() {
        return this.dataTable
    }

    get isInitialized() {
        return !!this.dataTable
    }
}