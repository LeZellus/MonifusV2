import { Controller } from '@hotwired/stimulus';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

export default class extends Controller {
    static values = { 
        data: Object,
        type: String,
        options: Object 
    }

    connect() {
        this.chart = new Chart(this.element, {
            type: this.typeValue || 'line',
            data: this.dataValue,
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
                                // Toujours afficher des entiers
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

    disconnect() {
        if (this.chart) {
            this.chart.destroy();
        }
    }
}