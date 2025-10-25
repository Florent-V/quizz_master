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
    'modalTitle',
    'modalContent',
  ]

  connect() {
    this.initializeParentMerge()
    this.initializeChildMerge()
    this.initializeMoveQuestions()
  }

  initializeParentMerge() {
    this.sourceParentTargets.forEach((checkbox) => {
      checkbox.addEventListener('change', () =>
        this.updateParentTargetOptions(),
      )
    })
  }

  initializeChildMerge() {
    this.sourceChildTargets.forEach((checkbox) => {
      checkbox.addEventListener('change', () => this.updateChildTargetOptions())
    })
  }

  initializeMoveQuestions() {
    if (this.hasMoveSourceTarget) {
      this.moveSourceTarget.addEventListener('change', () =>
        this.handleSourceChange(),
      )
    }

    if (this.hasSelectAllQuestionsTarget) {
      this.selectAllQuestionsTarget.addEventListener('change', (e) => {
        const questionCheckboxes =
          this.questionsContainerTarget.querySelectorAll(
            'input[type="checkbox"]',
          )
        questionCheckboxes.forEach((cb) => (cb.checked = e.target.checked))
      })
    }
  }

  updateParentTargetOptions() {
    const selectedSources = Array.from(this.sourceParentTargets)
      .filter((cb) => cb.checked)
      .map((cb) => cb.value)

    Array.from(this.mergeParentTargetTarget.options).forEach((option) => {
      if (selectedSources.includes(option.value)) {
        option.disabled = true
        if (option.selected) {
          this.mergeParentTargetTarget.value = ''
        }
      } else {
        option.disabled = false
      }
    })
  }

  updateChildTargetOptions() {
    const selectedSources = Array.from(this.sourceChildTargets)
      .filter((cb) => cb.checked)
      .map((cb) => cb.value)

    Array.from(this.mergeChildTargetTarget.options).forEach((option) => {
      if (selectedSources.includes(option.value)) {
        option.disabled = true
        if (option.selected) {
          this.mergeChildTargetTarget.value = ''
        }
      } else {
        option.disabled = false
      }
    })
  }

  handleSourceChange() {
    const sourceValue = this.moveSourceTarget.value

    if (sourceValue) {
      this.loadQuestions(sourceValue)
      Array.from(this.moveTargetTarget.options).forEach((option) => {
        option.disabled = option.value === sourceValue
        if (option.selected && option.disabled) {
          this.moveTargetTarget.value = ''
        }
      })
    } else {
      this.questionsListTarget.style.display = 'none'
      Array.from(this.moveTargetTarget.options).forEach((option) => {
        option.disabled = false
      })
    }
  }

  loadQuestions(categoryId) {
    this.questionsContainerTarget.innerHTML =
      '<div class="text-center"><span class="loading loading-spinner loading-md"></span> Chargement...</div>'

    const apiUrl = this.element.dataset.apiQuestionsUrl.replace(
      '__ID__',
      categoryId,
    )

    fetch(apiUrl)
      .then((response) => response.json())
      .then((data) => {
        if (data.questions && data.questions.length > 0) {
          this.questionsContainerTarget.innerHTML = data.questions
            .map(
              (q) => `
                        <div class="form-control">
                            <label class="label cursor-pointer justify-start gap-2">
                                <input type="checkbox" class="checkbox checkbox-sm" name="question_ids[]" value="${q.id}" />
                                <span class="label-text">
                                    <div class="font-semibold">${q.content}</div>
                                    <div class="text-xs opacity-70">
                                        ⭐ ${q.difficulty || 'Sans difficulté'} - 📅 ${q.created_at}
                                    </div>
                                </span>
                            </label>
                        </div>
                    `,
            )
            .join('')
          this.questionsListTarget.style.display = 'block'
        } else {
          this.questionsContainerTarget.innerHTML =
            '<div class="text-center text-base-content/60">Aucune question trouvée</div>'
          this.questionsListTarget.style.display = 'none'
        }
      })
      .catch((error) => {
        console.error('Erreur:', error)
        this.questionsContainerTarget.innerHTML =
          '<div class="text-center text-error">Erreur lors du chargement</div>'
      })
  }

  loadQuestionsInModal(event) {
    const categoryId = event.params.categoryId
    const categoryName = event.params.categoryName

    this.modalTitleTarget.textContent = `Questions de "${categoryName}"`
    this.modalContentTarget.innerHTML =
      '<div class="text-center"><span class="loading loading-spinner loading-md"></span> Chargement...</div>'

    const apiUrl = this.element.dataset.apiQuestionsUrl.replace(
      '__ID__',
      categoryId,
    )

    fetch(apiUrl)
      .then((response) => response.json())
      .then((data) => {
        if (data.questions && data.questions.length > 0) {
          this.modalContentTarget.innerHTML = data.questions
            .map(
              (q) => `
                        <div class="card bg-base-200 mb-2">
                            <div class="card-body p-3">
                                <div class="font-semibold">${q.content}</div>
                                <div class="text-xs opacity-70">
                                    ⭐ ${q.difficulty || 'Sans difficulté'} - 📅 ${q.created_at}
                                </div>
                            </div>
                        </div>
                    `,
            )
            .join('')
        } else {
          this.modalContentTarget.innerHTML =
            '<div class="text-center text-base-content/60">Aucune question trouvée</div>'
        }
      })
      .catch((error) => {
        console.error('Erreur:', error)
        this.modalContentTarget.innerHTML =
          '<div class="text-center text-error">Erreur lors du chargement</div>'
      })
  }

  confirmMergeParent(event) {
    const selectedSources = this.sourceParentTargets.filter((cb) => cb.checked)
    const target = this.mergeParentTargetTarget

    if (selectedSources.length === 0) {
      alert('Veuillez sélectionner au moins une catégorie parent source.')
      event.preventDefault()
      return false
    }

    if (!target.value) {
      alert('Veuillez sélectionner une catégorie parent cible.')
      event.preventDefault()
      return false
    }

    if (
      !confirm(
        `⚠️ ATTENTION ⚠️\n\nVous allez fusionner ${selectedSources.length} catégorie(s) PARENT.\nLes catégories enfants seront déplacées vers le parent cible.\nCette action est IRRÉVERSIBLE.\n\nContinuer ?`,
      )
    ) {
      event.preventDefault()
      return false
    }

    return true
  }

  confirmMergeChild(event) {
    const selectedSources = this.sourceChildTargets.filter((cb) => cb.checked)
    const target = this.mergeChildTargetTarget

    if (selectedSources.length === 0) {
      alert('Veuillez sélectionner au moins une catégorie enfant source.')
      event.preventDefault()
      return false
    }

    if (!target.value) {
      alert('Veuillez sélectionner une catégorie enfant cible.')
      event.preventDefault()
      return false
    }

    if (
      !confirm(
        `⚠️ ATTENTION ⚠️\n\nVous allez fusionner ${selectedSources.length} catégorie(s) ENFANT.\nToutes les questions seront déplacées vers l'enfant cible.\nCette action est IRRÉVERSIBLE.\n\nContinuer ?`,
      )
    ) {
      event.preventDefault()
      return false
    }

    return true
  }

  moveAllQuestions(event) {
    event.preventDefault()

    const sourceSelect = this.moveSourceTarget
    const targetSelect = this.moveTargetTarget

    if (!sourceSelect.value || !targetSelect.value) {
      alert('Veuillez sélectionner les catégories source et cible.')
      return
    }

    if (
      confirm(
        'Déplacer TOUTES les questions de la catégorie source vers la cible ?',
      )
    ) {
      const questionCheckboxes = this.questionsContainerTarget.querySelectorAll(
        'input[type="checkbox"]',
      )
      questionCheckboxes.forEach((cb) => (cb.checked = false))
      event.target.closest('form').submit()
    }
  }
}
