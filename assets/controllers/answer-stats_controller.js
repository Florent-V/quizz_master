// assets/controllers/answer-stats_controller.js
import { Controller } from '@hotwired/stimulus'
import Chart from 'chart.js/auto'

export default class extends Controller {
  static values = {
    correctAnswers: Number,
    totalAnswers: Number,
  }

  connect() {
    this.initQuestionStatsChart()
  }

  initQuestionStatsChart() {
    const ctx = this.element.querySelector('#questionStatsChart')
    if (!ctx) return

    const incorrectAnswers = this.totalAnswersValue - this.correctAnswersValue

    new Chart(ctx.getContext('2d'), {
      type: 'doughnut',
      data: {
        labels: ['Correctes', 'Incorrectes'],
        datasets: [
          {
            data: [this.correctAnswersValue, incorrectAnswers],
            backgroundColor: ['#1cc88a', '#e74a3b'],
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
}
