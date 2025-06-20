// assets/controllers/faq_accordion_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["content", "icon"]

    toggle(event) {
        const button = event.currentTarget;
        const targetId = button.getAttribute('data-target');
        const content = this.element.querySelector(`[data-question="${targetId}"]`);
        const icon = button.querySelector('[data-faq-accordion-target="icon"]');
        
        if (content.classList.contains('hidden')) {
            // Ouvrir
            content.classList.remove('hidden');
            icon.style.transform = 'rotate(180deg)';
        } else {
            // Fermer
            content.classList.add('hidden');
            icon.style.transform = 'rotate(0deg)';
        }
    }
}