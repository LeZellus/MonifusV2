// assets/controllers/flash_message_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { timeout: Number }

    connect() {
        if (this.timeoutValue > 0) {
            this.timer = setTimeout(() => {
                this.close();
            }, this.timeoutValue);
        }
    }

    close() {
        this.element.classList.add('animate-slide-out');
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