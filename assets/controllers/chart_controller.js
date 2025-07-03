import { Controller } from '@hotwired/stimulus';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

export default class extends Controller {
    static targets = ["filterBtn", "periodBtn", "canvas", "pointsInfo"]
    static values = { 
        data: Object,
        type: String,
        options: Object 
    }

    connect() {
        // MODIFIÉ : Inclure x1000 dans les types activés par défaut
        this.enabledTypes = ['x1', 'x10', 'x100', 'x1000'];
        this.currentPeriod = 'all';
        this.originalData = this.dataValue;
        
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
                            color: '#fff',
                            // MODIFIÉ : Améliorer l'affichage de la légende
                            boxWidth: 12,
                            padding: 10,
                            usePointStyle: true,
                            generateLabels: function(chart) {
                                const original = Chart.defaults.plugins.legend.labels.generateLabels;
                                const labels = original.call(this, chart);
                                
                                // Raccourcir les labels pour économiser l'espace
                                return labels.map(label => {
                                    label.text = label.text
                                        .replace('Prix x1 observé', 'Prix x1')
                                        .replace('Prix x10 observé', 'Prix x10') 
                                        .replace('Prix x100 observé', 'Prix x100')
                                        .replace('Prix x1000 observé', 'Prix x1000')
                                        .replace('Moyenne mobile x1', 'Moy. x1')
                                        .replace('Moyenne mobile x10', 'Moy. x10')
                                        .replace('Moyenne mobile x100', 'Moy. x100')
                                        .replace('Moyenne mobile x1000', 'Moy. x1000');
                                    return label;
                                });
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: { color: '#9CA3AF', maxTicksLimit: 8 },
                        grid: { color: '#374151' }
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
                        grid: { color: '#374151' }
                    }
                },
                ...this.optionsValue
            }
        });
        
        this.updatePointsInfo();
    }

    // Filtre par type de courbe (x1, x10, x100, x1000)
    filterChart(event) {
        const filterType = event.currentTarget.dataset.filter;
        const isActive = event.currentTarget.classList.contains('active');
        
        if (isActive) {
            this.enabledTypes = this.enabledTypes.filter(type => type !== filterType);
            event.currentTarget.classList.remove('active');
            event.currentTarget.classList.add('opacity-50');
        } else {
            if (!this.enabledTypes.includes(filterType)) {
                this.enabledTypes.push(filterType);
            }
            event.currentTarget.classList.add('active');
            event.currentTarget.classList.remove('opacity-50');
        }
        
        this.updateChart();
    }

    // Filtre par période
    changePeriod(event) {
        event.preventDefault();
        
        const newPeriod = event.currentTarget.dataset.period;
        
        // Mise à jour visuelle des boutons de période
        this.periodBtnTargets.forEach(btn => {
            btn.classList.remove('bg-orange-600', 'text-white');
            btn.classList.add('bg-gray-700', 'text-gray-300', 'hover:bg-gray-600');
        });
        
        event.currentTarget.classList.remove('bg-gray-700', 'text-gray-300', 'hover:bg-gray-600');
        event.currentTarget.classList.add('bg-orange-600', 'text-white');
        
        this.currentPeriod = newPeriod;
        this.updateChart();
    }

    updateChart() {
        this.chart.data = this.getFilteredData();
        this.chart.update();
        this.updatePointsInfo();
    }

    getFilteredData() {
        // 1. Filtrage temporel
        const periodFilteredData = this.filterDataByPeriod();
        
        // 2. Filtrage par type de courbe
        const filteredData = {
            labels: [...periodFilteredData.labels],
            datasets: periodFilteredData.datasets.filter(dataset => {
                // MODIFIÉ : Inclure x1000 dans le filtrage avec logique précise
                const isX1Dataset = dataset.label.includes('x1') && !dataset.label.includes('x10') && !dataset.label.includes('x100') && !dataset.label.includes('x1000');
                const isX10Dataset = dataset.label.includes('x10') && !dataset.label.includes('x100') && !dataset.label.includes('x1000');
                const isX100Dataset = dataset.label.includes('x100') && !dataset.label.includes('x1000');
                const isX1000Dataset = dataset.label.includes('x1000'); // NOUVEAU
                
                return (isX1Dataset && this.enabledTypes.includes('x1')) ||
                       (isX10Dataset && this.enabledTypes.includes('x10')) ||
                       (isX100Dataset && this.enabledTypes.includes('x100')) ||
                       (isX1000Dataset && this.enabledTypes.includes('x1000')); // NOUVEAU
            })
        };
        
        return filteredData;
    }

    filterDataByPeriod() {
        if (this.currentPeriod === 'all') {
            return this.originalData;
        }

        const now = new Date();
        let cutoffDate = new Date();
        
        switch (this.currentPeriod) {
            case '3':
                cutoffDate.setDate(now.getDate() - 3);
                break;
            case '7':
                cutoffDate.setDate(now.getDate() - 7);
                break;
            case '30':
                cutoffDate.setDate(now.getDate() - 30);
                break;
            default:
                return this.originalData;
        }

        // Convertir les labels en dates et filtrer
        const filteredIndices = [];
        this.originalData.labels.forEach((label, index) => {
            // Convertir le label en date (format: 'dd/mm' ou 'dd/mm/yy')
            const parts = label.split('/');
            let day = parseInt(parts[0]);
            let month = parseInt(parts[1]) - 1; // Les mois en JS commencent à 0
            let year = parts[2] ? (2000 + parseInt(parts[2])) : now.getFullYear();
            
            const labelDate = new Date(year, month, day);
            
            if (labelDate >= cutoffDate) {
                filteredIndices.push(index);
            }
        });

        return {
            labels: filteredIndices.map(i => this.originalData.labels[i]),
            datasets: this.originalData.datasets.map(dataset => ({
                ...dataset,
                data: filteredIndices.map(i => dataset.data[i])
            }))
        };
    }

    updatePointsInfo() {
        if (this.hasPointsInfoTarget) {
            const visiblePoints = this.chart.data.labels.length;
            this.pointsInfoTarget.textContent = `${visiblePoints} points`;
        }
    }

    disconnect() {
        if (this.chart) {
            this.chart.destroy();
        }
    }
}