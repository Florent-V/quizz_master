import { Controller } from '@hotwired/stimulus'
import Chart from 'chart.js/auto'

/*
 * Controller Stimulus pour la page d'analyse des questions
 * Gère le graphique des statistiques par catégorie
 */
export default class extends Controller {
  static targets = ['canvas']
  static values = {
    categories: Array,
    successRates: Array,
    questionCounts: Array,
  }

  connect() {
    this.initializeCategoryChart()
  }

  /**
   * Initialise le graphique des statistiques par catégorie
   */
  initializeCategoryChart() {
    if (!this.hasCanvasTarget) {
      console.warn('Canvas target not found for category chart')
      return
    }

    const ctx = this.canvasTarget.getContext('2d')

    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: this.categoriesValue,
        datasets: [
          {
            label: 'Taux de Réussite (%)',
            data: this.successRatesValue,
            backgroundColor: '#4e73df',
          },
          {
            label: 'Nombre de Questions',
            data: this.questionCountsValue,
            backgroundColor: '#1cc88a',
            yAxisID: 'y1',
          },
        ],
      },
      options: {
        responsive: true,
        scales: {
          y: {
            beginAtZero: true,
            max: 100,
            position: 'left',
          },
          y1: {
            beginAtZero: true,
            position: 'right',
            grid: {
              drawOnChartArea: false,
            },
          },
        },
      },
    })
  }
}
