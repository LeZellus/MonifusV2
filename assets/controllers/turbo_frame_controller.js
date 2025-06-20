// assets/controllers/turbo_frame_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        // Ajouter des classes de chargement
        this.element.addEventListener('turbo:before-fetch-request', this.addLoading);
        this.element.addEventListener('turbo:frame-load', this.removeLoading);
    }

    addLoading = () => {
        this.element.classList.add('opacity-50', 'pointer-events-none');
    }

    removeLoading = () => {
        this.element.classList.remove('opacity-50', 'pointer-events-none');
    }

    disconnect() {
        this.element.removeEventListener('turbo:before-fetch-request', this.addLoading);
        this.element.removeEventListener('turbo:frame-load', this.removeLoading);
    }
}