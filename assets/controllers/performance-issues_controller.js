// assets/controllers/performance-issues_controller.js
import { Controller } from '@hotwired/stimulus'

/* global $ */

export default class extends Controller {
  static targets = ['sessionTable']

  connect() {
    this.initDataTable()
  }

  disconnect() {
    this.destroyDataTable()
  }

  initDataTable() {
    // DataTables est disponible via jQuery (chargé par EasyAdmin)
    if (typeof $ !== 'undefined' && $.fn.DataTable) {
      if (this.hasSessionTableTarget) {
        this.dataTable = $(this.sessionTableTarget).DataTable({
          language: {
            url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/French.json',
          },
          order: [[2, 'asc']], // Trier par score croissant
          pageLength: 25,
        })
      }
    }
  }

  destroyDataTable() {
    if (this.dataTable) {
      this.dataTable.destroy()
      this.dataTable = null
    }
  }

  exportProblems() {
    // Logique d'export des problèmes
    alert('Export des problèmes en cours...')
    // TODO: Implémenter la vraie logique d'export
  }

  runDiagnostic() {
    // Logique de diagnostic
    alert('Diagnostic système lancé...')
    // TODO: Implémenter la vraie logique de diagnostic
  }
}

