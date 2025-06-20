// assets/controllers/character_selector_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { 
        selectedId: Number 
    }

    connect() {
        console.log('Character selector connected!');
        // Plus besoin d'auto-sélection car on a supprimé le select
    }

    // Méthode pour sélectionner un personnage (si nécessaire)
    async selectCharacter(characterId) {
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
        // Simple notification d'erreur
        const notification = document.createElement('div');
        notification.className = 'bg-red-600 text-white px-4 py-2 rounded-lg fixed top-4 right-4 z-50';
        notification.textContent = message;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }
}