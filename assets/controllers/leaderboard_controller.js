import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  connect() {
    this.animateProgressBars()
    this.animateStatsCards()
    this.addGoldBadgeEffects()
  }

  animateProgressBars() {
    const progressBars = this.element.querySelectorAll('.progress')
    progressBars.forEach((bar, index) => {
      const value = bar.getAttribute('value')
      bar.setAttribute('value', '0')
      setTimeout(
        () => {
          bar.setAttribute('value', value)
        },
        100 + index * 50,
      )
    })
  }

  animateStatsCards() {
    const statsCards = this.element.querySelectorAll('.stat')
    statsCards.forEach((card, index) => {
      card.style.animationDelay = `${index * 100}ms`
    })
  }

  addGoldBadgeEffects() {
    const goldBadges = this.element.querySelectorAll('.gold-gradient')
    goldBadges.forEach((badge) => {
      badge.addEventListener('mouseenter', function () {
        this.style.filter = 'brightness(1.2) saturate(1.3)'
      })
      badge.addEventListener('mouseleave', function () {
        this.style.filter = 'brightness(1) saturate(1)'
      })
    })
  }
}
