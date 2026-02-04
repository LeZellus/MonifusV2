import { Controller } from '@hotwired/stimulus';

/**
 * Contrôleur centralisé pour tous les calculs de profit
 * Gère les calculs avec buyUnit (achat) et saleUnit (revente) séparés
 */
export default class extends Controller {
    static targets = [
        "lotSize", "buyPrice", "sellPrice", "buyUnit", "saleUnit",
        "quantityInput", "priceInput",
        "investment", "totalProfit", "totalQuantity", "sellLotCount", "totalRevenue", "roi",
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
        const metrics = this.calculateLotMetrics(data);

        this.updateLotDisplay(metrics);
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
            lotSize: this.getFieldValue('lotSize'),
            buyPrice: this.getFieldValue('buyPrice'),
            sellPrice: this.getFieldValue('sellPrice'),
            buyUnit: this.getFieldValue('buyUnit', 1),
            saleUnit: this.getFieldValue('saleUnit', 1)
        };
    }

    extractSaleData() {
        return {
            quantity: this.getFieldValue('quantityInput'),
            sellPrice: this.getFieldValue('priceInput'),
            buyPrice: this.buyPriceValue || 0,
            totalStock: this.totalStockValue || 0
        };
    }

    getFieldValue(targetName, defaultValue = 0) {
        // Utilise directement les targets Stimulus
        const targetProperty = `${targetName}Target`;
        const hasTargetProperty = `has${targetName.charAt(0).toUpperCase() + targetName.slice(1)}Target`;

        if (this[hasTargetProperty] && this[targetProperty]) {
            return parseInt(this[targetProperty].value) || defaultValue;
        }

        return defaultValue;
    }

    // ===== CALCULS CENTRALISÉS =====

    /**
     * Calcule les métriques pour un lot avec buyUnit et saleUnit séparés
     *
     * Exemple:
     * - buyUnit = 1000 (j'achète par 1000)
     * - lotSize = 3 (j'achète 3 lots)
     * - buyPrice = 9000 (9000k par lot de 1000)
     * - saleUnit = 100 (je revends par 100)
     * - sellPrice = 1600 (1600k par lot de 100)
     *
     * Résultat:
     * - totalItems = 3 × 1000 = 3000 items
     * - investment = 3 × 9000 = 27 000k
     * - sellLotCount = 3000 / 100 = 30 lots à revendre
     * - totalRevenue = 30 × 1600 = 48 000k
     * - totalProfit = 48 000 - 27 000 = 21 000k
     */
    calculateLotMetrics({ lotSize, buyPrice, sellPrice, buyUnit, saleUnit }) {
        // Quantité totale d'items = nombre de lots achetés × taille lot achat
        const totalItems = lotSize * buyUnit;

        // Investissement total = nombre de lots achetés × prix par lot
        const investment = lotSize * buyPrice;

        // Nombre de lots à revendre = total items / taille lot revente
        const sellLotCount = saleUnit > 0 ? Math.floor(totalItems / saleUnit) : 0;

        // Revenu total = nombre de lots revente × prix par lot revente
        const totalRevenue = sellLotCount * sellPrice;

        // Profit total = revenu - investissement
        const totalProfit = totalRevenue - investment;

        // ROI = profit / investissement × 100
        const roi = investment > 0 ? (totalProfit / investment) * 100 : 0;

        return {
            totalItems,
            investment,
            sellLotCount,
            totalRevenue,
            totalProfit,
            roi,
            formattedTotalItems: totalItems.toLocaleString('fr-FR'),
            formattedInvestment: this.formatKamas(investment, true),
            formattedSellLotCount: sellLotCount.toLocaleString('fr-FR'),
            formattedTotalRevenue: this.formatKamas(totalRevenue, true),
            formattedTotalProfit: this.formatKamas(totalProfit, true),
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

    updateLotDisplay(metrics) {
        // Mise à jour directe des targets
        if (this.hasTotalQuantityTarget) {
            this.totalQuantityTarget.textContent = metrics.formattedTotalItems;
        }
        if (this.hasInvestmentTarget) {
            this.investmentTarget.textContent = metrics.formattedInvestment;
        }
        if (this.hasSellLotCountTarget) {
            this.sellLotCountTarget.textContent = metrics.formattedSellLotCount;
        }
        if (this.hasTotalRevenueTarget) {
            this.totalRevenueTarget.textContent = metrics.formattedTotalRevenue;
        }
        if (this.hasTotalProfitTarget) {
            this.totalProfitTarget.textContent = metrics.formattedTotalProfit;
            this.totalProfitTarget.className = `text-3xl font-bold ${metrics.profitClass}`;
        }
        if (this.hasRoiTarget) {
            this.roiTarget.textContent = `${metrics.roi.toFixed(1)}%`;
        }
    }

    updateSaleDisplay(metrics) {
        if (metrics.quantity > 0 && metrics.totalProfit !== 0) {
            this.showPreview();

            this.updateTarget('quantityDisplay', `${metrics.quantity} lots`);
            this.updateTarget('totalProfit', metrics.formattedTotalProfit, metrics.profitClass);

            if (this.hasRemainingStockTarget) {
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
        const targetProperty = `${targetName}Target`;
        const hasTargetProperty = `has${targetName.charAt(0).toUpperCase() + targetName.slice(1)}Target`;

        if (this[hasTargetProperty] && this[targetProperty]) {
            const element = this[targetProperty];
            element.textContent = value || '0';

            if (className) {
                element.className = `font-bold ${className}`;
            }
        }
    }

    // ===== UTILITAIRES =====

    formatKamas(amount, showZero = false) {
        if (amount === null || amount === undefined) return '-';
        if (amount === 0) return showZero ? '0' : '-';

        const abs = Math.abs(amount);
        const sign = amount < 0 ? '-' : '';

        if (abs >= 1000000000) {
            const kk = Math.floor(abs / 1000000);
            return `${sign}${kk}kk`;
        }

        if (abs >= 1000000) {
            const millions = Math.floor(abs / 1000000);
            const remainder = abs % 1000000;
            const milliers = Math.floor(remainder / 1000);

            if (milliers > 0) {
                return `${sign}${millions}m${milliers}k`;
            } else {
                return `${sign}${millions}m`;
            }
        }

        if (abs >= 1000) {
            return `${sign}${abs.toLocaleString('fr-FR')}`;
        }

        return `${sign}${abs}`;
    }

    getProfitClass(profit) {
        return profit >= 0 ? 'text-green-400' : 'text-red-400';
    }

    showPreview() {
        if (this.hasProfitPreviewTarget) {
            this.profitPreviewTarget.style.display = 'block';
        }
    }

    hidePreview() {
        if (this.hasProfitPreviewTarget) {
            this.profitPreviewTarget.style.display = 'none';
        }
    }

    // ===== MÉTHODES PUBLIQUES POUR API =====

    calculate(lotSize, buyPrice, sellPrice, buyUnit = 1, saleUnit = 1) {
        return this.calculateLotMetrics({ lotSize, buyPrice, sellPrice, buyUnit, saleUnit });
    }

    refresh() {
        this.updateCalculations();
    }
}
