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
        this.enabledTypes = ['x1', 'x10', 'x100'];
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
                        labels: { color: '#fff' }
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

    // Filtre par type de courbe (x1, x10, x100)
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
    filterPeriod(event) {
        event.preventDefault();
        
        const newPeriod = event.currentTarget.dataset.period;
        
        // Mise à jour visuelle des boutons de période
        this.periodBtnTargets.forEach(btn => {
            btn.classList.remove('bg-orange-600', 'text-white');
            btn.classList.add('bg-gray-700', 'text-gray-300');
        });
        
        event.currentTarget.classList.remove('bg-gray-700', 'text-gray-300');
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
        const typeFilteredDatasets = periodFilteredData.datasets.filter(dataset => {
            const label = dataset.label.toLowerCase();
            
            return this.enabledTypes.some(type => {
                if (type === 'x1') {
                    return label.includes('x1') && !label.includes('x10') && !label.includes('x100');
                } else if (type === 'x10') {
                    return label.includes('x10') && !label.includes('x100');
                } else if (type === 'x100') {
                    return label.includes('x100');
                }
                return false;
            });
        });

        return {
            labels: periodFilteredData.labels,
            datasets: typeFilteredDatasets
        };
    }

    filterDataByPeriod() {
        if (this.currentPeriod === 'all') {
            return this.originalData;
        }

        const now = new Date();
        const cutoffDate = new Date(now);

        switch (this.currentPeriod) {
            case '7d':
                cutoffDate.setDate(now.getDate() - 7);
                break;
            case '30d':
                cutoffDate.setDate(now.getDate() - 30);
                break;
            case '3m':
                cutoffDate.setMonth(now.getMonth() - 3);
                break;
            case '6m':
                cutoffDate.setMonth(now.getMonth() - 6);
                break;
            case '1y':
                cutoffDate.setFullYear(now.getFullYear() - 1);
                break;
            default:
                return this.originalData;
        }

        console.log(`Filtrage période "${this.currentPeriod}"`);
        console.log('Date limite:', cutoffDate.toDateString());
        console.log('Données originales:', this.originalData.labels);

        // Filtrer les labels et datasets selon la période
        const filteredIndices = [];
        const filteredLabels = [];
        
        this.originalData.labels.forEach((label, index) => {
            const labelDate = this.parseLabelToDate(label);
            const isIncluded = labelDate >= cutoffDate;
            
            console.log(`Index ${index}: "${label}" → ${labelDate.toDateString()} → ${isIncluded ? 'INCLUS' : 'EXCLU'}`);
            
            if (isIncluded) {
                filteredIndices.push(index);
                filteredLabels.push(label);
            }
        });

        console.log('Indices conservés:', filteredIndices);
        console.log('Labels conservés:', filteredLabels);

        // Filtrer les données de chaque dataset
        const filteredDatasets = this.originalData.datasets.map(dataset => ({
            ...dataset,
            data: dataset.data.filter((_, index) => filteredIndices.includes(index))
        }));

        return {
            labels: filteredLabels,
            datasets: filteredDatasets
        };
    }

    parseLabelToDate(label) {
        // Debug pour voir les labels reçus
        console.log('Parsing label:', label);
        
        // Convertir "d/m" ou "d/m/y" en Date
        const parts = label.split('/');
        if (parts.length < 2) {
            console.warn('Label invalide:', label);
            return new Date(0); // Date très ancienne pour être exclus
        }
        
        const day = parseInt(parts[0]);
        const month = parseInt(parts[1]) - 1; // JS months are 0-indexed
        
        // Gestion de l'année
        let year;
        if (parts[2]) {
            // Format d/m/y ou d/m/yy ou d/m/yyyy
            const yearPart = parseInt(parts[2]);
            if (yearPart < 50) {
                year = 2000 + yearPart; // 25 → 2025
            } else if (yearPart < 100) {
                year = 1900 + yearPart; // 95 → 1995
            } else {
                year = yearPart; // 2025 → 2025
            }
        } else {
            // Format d/m → année courante
            year = new Date().getFullYear();
        }
        
        const parsedDate = new Date(year, month, day);
        console.log(`Label "${label}" → Date: ${parsedDate.toDateString()}`);
        
        return parsedDate;
    }

    updatePointsInfo() {
        if (this.hasPointsInfoTarget) {
            const filtered = this.getFilteredData();
            const totalPoints = this.originalData.labels.length;
            const displayedPoints = filtered.labels.length;
            
            if (totalPoints !== displayedPoints) {
                this.pointsInfoTarget.textContent = `${displayedPoints} / ${totalPoints} points`;
            } else {
                this.pointsInfoTarget.textContent = `${totalPoints} points`;
            }
        }
    }

    disconnect() {
        if (this.chart) {
            this.chart.destroy();
        }
    }
}