import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static values = {
    score: Number,
    accuracy: Number,
  }

  connect() {
    this.animateStats()
  }

  /**
   * Anime l'apparition des statistiques au chargement
   */
  animateStats() {
    const stats = this.element.querySelectorAll('.stat-item')

    stats.forEach((stat, index) => {
      setTimeout(() => {
        stat.classList.add('animated')
      }, index * 200)
    })
  }

  /**
   * Partage les résultats via l'API Web Share ou copie dans le presse-papiers
   */
  shareResults() {
    const shareData = {
      title: 'Mes résultats au Quiz',
      text: `J'ai obtenu ${this.scoreValue} points au quiz avec ${this.accuracyValue}% de bonnes réponses !`,
      url: window.location.href,
    }

    if (navigator.share) {
      navigator.share(shareData).catch((error) => {
        console.error('Erreur lors du partage:', error)
      })
    } else {
      // Fallback pour les navigateurs qui ne supportent pas l'API Share
      const text = `${shareData.text} ${shareData.url}`

      navigator.clipboard
        .writeText(text)
        .then(() => {
          alert('Lien copié dans le presse-papiers !')
        })
        .catch((error) => {
          console.error('Erreur lors de la copie:', error)
          alert('Impossible de copier le lien')
        })
    }
  }

  /**
   * Imprime les résultats
   */
  printResults() {
    window.print()
  }
}
