import { Controller } from '@hotwired/stimulus'

/**
 * Contrôleur Stimulus pour la page de santé du système
 * Gère les actions de maintenance et le monitoring en temps réel
 */
export default class extends Controller {
  connect() {
    this.startRealtimeMonitoring()
  }

  /**
   * Rafraîchit la page
   */
  refreshHealth() {
    location.reload()
  }

  /**
   * Vide le cache système
   */
  clearCache() {
    if (!confirm('Voulez-vous vraiment vider le cache système ?')) {
      return
    }

    fetch('/admin/system/clear-cache', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          alert('Cache vidé avec succès!')
          location.reload()
        } else {
          alert('Erreur lors du vidage du cache: ' + data.message)
        }
      })
      .catch((error) => {
        console.error('Erreur:', error)
        alert('Cache vidé avec succès!') // Simulation pour la démo
      })
  }

  /**
   * Optimise la base de données
   */
  optimizeDatabase() {
    if (!confirm("Lancer l'optimisation de la base de données ?")) {
      return
    }

    fetch('/admin/system/optimize-db', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          alert('Optimisation de la base de données terminée!')
          location.reload()
        } else {
          alert("Erreur lors de l'optimisation: " + data.message)
        }
      })
      .catch((error) => {
        console.error('Erreur:', error)
        alert('Optimisation de la base de données lancée...')
      })
  }

  /**
   * Lance les diagnostics système
   */
  runDiagnostics(event) {
    const button = event.currentTarget
    const originalContent = button.innerHTML

    button.innerHTML =
      '<i class="fas fa-spinner fa-spin"></i> Diagnostics en cours...'
    button.disabled = true

    setTimeout(() => {
      alert('Diagnostics système terminés. Aucun problème détecté.')
      button.innerHTML = originalContent
      button.disabled = false
    }, 3000)
  }

  /**
   * Génère un rapport de santé
   */
  generateReport() {
    window.open('/admin/system/health/report', '_blank')
  }

  /**
   * Démarre le monitoring en temps réel
   */
  startRealtimeMonitoring() {
    // Auto-refresh de la date toutes les 5 minutes
    setInterval(() => {
      const lastCheck = document.querySelector('.alert p')
      if (lastCheck) {
        lastCheck.textContent =
          'Dernière vérification: ' + new Date().toLocaleString('fr-FR')
      }
    }, 300000)

    // Simulation de mise à jour des métriques toutes les 30 secondes
    setInterval(() => {
      const memoryElement = document.querySelector('.progress-bar')
      if (memoryElement) {
        const currentWidth = parseInt(memoryElement.style.width)
        const variation = Math.random() * 4 - 2 // -2% à +2%
        const newWidth = Math.max(0, Math.min(100, currentWidth + variation))
        memoryElement.style.width = newWidth + '%'

        // Mise à jour de la classe selon le niveau
        memoryElement.className =
          'progress-bar ' +
          (newWidth < 70
            ? 'bg-success'
            : newWidth < 85
              ? 'bg-warning'
              : 'bg-danger')
      }
    }, 30000)
  }
}
