import { Controller } from '@hotwired/stimulus';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

export default class extends Controller {
    static targets = ["filterBtn", "canvas"]
    static values = { 
        data: Object,
        type: String,
        options: Object 
    }

    connect() {
        this.enabledTypes = ['x1', 'x10', 'x100']; // Par défaut tout activé
        this.originalData = this.dataValue; // Sauvegarder les données originales
        
        const canvasElement = this.hasCanvasTarget ? this.canvasTarget : this.element;
        
        this.chart = new Chart(canvasElement, {
            type: this.typeValue || 'line',
            data: this.getFilteredData(),
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: '#fff'
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            color: '#9CA3AF',
                            maxTicksLimit: 8
                        },
                        grid: {
                            color: '#374151'
                        }
                    },
                    y: {
                        ticks: {
                            color: '#9CA3AF',
                            maxTicksLimit: 6,
                            callback: function(value) {
                                if (value >= 1000000) {
                                    return Math.round(value/1000000) + 'M';
                                } else if (value >= 1000) {
                                    return Math.round(value/1000) + 'K';
                                }
                                return Math.round(value);
                            }
                        },
                        grid: {
                            color: '#374151'
                        }
                    }
                },
                ...this.optionsValue
            }
        });
    }

    filterChart(event) {
        const filterType = event.currentTarget.dataset.filter;
        const isActive = event.currentTarget.classList.contains('active');
        
        if (isActive) {
            // Désactiver
            this.enabledTypes = this.enabledTypes.filter(type => type !== filterType);
            event.currentTarget.classList.remove('active');
            event.currentTarget.classList.add('opacity-50');
        } else {
            // Activer
            if (!this.enabledTypes.includes(filterType)) {
                this.enabledTypes.push(filterType);
            }
            event.currentTarget.classList.add('active');
            event.currentTarget.classList.remove('opacity-50');
        }
        
        this.updateChart();
    }

    updateChart() {
        this.chart.data = this.getFilteredData();
        this.chart.update();
    }

    getFilteredData() {
        const allDatasets = this.originalData.datasets;
        
        // Debug: voir tous les labels des datasets
        console.log('Tous les datasets:', allDatasets.map(d => d.label));
        console.log('Types activés:', this.enabledTypes);
        
        // Filtrer les datasets selon les types activés avec une logique plus précise
        const filteredDatasets = allDatasets.filter(dataset => {
            const label = dataset.label.toLowerCase();
            
            // Vérification exacte pour éviter les faux positifs
            const shouldInclude = this.enabledTypes.some(type => {
                if (type === 'x1') {
                    // Doit contenir "x1" mais pas "x10" ni "x100"
                    return label.includes('x1') && !label.includes('x10') && !label.includes('x100');
                } else if (type === 'x10') {
                    // Doit contenir "x10" mais pas "x100"  
                    return label.includes('x10') && !label.includes('x100');
                } else if (type === 'x100') {
                    // Doit contenir "x100"
                    return label.includes('x100');
                }
                return false;
            });
            
            console.log(`Dataset "${dataset.label}" -> ${shouldInclude ? 'INCLUS' : 'EXCLU'}`);
            return shouldInclude;
        });

        console.log('Datasets filtrés:', filteredDatasets.map(d => d.label));

        return {
            labels: this.originalData.labels,
            datasets: filteredDatasets
        };
    }

    disconnect() {
        if (this.chart) {
            this.chart.destroy();
        }
    }
}