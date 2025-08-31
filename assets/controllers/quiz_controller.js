// assets/controllers/quiz_controller.js
import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = [
    'timer',
    'submitBtn',
    'proposalCard',
    'form',
    'selectionIndicator',
  ]
  static values = {
    startTime: Number,
  }

  connect() {
    console.log('Quiz controller connected')
    this.startTime = Date.now()
    this.timerInterval = null
    this.selectedAnswer = null

    this.startTimer()
    this.animateCards()
  }

  disconnect() {
    if (this.timerInterval) {
      clearInterval(this.timerInterval)
    }
  }

  startTimer() {
    this.updateTimerDisplay()
    this.timerInterval = setInterval(() => {
      this.updateTimerDisplay()
    }, 1000)
  }

  updateTimerDisplay() {
    if (this.hasTimerTarget) {
      const elapsed = Math.floor((Date.now() - this.startTime) / 1000)
      const mins = Math.floor(elapsed / 60)
      const secs = elapsed % 60
      this.timerTarget.textContent = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
    }
  }

  animateCards() {
    this.proposalCardTargets.forEach((card, index) => {
      setTimeout(() => {
        card.style.opacity = '1'
        card.style.transform = 'translateY(0)'
      }, index * 100)
    })
  }

  selectAnswer(event) {
    const radio = event.currentTarget.querySelector('input[type="radio"]')
    if (radio) {
      radio.checked = true
      this.updateSelection(radio)
    }
  }

  updateSelection(radio) {
    // Reset toutes les cartes
    this.proposalCardTargets.forEach((card) => {
      card.classList.remove('ring-2', 'ring-primary', 'bg-primary/5')
      const indicator = card.querySelector('.selection-indicator')
      if (indicator) {
        indicator.style.display = 'none'
      }
    })

    // Highlight la sélection actuelle
    const selectedCard = radio.closest('.proposal-card')
    if (selectedCard) {
      selectedCard.classList.add('ring-2', 'ring-primary', 'bg-primary/5')
      const indicator = selectedCard.querySelector('.selection-indicator')
      if (indicator) {
        indicator.style.display = 'block'
      }
    }

    // Activer le bouton de validation
    if (this.hasSubmitBtnTarget) {
      this.submitBtnTarget.disabled = false
    }

    // Feedback haptic sur mobile
    if (navigator.vibrate) {
      navigator.vibrate(50)
    }

    this.selectedAnswer = radio.value
  }

  // Action pour la sélection via input radio directement
  onRadioChange(event) {
    this.updateSelection(event.target)
  }

  submitForm(event) {
    if (!this.selectedAnswer) {
      event.preventDefault()
      return
    }

    // Désactiver le bouton et changer le texte
    if (this.hasSubmitBtnTarget) {
      this.submitBtnTarget.disabled = true
      this.submitBtnTarget.innerHTML =
        '<span class="loading loading-spinner loading-sm mr-2"></span>Traitement...'
    }

    // Désactiver toutes les cartes
    this.proposalCardTargets.forEach((card) => {
      card.style.pointerEvents = 'none'
    })

    // Arrêter le timer
    if (this.timerInterval) {
      clearInterval(this.timerInterval)
    }

    // Le formulaire se soumet normalement
  }

  // Action pour ouvrir la modal d'image
  openImageModal() {
    const modal = document.getElementById('image_modal')
    if (modal) {
      modal.showModal()
    }
  }
}
