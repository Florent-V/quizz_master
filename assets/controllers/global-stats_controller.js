// assets/controllers/global-stats_controller.js
import { Controller } from '@hotwired/stimulus'
import { Chart } from 'chart.js/auto'

export default class extends Controller {
  static values = {
    gameModes: Object,
    categories: Array,
    trends: Array,
  }

  connect() {
    this.charts = []
    this.initCharts()
  }

  disconnect() {
    this.destroyCharts()
  }

  initCharts() {
    this.createGameModeChart()
    this.createCategoryChart()
    this.createTrendsChart()
  }

  createGameModeChart() {
    const canvas = document.getElementById('gameModeChart')
    if (!canvas) return

    const labels = Object.keys(this.gameModesValue)
    const data = Object.values(this.gameModesValue).map((d) => d.avgScore)

    const chart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [
          {
            label: 'Score moyen',
            data: data,
            backgroundColor: '#4e73df',
            borderColor: '#4e73df',
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: function (value) {
                return value.toFixed(0)
              },
            },
          },
        },
        plugins: {
          tooltip: {
            callbacks: {
              label: function (context) {
                return 'Score moyen: ' + context.parsed.y.toFixed(1)
              },
            },
          },
        },
      },
    })

    this.charts.push(chart)
  }

  createCategoryChart() {
    const canvas = document.getElementById('categoryChart')
    if (!canvas) return

    const categories = this.categoriesValue.slice(0, 6)
    const labels = categories.map((c) => c.name)
    const data = categories.map((c) => c.successRate)

    const chart = new Chart(canvas, {
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
              '#5a5c69',
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

  createTrendsChart() {
    const canvas = document.getElementById('trendsChart')
    if (!canvas) return

    const labels = this.trendsValue.map((day) => {
      const date = new Date(day.date)
      return `${date.getDate()}/${date.getMonth() + 1}`
    })
    const sessionsData = this.trendsValue.map((day) => day.sessions)
    const scoresData = this.trendsValue.map((day) => day.avgScore ?? 0)

    const chart = new Chart(canvas, {
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
            yAxisID: 'y',
          },
          {
            label: 'Score moyen',
            data: scoresData,
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
            title: {
              display: true,
              text: 'Sessions',
            },
          },
          y1: {
            type: 'linear',
            display: true,
            position: 'right',
            title: {
              display: true,
              text: 'Score moyen',
            },
            grid: {
              drawOnChartArea: false,
            },
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
