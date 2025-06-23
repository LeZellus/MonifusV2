// assets/controllers/toggle_controller.js
import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ["input"]
    static values = { 
        onValue: String, 
        offValue: String 
    }

    connect() {
        console.log('Toggle connecté')
        console.log('Input element:', this.inputTarget)
        console.log('Valeur initiale:', this.inputTarget.value)
        console.log('Type de la valeur:', typeof this.inputTarget.value)
        console.log('On value:', this.onValueValue)
        console.log('Off value:', this.offValueValue)
        
        // Initialiser l'état visuel selon la valeur actuelle
        this.updateVisualState()
    }

    switch() {
        console.log('=== AVANT SWITCH ===')
        console.log('Valeur actuelle:', this.inputTarget.value)
        console.log('Type:', typeof this.inputTarget.value)
        
        const currentValue = this.inputTarget.value
        const newValue = currentValue === this.onValueValue ? this.offValueValue : this.onValueValue
        
        console.log('Nouvelle valeur calculée:', newValue)
        
        // Mettre à jour la valeur du champ
        this.inputTarget.value = newValue
        
        console.log('=== APRÈS SWITCH ===')
        console.log('Valeur dans le champ:', this.inputTarget.value)
        
        // Déclencher l'événement change pour Symfony
        this.inputTarget.dispatchEvent(new Event('change', { bubbles: true }))
        
        // Mettre à jour l'état visuel
        this.updateVisualState()
    }

    updateVisualState() {
        const currentValue = this.inputTarget.value
        const isOn = currentValue === this.onValueValue
        
        console.log('=== UPDATE VISUAL ===')
        console.log('Valeur actuelle:', currentValue)
        console.log('On value:', this.onValueValue) 
        console.log('Est activé (isOn):', isOn)
        
        const button = this.element.querySelector('.toggle-switch')
        const labelOff = this.element.querySelector('.toggle-label-off')
        const labelOn = this.element.querySelector('.toggle-label-on')
        
        // Toggle du bouton
        if (button) {
            console.log('Mise à jour bouton, actif:', isOn)
            button.classList.toggle('active', isOn)
        }
        
        // Toggle des labels
        if (labelOff) {
            console.log('Mise à jour label OFF, actif:', !isOn)
            labelOff.classList.toggle('active', !isOn)
        }
        if (labelOn) {
            console.log('Mise à jour label ON, actif:', isOn)
            labelOn.classList.toggle('active', isOn)
        }
    }
}