import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["quantityInput", "priceInput", "profitPreview", "quantityDisplay", "totalProfit", "remainingStock"]
    static values = { 
        buyPrice: Number,
        totalStock: Number 
    }

    connect() {
        this.updatePreview();
    }

    updatePreview() {
        const quantity = parseInt(this.quantityInputTarget.value) || 0;
        const price = parseInt(this.priceInputTarget.value) || 0;
        
        if (quantity > 0 && price > 0) {
            this.showPreview();
            
            const totalProfit = (price - this.buyPriceValue) * quantity;
            const remaining = this.totalStockValue - quantity;
            
            this.quantityDisplayTarget.textContent = `${quantity} lots`;
            this.totalProfitTarget.textContent = `${(totalProfit / 1000).toLocaleString()}k`;
            
            if (remaining > 0) {
                this.remainingStockTarget.textContent = `Il restera ${remaining} lots en stock`;
            } else {
                this.remainingStockTarget.textContent = 'Lot entièrement vendu';
            }
        } else {
            this.hidePreview();
        }
    }

    showPreview() {
        this.profitPreviewTarget.style.display = 'block';
    }

    hidePreview() {
        this.profitPreviewTarget.style.display = 'none';
    }
}