import { Controller } from '@hotwired/stimulus'
import Chart from 'chart.js/auto'

/**
 * Contrôleur Stimulus pour le tableau de bord admin
 * Gère les graphiques Chart.js pour les statistiques
 */
export default class extends Controller {
  static values = {
    gameModes: Object,
    categories: Array,
    trends: Array,
  }

  connect() {
    this.initGameModeChart()
    this.initCategoryChart()
    this.initTrendsChart()
  }

  /**
   * Initialise le graphique des modes de jeu (doughnut)
   */
  initGameModeChart() {
    const canvas = document.getElementById('gameModeChart')
    if (!canvas) return

    const ctx = canvas.getContext('2d')
    const gameModes = this.gameModesValue

    const labels = Object.keys(gameModes)
    const data = Object.values(gameModes).map((mode) => mode.count)

    new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: labels,
        datasets: [
          {
            data: data,
            backgroundColor: [
              '#4e73df',
              '#1cc88a',
              '#36b9cc',
              '#f6c23e',
              '#e74a3b',
            ],
          },
        ],
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            position: 'bottom',
          },
        },
      },
    })
  }

  /**
   * Initialise le graphique des catégories (bar)
   */
  initCategoryChart() {
    const canvas = document.getElementById('categoryChart')
    if (!canvas) return

    const ctx = canvas.getContext('2d')
    const categories = this.categoriesValue.slice(0, 8)

    const labels = categories.map((cat) => cat.name)
    const data = categories.map((cat) => cat.successRate)

    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [
          {
            label: 'Taux de réussite (%)',
            data: data,
            backgroundColor: '#1cc88a',
          },
        ],
      },
      options: {
        responsive: true,
        scales: {
          y: {
            beginAtZero: true,
            max: 100,
          },
        },
      },
    })
  }

  /**
   * Initialise le graphique des tendances (line)
   */
  initTrendsChart() {
    const canvas = document.getElementById('trendsChart')
    if (!canvas) return

    const ctx = canvas.getContext('2d')
    const trends = this.trendsValue

    const labels = trends.map((day) => {
      const date = new Date(day.date.date)
      return date.toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
      })
    })

    const sessionsData = trends.map((day) => day.sessions)
    const avgScoreData = trends.map((day) => day.avgScore ?? 0)

    new Chart(ctx, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [
          {
            label: 'Sessions par jour',
            data: sessionsData,
            borderColor: '#4e73df',
            backgroundColor: 'rgba(78, 115, 223, 0.1)',
            tension: 0.3,
          },
          {
            label: 'Score moyen',
            data: avgScoreData,
            borderColor: '#1cc88a',
            backgroundColor: 'rgba(28, 200, 138, 0.1)',
            tension: 0.3,
            yAxisID: 'y1',
          },
        ],
      },
      options: {
        responsive: true,
        interaction: {
          intersect: false,
        },
        scales: {
          y: {
            type: 'linear',
            display: true,
            position: 'left',
          },
          y1: {
            type: 'linear',
            display: true,
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
