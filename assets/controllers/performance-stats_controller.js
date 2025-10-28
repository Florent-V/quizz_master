// assets/controllers/performance-stats_controller.js
import { Controller } from '@hotwired/stimulus'
import { Chart } from 'chart.js/auto'

export default class extends Controller {
  static values = {
    gameModePerformance: Array,
    timeAnalysis: Object,
  }

  connect() {
    this.charts = []
    this.initCharts()
  }

  disconnect() {
    this.destroyCharts()
  }

  initCharts() {
    this.createGameModePerformanceChart()
    this.createGameModeDistributionChart()
    this.createResponseTimeChart()
  }

  createGameModePerformanceChart() {
    const canvas = document.getElementById('gameModePerformanceChart')
    if (!canvas) return

    const labels = this.gameModePerformanceValue.map((mode) => mode.gameMode)
    const avgScores = this.gameModePerformanceValue.map((mode) => mode.avgScore)
    const minScores = this.gameModePerformanceValue.map((mode) => mode.minScore)
    const maxScores = this.gameModePerformanceValue.map((mode) => mode.maxScore)

    const chart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [
          {
            label: 'Score Moyen',
            data: avgScores,
            backgroundColor: '#4e73df',
          },
          {
            label: 'Score Min',
            data: minScores,
            backgroundColor: '#e74a3b',
          },
          {
            label: 'Score Max',
            data: maxScores,
            backgroundColor: '#1cc88a',
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
      },
    })

    this.charts.push(chart)
  }

  createGameModeDistributionChart() {
    const canvas = document.getElementById('gameModeDistributionChart')
    if (!canvas) return

    const labels = this.gameModePerformanceValue.map((mode) => mode.gameMode)
    const sessions = this.gameModePerformanceValue.map(
      (mode) => mode.totalSessions,
    )

    const chart = new Chart(canvas, {
      type: 'doughnut',
      data: {
        labels: labels,
        datasets: [
          {
            data: sessions,
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

    this.charts.push(chart)
  }

  createResponseTimeChart() {
    const canvas = document.getElementById('responseTimeChart')
    if (!canvas) return

    const timeData = this.timeAnalysisValue

    const chart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels: ['Très Rapide', 'Rapide', 'Normal', 'Lent'],
        datasets: [
          {
            label: 'Nombre de réponses',
            data: [
              timeData.veryFast || 0,
              timeData.fast || 0,
              timeData.normal || 0,
              timeData.slow || 0,
            ],
            backgroundColor: ['#1cc88a', '#4e73df', '#f6c23e', '#e74a3b'],
          },
        ],
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            display: false,
          },
        },
      },
    })

    this.charts.push(chart)
  }

  destroyCharts() {
    this.charts.forEach((chart) => {
      if (chart) {
        chart.destroy()
      }
    })
    this.charts = []
  }
}
