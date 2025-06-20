// assets/controllers/turbo_progress_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        document.addEventListener('turbo:visit', this.showProgress);
        document.addEventListener('turbo:load', this.hideProgress);
        document.addEventListener('turbo:fetch-request-error', this.hideProgress);
    }

    disconnect() {
        document.removeEventListener('turbo:visit', this.showProgress);
        document.removeEventListener('turbo:load', this.hideProgress);
        document.removeEventListener('turbo:fetch-request-error', this.hideProgress);
    }

    showProgress = () => {
        this.element.classList.remove('opacity-0');
        this.element.classList.add('opacity-100');
    }

    hideProgress = () => {
        this.element.classList.remove('opacity-100');
        this.element.classList.add('opacity-0');
    }
}