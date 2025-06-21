// assets/controllers/dropdown_controller.js
import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["menu"]

    connect() {
        this.outsideClickListener = this.outsideClick.bind(this)
    }

    toggle() {
        if (this.menuTarget.classList.contains("hidden")) {
            this.open()
        } else {
            this.close()
        }
    }

    open() {
        this.menuTarget.classList.remove("hidden")
        document.addEventListener("click", this.outsideClickListener)
    }

    close() {
        this.menuTarget.classList.add("hidden")
        document.removeEventListener("click", this.outsideClickListener)
    }

    outsideClick(event) {
        if (!this.element.contains(event.target)) {
            this.close()
        }
    }

    disconnect() {
        document.removeEventListener("click", this.outsideClickListener)
    }
}