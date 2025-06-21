// assets/controllers/docs_search_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["menu"]

    connect() {
        // Debug pour vérifier la connexion
        console.log('docs-search controller connected');
    }

    search(event) {
        const query = event.target.value.toLowerCase().trim();
        const menuItems = this.menuTarget.querySelectorAll('a[data-search-content]');
        
        console.log(`Recherche: "${query}"`);
        console.log(`Items trouvés: ${menuItems.length}`);
        
        if (query === '') {
            // Afficher tous les items si recherche vide
            menuItems.forEach(item => {
                item.style.display = 'block';
            });
            return;
        }
        
        // Filtrer les items selon la recherche
        let visibleCount = 0;
        menuItems.forEach(item => {
            const searchContent = item.getAttribute('data-search-content').toLowerCase();
            const itemText = item.textContent.toLowerCase();
            
            const matches = searchContent.includes(query) || itemText.includes(query);
            item.style.display = matches ? 'block' : 'none';
            
            if (matches) visibleCount++;
        });
        
        console.log(`Items visibles après filtrage: ${visibleCount}`);
    }
}