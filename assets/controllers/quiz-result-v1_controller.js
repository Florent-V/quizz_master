import { Controller } from '@hotwired/stimulus'
import { Chart } from 'chart.js/auto'

export default class extends Controller {
  static targets = ['performanceChart', 'timeChart']
  static values = {
    answers: Array,
    accuracy: Number,
  }

  connect() {
    this.animateCards()
    this.initCharts()

    if (this.accuracyValue >= 80) {
      setTimeout(() => this.createConfetti(), 500)
    }
  }

  animateCards() {
    const cards = document.querySelectorAll('.card')
    cards.forEach((card, index) => {
      card.style.opacity = '0'
      card.style.transform = 'translateY(30px)'
      setTimeout(() => {
        card.style.transition = 'all 0.6s ease-out'
        card.style.opacity = '1'
        card.style.transform = 'translateY(0)'
      }, 100 * index)
    })
  }

  initCharts() {
    this.createPerformanceChart()
    this.createTimeChart()
  }

  createPerformanceChart() {
    const ctx = this.performanceChartTarget.getContext('2d')

    // Détection du thème pour les couleurs
    const isDark =
      document.documentElement.getAttribute('data-theme')?.includes('dark') ||
      document.documentElement.classList.contains('dark')

    const textColor = isDark ? '#e5e7eb' : '#374151'
    const gridColor = isDark ? 'rgba(229, 231, 235, 0.1)' : 'rgba(0, 0, 0, 0.1)'

    console.log(this.answersValue)

    new Chart(ctx, {
      type: 'line',
      data: {
        labels: this.answersValue.map((_, index) => `Q${index + 1}`),
        datasets: [
          {
            label: 'Temps de réponse (secondes)',
            data: this.answersValue.map((answer) => answer.time),
            borderColor: 'rgb(16, 185, 129)',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: this.answersValue.map((answer) =>
              answer.isCorrect ? '#10b981' : '#ef4444',
            ),
            pointBorderColor: this.answersValue.map((answer) =>
              answer.isCorrect ? '#059669' : '#dc2626',
            ),
            pointRadius: 6,
            pointHoverRadius: 8,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: 'Temps (secondes)',
              color: textColor,
            },
            ticks: { color: textColor },
            grid: { color: gridColor },
          },
          x: {
            title: {
              display: true,
              text: 'Questions',
              color: textColor,
            },
            ticks: { color: textColor },
            grid: { color: gridColor },
          },
        },
        plugins: {
          legend: {
            display: true,
            position: 'top',
            labels: { color: textColor },
          },
          tooltip: {
            callbacks: {
              afterLabel: (context) => {
                const answer = this.answersValue[context.dataIndex]
                return answer.isCorrect
                  ? 'Réponse correcte'
                  : 'Réponse incorrecte'
              },
            },
          },
        },
      },
    })
  }

  createTimeChart() {
    const ctx = this.timeChartTarget.getContext('2d')

    const isDark =
      document.documentElement.getAttribute('data-theme')?.includes('dark') ||
      document.documentElement.classList.contains('dark')

    const textColor = isDark ? '#e5e7eb' : '#374151'

    const timeRanges = ['0-3s', '3-5s', '5-10s', '10s+']
    const timeCounts = [0, 0, 0, 0]

    this.answersValue.forEach((answer) => {
      if (answer.time <= 3) timeCounts[0]++
      else if (answer.time <= 5) timeCounts[1]++
      else if (answer.time <= 10) timeCounts[2]++
      else timeCounts[3]++
    })

    new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: timeRanges,
        datasets: [
          {
            data: timeCounts,
            backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444'],
            borderWidth: 2,
            borderColor: isDark ? '#1f2937' : '#ffffff',
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: { color: textColor },
          },
        },
      },
    })
  }

  createConfetti() {
    const colors = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6']
    for (let i = 0; i < 50; i++) {
      const confetti = document.createElement('div')
      confetti.style.position = 'fixed'
      confetti.style.width = '10px'
      confetti.style.height = '10px'
      confetti.style.backgroundColor =
        colors[Math.floor(Math.random() * colors.length)]
      confetti.style.left = Math.random() * 100 + 'vw'
      confetti.style.top = '-10px'
      confetti.style.zIndex = '9999'
      confetti.style.borderRadius = '50%'

      document.body.appendChild(confetti)

      const animation = confetti.animate(
        [
          { transform: 'translateY(0) rotate(0deg)', opacity: 1 },
          {
            transform: `translateY(${window.innerHeight + 20}px) rotate(360deg)`,
            opacity: 0,
          },
        ],
        {
          duration: Math.random() * 3000 + 2000,
          easing: 'cubic-bezier(0.25, 0.46, 0.45, 0.94)',
        },
      )

      animation.addEventListener('finish', () => {
        confetti.remove()
      })
    }
  }

  printResults() {
    window.print()
  }
}
