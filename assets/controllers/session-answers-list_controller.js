// assets/controllers/session-answers-list_controller.js
import { Controller } from '@hotwired/stimulus'

/* global bootstrap */

export default class extends Controller {
  connect() {
    this.initTooltips()
  }

  disconnect() {
    this.destroyTooltips()
  }

  initTooltips() {
    // Bootstrap est disponible globalement via EasyAdmin
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
      const tooltipTriggerList = this.element.querySelectorAll(
        '[data-bs-toggle="tooltip"]',
      )
      this.tooltips = Array.from(tooltipTriggerList).map(
        (tooltipTriggerEl) => new bootstrap.Tooltip(tooltipTriggerEl),
      )
    }
  }

  destroyTooltips() {
    if (this.tooltips) {
      this.tooltips.forEach((tooltip) => {
        if (tooltip.dispose) {
          tooltip.dispose()
        }
      })
      this.tooltips = []
    }
  }
}
