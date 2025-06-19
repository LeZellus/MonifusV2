// assets/controllers/character_selector_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        characters: Array,
        selectedId: Number
    }

    connect() {
        if (this.charactersValue.length === 1 && this.selectedIdValue === null) {
            this.changeCharacter(this.charactersValue[0].id);
        }
    }

    change(event) {
        const characterId = event.target.value;
        this.changeCharacter(characterId);
    }

    async changeCharacter(characterId) {
        try {
            console.log("coucou")
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
                console.error('Erreur lors du changement de personnage');
            }
        } catch (error) {
            console.error('Erreur réseau:', error);
        }
    }
}
