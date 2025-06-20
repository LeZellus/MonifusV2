import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["menu", "button"]

    connect() {
        this.clickOutsideHandler = this.handleClickOutside.bind(this);
    }

    toggle() {
        const isHidden = this.menuTarget.classList.contains('hidden');
        
        if (isHidden) {
            this.open();
        } else {
            this.close();
        }
    }

    open() {
        this.menuTarget.classList.remove('hidden');
        // Ajouter l'écouteur de clic extérieur quand le menu s'ouvre
        document.addEventListener('click', this.clickOutsideHandler);
    }

    close() {
        this.menuTarget.classList.add('hidden');
        // Retirer l'écouteur quand le menu se ferme
        document.removeEventListener('click', this.clickOutsideHandler);
    }

    handleClickOutside(event) {
        // Fermer si le clic est en dehors du bouton et du menu
        if (!this.element.contains(event.target)) {
            this.close();
        }
    }

    disconnect() {
        // Nettoyage au démontage du contrôleur
        document.removeEventListener('click', this.clickOutsideHandler);
    }
}