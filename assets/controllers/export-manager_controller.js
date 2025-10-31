import { Controller } from '@hotwired/stimulus'
import Chart from 'chart.js/auto'

/*
 * Controller Stimulus pour la page d'export de données
 * Gère les exports personnalisés, programmés, l'historique et les graphiques
 */
export default class extends Controller {
  static targets = [
    'customForm',
    'periodSelect',
    'advancedFilters',
    'exportChart',
  ]

  connect() {
    this.initializeChart()
    this.setupEventListeners()
  }

  /**
   * Initialise le graphique des statistiques d'export
   */
  initializeChart() {
    if (!this.hasExportChartTarget) {
      return
    }

    const ctx = this.exportChartTarget.getContext('2d')
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
        datasets: [
          {
            label: 'Exports par jour',
            data: [2, 1, 4, 3, 5, 2, 1],
            borderColor: '#4e73df',
            backgroundColor: 'rgba(78, 115, 223, 0.1)',
            tension: 0.3,
          },
        ],
      },
      options: {
        responsive: true,
        scales: {
          y: {
            beginAtZero: true,
          },
        },
        plugins: {
          legend: {
            display: false,
          },
        },
      },
    })
  }

  /**
   * Configure les écouteurs d'événements
   */
  setupEventListeners() {
    if (this.hasCustomFormTarget) {
      this.customFormTarget.addEventListener('submit', (e) =>
        this.handleCustomExport(e),
      )
    }

    if (this.hasPeriodSelectTarget) {
      this.periodSelectTarget.addEventListener('change', () =>
        this.toggleAdvancedFilters(),
      )
    }
  }

  /**
   * Gère la soumission du formulaire d'export personnalisé
   */
  handleCustomExport(e) {
    e.preventDefault()

    const formData = new FormData(this.customFormTarget)
    // const params = new URLSearchParams(formData)

    this.showExportProgress()

    setTimeout(() => {
      this.hideExportProgress()
      alert(
        `Export terminé avec succès!\nType: ${formData.get('dataType')}\nPériode: ${formData.get('period')}\nFormat: ${formData.get('format')}`,
      )
      location.reload()
    }, 3000)
  }

  /**
   * Affiche/masque les filtres avancés
   */
  toggleAdvancedFilters() {
    if (!this.hasAdvancedFiltersTarget) {
      return
    }

    if (this.periodSelectTarget.value === 'custom') {
      this.advancedFiltersTarget.style.display = 'block'
    } else {
      this.advancedFiltersTarget.style.display = 'none'
    }
  }

  /**
   * Exporte les statistiques globales
   */
  exportStats() {
    this.showExportProgress()
    setTimeout(() => {
      this.hideExportProgress()
      window.open('/admin/export/statistics/global.json', '_blank')
    }, 2000)
  }

  /**
   * Exporte l'analyse de performance
   */
  exportPerformance() {
    this.showExportProgress()
    setTimeout(() => {
      this.hideExportProgress()
      window.open('/admin/export/statistics/performance.xlsx', '_blank')
    }, 2000)
  }

  /**
   * Exporte le rapport des problèmes
   */
  exportProblems() {
    this.showExportProgress()
    setTimeout(() => {
      this.hideExportProgress()
      window.open('/admin/export/statistics/problems.pdf', '_blank')
    }, 2000)
  }

  /**
   * Exporte les tendances temporelles
   */
  exportTrends() {
    this.showExportProgress()
    setTimeout(() => {
      this.hideExportProgress()
      window.open('/admin/export/statistics/trends.csv', '_blank')
    }, 2000)
  }

  /**
   * Active/désactive un export programmé
   */
  toggleSchedule(event) {
    const button = event.currentTarget
    const row = button.closest('tr')
    const statusBadge = row.querySelector('.badge')
    const type = button.dataset.scheduleType || 'export'

    if (statusBadge.textContent === 'Actif') {
      statusBadge.className = 'badge bg-secondary'
      statusBadge.textContent = 'Inactif'
      button.innerHTML = '<i class="fas fa-play"></i>'
      button.className = 'btn btn-sm btn-outline-success'
    } else {
      statusBadge.className = 'badge bg-success'
      statusBadge.textContent = 'Actif'
      button.innerHTML = '<i class="fas fa-pause"></i>'
      button.className = 'btn btn-sm btn-outline-secondary'
    }

    alert(`Programmation ${type} ${statusBadge.textContent.toLowerCase()}e`)
  }

  /**
   * Configure les programmations
   */
  configureSchedule() {
    alert('Interface de configuration des programmations à venir...')
  }

  /**
   * Télécharge un export depuis l'historique
   */
  downloadExport(event) {
    const exportId = event.currentTarget.dataset.exportId
    alert(`Téléchargement de l'export ${exportId}...`)
  }

  /**
   * Supprime un export depuis l'historique
   */
  deleteExport(event) {
    if (!confirm('Êtes-vous sûr de vouloir supprimer cet export ?')) {
      return
    }

    const button = event.currentTarget
    const row = button.closest('tr')
    row.style.opacity = '0.5'

    setTimeout(() => {
      row.remove()
      alert('Export supprimé avec succès')
    }, 1000)
  }

  /**
   * Affiche la modal de progression d'export
   */
  showExportProgress() {
    const modal = document.createElement('div')
    modal.id = 'exportProgressModal'
    modal.className = 'modal fade show'
    modal.style.display = 'block'
    modal.innerHTML = `
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-body text-center">
            <div class="spinner-border text-primary mb-3" role="status">
              <span class="sr-only">Loading...</span>
            </div>
            <h5>Export en cours...</h5>
            <p class="text-muted">Veuillez patienter pendant la génération de votre export.</p>
            <div class="progress">
              <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar" style="width: 0%"></div>
            </div>
          </div>
        </div>
      </div>
    `

    document.body.appendChild(modal)

    let progress = 0
    const interval = setInterval(() => {
      progress += Math.random() * 20
      if (progress > 100) progress = 100
      const progressBar = document.getElementById('progressBar')
      if (progressBar) {
        progressBar.style.width = progress + '%'
      }
      if (progress === 100) clearInterval(interval)
    }, 200)
  }

  /**
   * Masque la modal de progression d'export
   */
  hideExportProgress() {
    const modal = document.getElementById('exportProgressModal')
    if (modal) {
      modal.remove()
    }
  }
}
