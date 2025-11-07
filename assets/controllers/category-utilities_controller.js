// assets/controllers/category-utilities_controller.js
import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = [
    'sourceParent',
    'mergeParentTarget',
    'sourceChild',
    'mergeChildTarget',
    'moveSource',
    'moveTarget',
    'questionsList',
    'questionsContainer',
    'selectAllQuestions',
    'modalContent',
    'modalTitle',
  ]

  static values = {
    questionsApiUrl: String,
  }

  connect() {
    console.log('🎯 Controller connecté')
    this.setupEventListeners()
  }

  setupEventListeners() {
    // Gestion fusion parents
    this.sourceParentTargets.forEach((checkbox) => {
      checkbox.addEventListener('change', () => this.handleSourceParentChange())
    })

    // Gestion fusion enfants
    this.sourceChildTargets.forEach((checkbox) => {
      checkbox.addEventListener('change', () => this.handleSourceChildChange())
    })

    // Gestion déplacement questions
    if (this.hasMoveSourceTarget) {
      this.moveSourceTarget.addEventListener('change', () =>
        this.handleMoveSourceChange(),
      )
    }

    // Select all questions
    if (this.hasSelectAllQuestionsTarget) {
      this.selectAllQuestionsTarget.addEventListener('change', (e) => {
        this.toggleAllQuestions(e.target.checked)
      })
    }
  }

  // === FUSION PARENTS ===
  handleSourceParentChange() {
    const selectedSources = this.getSelectedValues(this.sourceParentTargets)
    this.disableSelectedOptions(this.mergeParentTargetTarget, selectedSources)
  }

  confirmMergeParent(event) {
    const selectedSources = this.getSelectedCheckboxes(this.sourceParentTargets)
    const target = this.mergeParentTargetTarget

    if (selectedSources.length === 0) {
      event.preventDefault()
      alert('Veuillez sélectionner au moins une catégorie parent source.')
      return false
    }

    if (!target.value) {
      event.preventDefault()
      alert('Veuillez sélectionner une catégorie parent cible.')
      return false
    }

    const confirmed = confirm(
      `⚠️ ATTENTION ⚠️\n\n` +
        `Vous allez fusionner ${selectedSources.length} catégorie(s) PARENT.\n` +
        `Les catégories enfants seront déplacées vers le parent cible.\n` +
        `Cette action est IRRÉVERSIBLE.\n\n` +
        `Continuer ?`,
    )

    if (!confirmed) {
      event.preventDefault()
    }

    return confirmed
  }

  // === FUSION ENFANTS ===
  handleSourceChildChange() {
    const selectedSources = this.getSelectedValues(this.sourceChildTargets)
    this.disableSelectedOptions(this.mergeChildTargetTarget, selectedSources)
  }

  confirmMergeChild(event) {
    const selectedSources = this.getSelectedCheckboxes(this.sourceChildTargets)
    const target = this.mergeChildTargetTarget

    if (selectedSources.length === 0) {
      event.preventDefault()
      alert('Veuillez sélectionner au moins une catégorie enfant source.')
      return false
    }

    if (!target.value) {
      event.preventDefault()
      alert('Veuillez sélectionner une catégorie enfant cible.')
      return false
    }

    const confirmed = confirm(
      `⚠️ ATTENTION ⚠️\n\n` +
        `Vous allez fusionner ${selectedSources.length} catégorie(s) ENFANT.\n` +
        `Toutes les questions seront déplacées vers l'enfant cible.\n` +
        `Cette action est IRRÉVERSIBLE.\n\n` +
        `Continuer ?`,
    )

    if (!confirmed) {
      event.preventDefault()
    }

    return confirmed
  }

  // === DÉPLACEMENT QUESTIONS ===
  handleMoveSourceChange() {
    const sourceValue = this.moveSourceTarget.value

    if (sourceValue) {
      this.loadQuestions(sourceValue)
      this.disableSelectedOptions(this.moveTargetTarget, [sourceValue])
    } else {
      this.questionsListTarget.style.display = 'none'
      this.enableAllOptions(this.moveTargetTarget)
    }
  }

  async loadQuestions(categoryId) {
    this.questionsContainerTarget.innerHTML =
      '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Chargement...</div>'

    try {
      const url = this.questionsApiUrlValue.replace('__ID__', categoryId)
      const response = await fetch(url, {
        credentials: 'same-origin',
      })
      const data = await response.json()

      if (data.questions && data.questions.length > 0) {
        this.questionsContainerTarget.innerHTML = data.questions
          .map((q) => this.renderQuestionCheckbox(q))
          .join('')
        this.questionsListTarget.style.display = 'block'
      } else {
        this.questionsContainerTarget.innerHTML =
          '<div class="text-muted text-center small">Aucune question trouvée</div>'
        this.questionsListTarget.style.display = 'none'
      }
    } catch (error) {
      console.error('Erreur lors du chargement des questions:', error)
      this.questionsContainerTarget.innerHTML =
        '<div class="text-danger text-center small">Erreur lors du chargement</div>'
    }
  }

  renderQuestionCheckbox(question) {
    return `
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="question_ids[]"
                       value="${question.id}" id="question_${question.id}">
                <label class="form-check-label small" for="question_${question.id}">
                    <div><strong>${this.escapeHtml(question.content)}</strong></div>
                    <small class="text-muted">
                        <i class="fas fa-star"></i> ${question.difficulty || 'Sans difficulté'} -
                        <i class="fas fa-calendar"></i> ${question.created_at}
                    </small>
                </label>
            </div>
        `
  }

  toggleAllQuestions(checked) {
    const checkboxes = this.questionsContainerTarget.querySelectorAll(
      'input[type="checkbox"]',
    )
    checkboxes.forEach((cb) => (cb.checked = checked))
  }

  moveAllQuestions(event) {
    event.preventDefault()

    const source = this.moveSourceTarget
    const target = this.moveTargetTarget

    if (!source.value || !target.value) {
      alert('Veuillez sélectionner les catégories source et cible.')
      return
    }

    if (
      confirm(
        'Déplacer TOUTES les questions de la catégorie source vers la cible ?',
      )
    ) {
      // Décocher toutes les questions pour indiquer "tout déplacer"
      const checkboxes = this.questionsContainerTarget.querySelectorAll(
        'input[type="checkbox"]',
      )
      checkboxes.forEach((cb) => (cb.checked = false))
      event.target.closest('form').submit()
    }
  }

  // === MODAL QUESTIONS ===
  async showQuestionsModal(event) {
    const categoryId = event.currentTarget.dataset.categoryId
    const categoryName = event.currentTarget.dataset.categoryName

    this.modalTitleTarget.textContent = `Questions de "${categoryName}"`
    this.modalContentTarget.innerHTML =
      '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Chargement...</div>'

    try {
      const url = this.questionsApiUrlValue.replace('__ID__', categoryId)
      const response = await fetch(url, {
        credentials: 'same-origin',
      })

      const data = await response.json()

      if (data.questions && data.questions.length > 0) {
        const html = data.questions
          .map((q) => this.renderQuestionCard(q))
          .join('')
        this.modalContentTarget.innerHTML = html
      } else {
        this.modalContentTarget.innerHTML =
          '<div class="text-muted text-center">Aucune question trouvée</div>'
      }
    } catch (error) {
      console.error('❌ Erreur complète:', error)
      console.error('❌ Stack trace:', error.stack)
      this.modalContentTarget.innerHTML =
        '<div class="text-danger text-center">Erreur lors du chargement: ' +
        error.message +
        '</div>'
    }
  }

  renderQuestionCard(question) {
    return `
            <div class="card mb-2">
                <div class="card-body py-2">
                    <div><strong>${this.escapeHtml(question.content)}</strong></div>
                    <small class="text-muted">
                        <i class="fas fa-star"></i> ${question.difficulty || 'Sans difficulté'} -
                        <i class="fas fa-calendar"></i> ${question.created_at}
                    </small>
                </div>
            </div>
        `
  }

  // === UTILITAIRES ===
  getSelectedCheckboxes(targets) {
    return targets.filter((cb) => cb.checked)
  }

  getSelectedValues(targets) {
    return this.getSelectedCheckboxes(targets).map((cb) => cb.value)
  }

  disableSelectedOptions(selectElement, values) {
    Array.from(selectElement.options).forEach((option) => {
      if (values.includes(option.value)) {
        option.disabled = true
        if (option.selected) {
          selectElement.value = ''
        }
      } else {
        option.disabled = false
      }
    })
  }

  enableAllOptions(selectElement) {
    Array.from(selectElement.options).forEach((option) => {
      option.disabled = false
    })
  }

  escapeHtml(text) {
    const div = document.createElement('div')
    div.textContent = text
    return div.innerHTML
  }
}
