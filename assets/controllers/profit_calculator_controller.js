import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["lotSize", "buyPrice", "sellPrice", "investment", "profitPerLot", "totalProfit"]
    static classes = ["profit", "loss"]

    connect() {
        console.log('Profit calculator connected!');
        console.log('Available targets:', this.targets);
        this.updateProfit();
    }

    updateProfit() {
        console.log('updateProfit called');
        
        // Récupérer les valeurs de manière plus flexible
        const lotSizeValue = this.hasLotSizeTarget ? this.lotSizeTarget.value : 
                            document.querySelector('input[name*="lotSize"]')?.value;
        const buyPriceValue = this.hasBuyPriceTarget ? this.buyPriceTarget.value : 
                             document.querySelector('input[name*="buyPricePerLot"]')?.value;
        const sellPriceValue = this.hasSellPriceTarget ? this.sellPriceTarget.value : 
                              document.querySelector('input[name*="sellPricePerLot"]')?.value;
        
        const lotSize = parseInt(lotSizeValue) || 0;
        const buyPrice = parseInt(buyPriceValue) || 0;
        const sellPrice = parseInt(sellPriceValue) || 0;
        
        console.log('Values:', { lotSize, buyPrice, sellPrice });
        
        const investment = lotSize * buyPrice;
        const profitPerLot = sellPrice - buyPrice;
        const totalProfit = lotSize * profitPerLot;
        
        console.log('Calculated:', { investment, profitPerLot, totalProfit });
        
        // Mettre à jour l'affichage
        if (this.hasInvestmentTarget) {
            this.investmentTarget.textContent = investment > 0 ? this.formatKamas(investment) : '-';
        }
        if (this.hasProfitPerLotTarget) {
            this.profitPerLotTarget.textContent = profitPerLot !== 0 ? this.formatKamas(profitPerLot) : '-';
        }
        if (this.hasTotalProfitTarget) {
            this.totalProfitTarget.textContent = totalProfit !== 0 ? this.formatKamas(totalProfit) : '-';
            this.totalProfitTarget.className = totalProfit >= 0 ? 'text-green-400 font-bold' : 'text-red-400 font-bold';
        }
    }

    formatKamas(amount) {
        if (Math.abs(amount) >= 1000) {
            return (amount / 1000).toLocaleString() + 'k';
        }
        return amount.toLocaleString();
    }
}