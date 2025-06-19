import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["select"]
    static values = { 
        selectedId: Number 
    }

    connect() {
        // Auto-sélection si un seul personnage et aucun sélectionné
        const selectElement = this.selectTarget;
        if (selectElement && selectElement.options.length === 2 && !this.selectedIdValue) { // 2 car il y a l'option par défaut
            const firstCharacterId = selectElement.options[1]?.value;
            if (firstCharacterId) {
                this.changeCharacter(firstCharacterId);
            }
        }
    }

    async change(event) {
        const characterId = event.target.value;
        if (characterId) {
            await this.changeCharacter(characterId);
        }
    }

    async changeCharacter(characterId) {
        try {
            const response = await fetch(`/character/select/${characterId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (response.ok) {
                window.location.reload();
            } else {
                console.error('Erreur lors du changement de personnage:', response.status);
                this.showError('Erreur lors du changement de personnage');
            }
        } catch (error) {
            console.error('Erreur réseau:', error);
            this.showError('Erreur réseau lors du changement de personnage');
        }
    }

    showError(message) {
        // Simple notification d'erreur - vous pouvez améliorer cela
        const notification = document.createElement('div');
        notification.className = 'bg-red-600 text-white px-4 py-2 rounded-lg fixed top-4 right-4 z-50';
        notification.textContent = message;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }
}