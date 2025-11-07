import { Controller } from '@hotwired/stimulus'
import Chart from 'chart.js/auto'

export default class extends Controller {
  static targets = ['shareModal', 'shareText', 'timeChart', 'resultsChart']

  static values = {
    answers: Array,
    correctAnswers: Number,
    totalQuestions: Number,
  }

  connect() {
    console.log('Result V3 controller connected')
    this.initCharts()
  }

  /**
   * Initialise les graphiques Chart.js
   */
  initCharts() {
    this.initTimeChart()
    this.initResultsChart()
  }

  /**
   * Initialise le graphique des temps de réponse
   */
  initTimeChart() {
    if (!this.hasTimeChartTarget) return

    const ctx = this.timeChartTarget.getContext('2d')
    const answers = this.answersValue

    const labels = answers.map((_, index) => `Q${index + 1}`)
    const data = answers.map((answer) => answer.time)
    const backgroundColors = answers.map((answer) =>
      answer.isCorrect ? 'rgba(34, 197, 94, 0.8)' : 'rgba(239, 68, 68, 0.8)',
    )
    const borderColors = answers.map((answer) =>
      answer.isCorrect ? 'rgb(34, 197, 94)' : 'rgb(239, 68, 68)',
    )

    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [
          {
            label: 'Temps (secondes)',
            data: data,
            backgroundColor: backgroundColors,
            borderColor: borderColors,
            borderWidth: 2,
            borderRadius: 8,
            borderSkipped: false,
          },
        ],
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            display: false,
          },
          tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            titleColor: '#fff',
            bodyColor: '#fff',
            borderColor: 'rgba(255, 255, 255, 0.2)',
            borderWidth: 1,
            cornerRadius: 8,
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: 'Temps (secondes)',
              font: {
                weight: 'bold',
              },
            },
            grid: {
              color: 'rgba(0, 0, 0, 0.1)',
            },
          },
          x: {
            title: {
              display: true,
              text: 'Questions',
              font: {
                weight: 'bold',
              },
            },
            grid: {
              display: false,
            },
          },
        },
        animation: {
          duration: 2000,
          easing: 'easeOutBounce',
        },
      },
    })
  }

  /**
   * Initialise le graphique en secteurs
   */
  initResultsChart() {
    if (!this.hasResultsChartTarget) return

    const ctx = this.resultsChartTarget.getContext('2d')
    const correctAnswers = this.correctAnswersValue
    const totalQuestions = this.totalQuestionsValue

    new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Correctes', 'Incorrectes'],
        datasets: [
          {
            data: [correctAnswers, totalQuestions - correctAnswers],
            backgroundColor: [
              'rgba(34, 197, 94, 0.8)',
              'rgba(239, 68, 68, 0.8)',
            ],
            borderColor: ['rgb(34, 197, 94)', 'rgb(239, 68, 68)'],
            borderWidth: 3,
            hoverOffset: 4,
          },
        ],
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              padding: 20,
              font: {
                size: 14,
                weight: 'bold',
              },
            },
          },
          tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            titleColor: '#fff',
            bodyColor: '#fff',
            borderColor: 'rgba(255, 255, 255, 0.2)',
            borderWidth: 1,
            cornerRadius: 8,
            callbacks: {
              label: function (context) {
                const label = context.label || ''
                const value = context.parsed
                const total = context.dataset.data.reduce((a, b) => a + b, 0)
                const percentage = Math.round((value / total) * 100)
                return `${label}: ${value} (${percentage}%)`
              },
            },
          },
        },
        animation: {
          animateRotate: true,
          duration: 2000,
          easing: 'easeOutBounce',
        },
      },
    })
  }

  /**
   * Ouvre le modal de partage
   */
  shareResults(event) {
    event.preventDefault()
    if (this.hasShareModalTarget) {
      this.shareModalTarget.showModal()
    }
  }

  /**
   * Copie le texte dans le presse-papier
   */
  async copyToClipboard(event) {
    event.preventDefault()

    if (!this.hasShareTextTarget) return

    const text = this.shareTextTarget.value

    try {
      await navigator.clipboard.writeText(text)
      this.showNotification('Texte copié dans le presse-papier !', 'success')
    } catch (err) {
      console.error('Erreur lors de la copie:', err)
      // Fallback pour les navigateurs plus anciens
      this.shareTextTarget.select()
      this.shareTextTarget.setSelectionRange(0, 99999) // Pour les mobiles
      document.execCommand('copy')
      this.showNotification('Texte copié dans le presse-papier !', 'success')
    }
  }

  /**
   * Lance l'impression de la page
   */
  print(event) {
    event.preventDefault()
    window.print()
  }

  /**
   * Affiche une notification temporaire
   */
  showNotification(message, type = 'info') {
    const alertDiv = document.createElement('div')
    alertDiv.className = `alert alert-${type} fixed top-4 right-4 z-50 w-auto toast-notification shadow-lg`
    alertDiv.innerHTML = `
            <i class="fas fa-check-circle"></i>
            <span>${message}</span>
        `
    document.body.appendChild(alertDiv)

    // Animation de disparition
    setTimeout(() => {
      alertDiv.classList.add('fade-out')
      setTimeout(() => {
        alertDiv.remove()
      }, 300)
    }, 2700)
  }
}
