// assets/controllers/flash_message_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { timeout: Number }

    connect() {
        // Animation d'apparition fluide
        this.element.style.opacity = '0';
        this.element.style.transform = 'translateX(100%)';
        this.element.style.transition = 'all 0.3s ease-out';

        // Déclencher l'animation après un micro-délai
        requestAnimationFrame(() => {
            this.element.style.opacity = '1';
            this.element.style.transform = 'translateX(0)';
        });

        if (this.timeoutValue > 0) {
            this.timer = setTimeout(() => {
                this.close();
            }, this.timeoutValue);
        }
    }

    close() {
        this.element.style.opacity = '0';
        this.element.style.transform = 'translateX(100%)';
        setTimeout(() => {
            this.element.remove();
        }, 300);
    }

    disconnect() {
        if (this.timer) {
            clearTimeout(this.timer);
        }
    }
}