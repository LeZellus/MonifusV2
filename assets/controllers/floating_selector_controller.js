// assets/controllers/floating_selector_controller.js
import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["panel", "overlay", "trigger"]

    connect() {
        // Fermer le panel avec Escape
        this.boundHandleKeydown = this.handleKeydown.bind(this)

        // Fermer le panel si on clique ailleurs
        this.boundCloseOnClickOutside = this.closeOnClickOutside.bind(this)
    }

    disconnect() {
        document.removeEventListener('keydown', this.boundHandleKeydown)
        document.removeEventListener('click', this.boundCloseOnClickOutside)
    }

    togglePanel() {
        if (this.panelTarget.classList.contains('hidden')) {
            this.openPanel()
        } else {
            this.closePanel()
        }
    }

    openPanel() {
        // Vérifier s'il y a assez de place en hauteur
        const windowHeight = window.innerHeight
        const triggerRect = this.triggerTarget.getBoundingClientRect()
        const panelHeight = 400 // hauteur approximative du panel

        // Si pas assez de place au-dessus, le repositionner en dessous
        if (triggerRect.top < panelHeight) {
            this.panelTarget.classList.remove('bottom-full', 'mb-4')
            this.panelTarget.classList.add('top-full', 'mt-4')
        } else {
            this.panelTarget.classList.remove('top-full', 'mt-4')
            this.panelTarget.classList.add('bottom-full', 'mb-4')
        }

        // Montrer l'overlay et le panel
        this.overlayTarget.classList.remove('hidden')
        this.panelTarget.classList.remove('hidden')

        // Animation d'entrée fluide
        requestAnimationFrame(() => {
            this.panelTarget.style.transform = 'scale(0.95) translateY(10px)'
            this.panelTarget.style.opacity = '0'
            this.overlayTarget.style.opacity = '0'

            requestAnimationFrame(() => {
                this.panelTarget.style.transition = 'all 0.2s cubic-bezier(0.16, 1, 0.3, 1)'
                this.overlayTarget.style.transition = 'opacity 0.2s ease-out'

                this.panelTarget.style.transform = 'scale(1) translateY(0)'
                this.panelTarget.style.opacity = '1'
                this.overlayTarget.style.opacity = '1'
            })
        })

        // Écouter les interactions après un délai
        setTimeout(() => {
            document.addEventListener('keydown', this.boundHandleKeydown)
            document.addEventListener('click', this.boundCloseOnClickOutside)
        }, 100)
    }

    closePanel() {
        // Animation de sortie
        this.panelTarget.style.transition = 'all 0.15s cubic-bezier(0.4, 0, 1, 1)'
        this.overlayTarget.style.transition = 'opacity 0.15s ease-in'

        this.panelTarget.style.transform = 'scale(0.95) translateY(10px)'
        this.panelTarget.style.opacity = '0'
        this.overlayTarget.style.opacity = '0'

        // Masquer après l'animation
        setTimeout(() => {
            this.panelTarget.classList.add('hidden')
            this.overlayTarget.classList.add('hidden')

            // Réinitialiser les styles
            this.panelTarget.style.transform = ''
            this.panelTarget.style.opacity = ''
            this.panelTarget.style.transition = ''
            this.overlayTarget.style.opacity = ''
            this.overlayTarget.style.transition = ''
        }, 150)

        // Arrêter d'écouter les événements
        document.removeEventListener('keydown', this.boundHandleKeydown)
        document.removeEventListener('click', this.boundCloseOnClickOutside)
    }

    closeOnClickOutside(event) {
        // Ne pas fermer si on clique à l'intérieur du sélecteur
        if (this.element.contains(event.target)) {
            return
        }

        this.closePanel()
    }

    handleKeydown(event) {
        if (event.key === 'Escape') {
            event.preventDefault()
            this.closePanel()
        }
    }

    // Méthode pour fermer automatiquement lors de navigation
    handlePageChange() {
        if (!this.panelTarget.classList.contains('hidden')) {
            this.closePanel()
        }
    }
}