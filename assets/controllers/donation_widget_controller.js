// assets/controllers/donation_widget_controller.js
import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    connect() {
        // Vérifier si le widget a été fermé récemment
        const closedData = localStorage.getItem('donation-widget-closed')
        if (closedData) {
            const { timestamp } = JSON.parse(closedData)
            const now = Date.now()
            const oneHour = 60 * 60 * 1000 // 1 heure en millisecondes
            
            // Si fermé il y a moins d'1 heure, ne pas afficher
            if (now - timestamp < oneHour) {
                this.element.style.display = 'none'
                return
            } else {
                // Si plus d'1 heure, supprimer l'entrée pour permettre l'affichage
                localStorage.removeItem('donation-widget-closed')
            }
        }

        // Animation simple : slide depuis la droite
        this.element.style.transform = 'translateX(100%)'
        this.element.style.opacity = '0'

        // Apparition après 3 secondes
        setTimeout(() => {
            this.element.style.transition = 'all 0.5s ease-out'
            this.element.style.transform = 'translateX(0)'
            this.element.style.opacity = '1'
        }, 3000)
    }

    close() {
        this.hide()
        // Enregistrer la fermeture avec timestamp
        const closedData = {
            timestamp: Date.now()
        }
        localStorage.setItem('donation-widget-closed', JSON.stringify(closedData))
    }

    hide() {
        this.element.style.transition = 'all 0.3s ease-out'
        this.element.style.transform = 'translateX(100%)'
        this.element.style.opacity = '0'
        
        setTimeout(() => {
            this.element.style.display = 'none'
        }, 300)
    }

    trackDonation() {
        console.log('Donation clicked')
        
        // Considérer un clic comme une interaction positive  
        // Garder 6 heures pour les donations (éviter le spam après contribution)
        const closedData = {
            timestamp: Date.now(),
            donated: true
        }
        localStorage.setItem('donation-widget-closed', JSON.stringify(closedData))
    }
}