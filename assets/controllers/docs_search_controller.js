// assets/controllers/docs_search_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["menu"]

    search(event) {
        const query = event.target.value.toLowerCase().trim();
        const menuItems = this.menuTarget.querySelectorAll('a[data-search-content]');
        
        if (query === '') {
            // Afficher tous les items si recherche vide
            menuItems.forEach(item => {
                item.style.display = 'block';
            });
            return;
        }
        
        // Filtrer les items selon la recherche
        menuItems.forEach(item => {
            const searchContent = item.getAttribute('data-search-content').toLowerCase();
            const itemText = item.textContent.toLowerCase();
            
            const matches = searchContent.includes(query) || itemText.includes(query);
            item.style.display = matches ? 'block' : 'none';
        });
    }
}