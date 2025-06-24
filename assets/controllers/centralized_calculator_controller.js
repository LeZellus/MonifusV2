import { Controller } from '@hotwired/stimulus';

/**
 * Contrôleur centralisé pour tous les calculs de profit
 * Remplace profit_calculator_controller.js et sale_calculator_controller.js
 */
export default class extends Controller {
    static targets = [
        "lotSize", "buyPrice", "sellPrice", "quantityInput", "priceInput",
        "investment", "profitPerLot", "totalProfit", "roi",
        "profitPreview", "quantityDisplay", "remainingStock"
    ]
    
    static values = { 
        buyPrice: Number,
        totalStock: Number,
        mode: String // 'lot' | 'sale'
    }

    connect() {
        this.updateCalculations();
    }

    /**
     * Méthode principale appelée à chaque changement
     */
    updateCalculations() {
        const mode = this.modeValue || 'lot';
        
        if (mode === 'sale') {
            this.updateSaleCalculations();
        } else {
            this.updateLotCalculations();
        }
    }

    /**
     * Calculs pour formulaire de lot (création/modification)
     */
    updateLotCalculations() {
        const data = this.extractLotData();
        const metrics = this.calculateMetrics(data);
        
        this.updateDisplay(metrics);
    }

    /**
     * Calculs pour formulaire de vente
     */
    updateSaleCalculations() {
        const data = this.extractSaleData();
        const metrics = this.calculateSaleMetrics(data);
        
        this.updateSaleDisplay(metrics);
    }

    // ===== EXTRACTION DES DONNÉES =====

    extractLotData() {
        return {
            quantity: this.getInputValue('lotSize', 'input[name*="lotSize"]'),
            buyPrice: this.getInputValue('buyPrice', 'input[name*="buyPricePerLot"]'),
            sellPrice: this.getInputValue('sellPrice', 'input[name*="sellPricePerLot"]')
        };
    }

    extractSaleData() {
        return {
            quantity: this.getInputValue('quantityInput'),
            sellPrice: this.getInputValue('priceInput'),
            buyPrice: this.buyPriceValue || 0,
            totalStock: this.totalStockValue || 0
        };
    }

    getInputValue(targetName, fallbackSelector = null) {
        // Essaie d'abord le target Stimulus
        if (this.hasTarget(targetName)) {
            return parseInt(this.getTarget(targetName).value) || 0;
        }
        
        // Sinon, essaie le sélecteur de fallback
        if (fallbackSelector) {
            const element = document.querySelector(fallbackSelector);
            return element ? parseInt(element.value) || 0 : 0;
        }
        
        return 0;
    }

    // ===== CALCULS CENTRALISÉS =====

    calculateMetrics({ quantity, buyPrice, sellPrice }) {
        const investment = quantity * buyPrice;
        const profitPerLot = sellPrice - buyPrice;
        const totalProfit = quantity * profitPerLot;
        const roi = buyPrice > 0 ? (profitPerLot / buyPrice) * 100 : 0;

        return {
            investment,
            profitPerLot,
            totalProfit,
            roi,
            formattedInvestment: this.formatKamas(investment),
            formattedProfitPerLot: this.formatKamas(profitPerLot),
            formattedTotalProfit: this.formatKamas(totalProfit),
            profitClass: this.getProfitClass(totalProfit)
        };
    }

    calculateSaleMetrics({ quantity, sellPrice, buyPrice, totalStock }) {
        const investment = quantity * buyPrice;
        const totalProfit = quantity * (sellPrice - buyPrice);
        const remainingStock = Math.max(0, totalStock - quantity);
        
        return {
            investment,
            totalProfit,
            remainingStock,
            quantity,
            formattedTotalProfit: this.formatKamas(totalProfit),
            profitClass: this.getProfitClass(totalProfit)
        };
    }

    // ===== MISE À JOUR DE L'AFFICHAGE =====

    updateDisplay(metrics) {
        this.updateTarget('investment', metrics.formattedInvestment);
        this.updateTarget('profitPerLot', metrics.formattedProfitPerLot);
        this.updateTarget('totalProfit', metrics.formattedTotalProfit, metrics.profitClass);
        
        // ROI si disponible
        if (this.hasTarget('roi')) {
            this.updateTarget('roi', `${metrics.roi.toFixed(1)}%`);
        }
    }

    updateSaleDisplay(metrics) {
        if (metrics.quantity > 0 && metrics.totalProfit !== 0) {
            this.showPreview();
            
            this.updateTarget('quantityDisplay', `${metrics.quantity} lots`);
            this.updateTarget('totalProfit', metrics.formattedTotalProfit, metrics.profitClass);
            
            if (this.hasTarget('remainingStock')) {
                const message = metrics.remainingStock > 0 
                    ? `Il restera ${metrics.remainingStock} lots en stock`
                    : 'Lot entièrement vendu';
                this.updateTarget('remainingStock', message);
            }
        } else {
            this.hidePreview();
        }
    }

    updateTarget(targetName, value, className = null) {
        if (this.hasTarget(targetName)) {
            const element = this.getTarget(targetName);
            element.textContent = value || '-';
            
            if (className) {
                element.className = `font-bold ${className}`;
            }
        }
    }

    // ===== UTILITAIRES =====

    formatKamas(amount) {
        if (amount === 0) return '-';
        
        const absAmount = Math.abs(amount);
        if (absAmount >= 1000000) {
            return (amount / 1000000).toFixed(1) + 'M';
        } else if (absAmount >= 1000) {
            return Math.round(amount / 1000).toLocaleString() + 'k';
        }
        return amount.toLocaleString();
    }

    getProfitClass(profit) {
        return profit >= 0 ? 'text-green-400' : 'text-red-400';
    }

    showPreview() {
        if (this.hasTarget('profitPreview')) {
            this.profitPreviewTarget.style.display = 'block';
        }
    }

    hidePreview() {
        if (this.hasTarget('profitPreview')) {
            this.profitPreviewTarget.style.display = 'none';
        }
    }

    // ===== HELPERS POUR LA COMPATIBILITÉ =====

    getTarget(name) {
        return this[`${name}Target`];
    }

    hasTarget(name) {
        return this[`has${name.charAt(0).toUpperCase() + name.slice(1)}Target`];
    }

    // ===== MÉTHODES PUBLIQUES POUR API =====

    /**
     * Méthode pour calculer via JavaScript externe
     */
    calculate(quantity, buyPrice, sellPrice) {
        return this.calculateMetrics({ quantity, buyPrice, sellPrice });
    }

    /**
     * Met à jour manuellement les calculs (pour usage externe)
     */
    refresh() {
        this.updateCalculations();
    }
}