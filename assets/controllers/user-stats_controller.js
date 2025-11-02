import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  connect() {
    this.animateProgressRings()
    this.animateStatsCards()
    this.animateRadialProgress()
    this.addCardHoverEffects()
  }

  animateProgressRings() {
    const progressRings = this.element.querySelectorAll(
      '.progress-ring-animate',
    )
    progressRings.forEach((ring) => {
      const targetOffset =
        getComputedStyle(ring).getPropertyValue('--target-offset')
      ring.style.strokeDashoffset = '283'
      setTimeout(() => {
        ring.style.strokeDashoffset = targetOffset
      }, 500)
    })
  }

  animateStatsCards() {
    const statCards = this.element.querySelectorAll('.stats-slide-in')
    statCards.forEach((card, index) => {
      card.style.animationDelay = `${index * 150}ms`
    })
  }

  animateRadialProgress() {
    const radialProgress = this.element.querySelectorAll('.radial-progress')
    radialProgress.forEach((progress) => {
      const value = progress.style.getPropertyValue('--value')
      progress.style.setProperty('--value', '0')
      setTimeout(() => {
        progress.style.setProperty('--value', value)
      }, 1000)
    })
  }

  addCardHoverEffects() {
    const gameCards = this.element.querySelectorAll('.card')
    gameCards.forEach((card) => {
      card.addEventListener('mouseenter', function () {
        this.style.transform = 'translateY(-2px)'
      })
      card.addEventListener('mouseleave', function () {
        this.style.transform = 'translateY(0)'
      })
    })
  }
}
