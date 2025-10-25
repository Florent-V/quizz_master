// assets/controllers/quiz-session-stats_controller.js
import { Controller } from '@hotwired/stimulus'
import Chart from 'chart.js/auto'

export default class extends Controller {
  static values = {
    scores: Array,
    times: Array,
    totalAnswers: Number,
  }

  connect() {
    this.initScoreChart()
    this.initTimeChart()
  }

  initScoreChart() {
    const ctx = this.element.querySelector('#sessionPerformanceChart')
    if (!ctx) return

    const scores = this.scoresValue

    new Chart(ctx.getContext('2d'), {
      type: 'line',
      data: {
        labels: Array.from(
          { length: this.totalAnswersValue },
          (_, i) => `Q${i + 1}`,
        ),
        datasets: [
          {
            label: 'Score par question',
            data: scores,
            borderColor: '#4e73df',
            backgroundColor: 'rgba(78, 115, 223, 0.1)',
            tension: 0.3,
            pointBackgroundColor: scores.map((score) =>
              score > 0 ? '#28a745' : '#dc3545',
            ),
            pointBorderColor: scores.map((score) =>
              score > 0 ? '#28a745' : '#dc3545',
            ),
            pointRadius: 5,
            pointHoverRadius: 7,
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
                return value + ' pts'
              },
            },
          },
        },
        plugins: {
          tooltip: {
            callbacks: {
              label: function (context) {
                return 'Score: ' + context.parsed.y + ' points'
              },
            },
          },
        },
      },
    })
  }

  initTimeChart() {
    const ctx = this.element.querySelector('#sessionTimeChart')
    if (!ctx) return

    const times = this.timesValue

    new Chart(ctx.getContext('2d'), {
      type: 'line',
      data: {
        labels: Array.from(
          { length: this.totalAnswersValue },
          (_, i) => `Q${i + 1}`,
        ),
        datasets: [
          {
            label: 'Temps de réponse (secondes)',
            data: times,
            borderColor: '#f6c23e',
            backgroundColor: 'rgba(246, 194, 62, 0.1)',
            tension: 0.3,
            pointBackgroundColor: '#f6c23e',
            pointBorderColor: '#f6c23e',
            pointRadius: 5,
            pointHoverRadius: 7,
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
                return value + 's'
              },
            },
          },
        },
        plugins: {
          tooltip: {
            callbacks: {
              label: function (context) {
                return 'Temps: ' + context.parsed.y + ' secondes'
              },
            },
          },
        },
      },
    })
  }
}
