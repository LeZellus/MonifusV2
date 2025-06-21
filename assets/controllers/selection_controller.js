// assets/controllers/selection_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["select"]
    static values = { selectedId: Number }

    connect() {
        // Auto-sélection si un seul personnage
        if (this.hasSelectTarget) {
            const options = this.selectTarget.options;
            if (options.length === 2 && !this.selectedIdValue) {
                const firstId = options[1]?.value;
                if (firstId) this.selectTarget.value = firstId;
            }
        }
    }

    // Gestion simple des erreurs via flash messages
    showError(message) {
        const notification = document.createElement('div');
        notification.className = 'fixed top-4 right-4 bg-red-600 text-white px-4 py-2 rounded-lg z-50 animate-slide-in';
        notification.textContent = message;
        document.body.appendChild(notification);
        
        setTimeout(() => notification.remove(), 3000);
    }
}