<script setup>
import { ref, onMounted, computed, nextTick, watch, onUnmounted } from 'vue'
import IconComponent from '../Components/IconComponent.vue'

// Props
const props = defineProps({
  quizSessionId: {
    type: Number,
    required: true,
  },
  pseudo: {
    type: String,
    required: true,
  },
})

// --- State ---
const questions = ref([])
const processedQuestionIds = ref(new Set())
const currentQuestionIndex = ref(0)
const totalScore = ref(0)
const loading = ref(true)
const error = ref(null)

const selectedAnswer = ref(null)
const answerSubmitted = ref(false)
const lastAnswerResult = ref(null)
const quizSessionAnswerId = ref(null)

// Timer state
const startTime = ref(null)
const elapsedTime = ref(0)
const timerInterval = ref(null)

// UI state
const isSubmitting = ref(false)
const showImageModal = ref(false)

// --- Computed ---
const currentQuestion = computed(() => {
  return questions.value[currentQuestionIndex.value] || null
})

const currentQuestionNumber = computed(() => {
  return currentQuestionIndex.value + 1
})

const formattedTime = computed(() => {
  const mins = Math.floor(elapsedTime.value / 60)
  const secs = elapsedTime.value % 60
  return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
})

const remainingQuestions = computed(() => {
  return questions.value.length - currentQuestionIndex.value - 1
})

// --- Watchers ---
watch(currentQuestion, async (newQuestion) => {
  if (newQuestion) {
    await prepareNextQuestion()
  }
})

watch(remainingQuestions, (remaining) => {
  // Télécharger plus de questions quand il en reste 3 ou moins
  if (remaining <= 3 && !loading.value) {
    fetchMoreQuestions()
  }
})

// --- Timer Logic ---
const startTimer = () => {
  startTime.value = Date.now()
  elapsedTime.value = 0

  timerInterval.value = setInterval(() => {
    elapsedTime.value = Math.floor((Date.now() - startTime.value) / 1000)
  }, 1000)
}

const stopTimer = () => {
  if (timerInterval.value) {
    clearInterval(timerInterval.value)
    timerInterval.value = null
  }
}

// --- Duplicate Questions Logic ---
// 1. Validation et ajout des nouvelles questions
const validateAndAddQuestions = (newQuestions) => {
  const duplicates = []
  const validQuestions = []

  // Récupérer les IDs des questions actuellement en file d'attente
  const currentQueueIds = new Set(questions.value.map((q) => q.id))

  newQuestions.forEach((question) => {
    const isAlreadyProcessed = processedQuestionIds.value.has(question.id)
    const isInCurrentQueue = currentQueueIds.has(question.id)

    if (isAlreadyProcessed || isInCurrentQueue) {
      duplicates.push(question.id)
      console.log(`🔍 Question ${question.id} détectée comme doublon:`, {
        alreadyProcessed: isAlreadyProcessed,
        inQueue: isInCurrentQueue,
      })
    } else {
      validQuestions.push(question)
    }
  })

  // Si on détecte des doublons, c'est suspect
  if (duplicates.length > 0) {
    console.error(
      '🚨 SÉCURITÉ: Tentative de duplication de questions détectée:',
      duplicates,
    )

    // Si plus de 50% de doublons = très suspect, on arrête
    if (duplicates.length >= newQuestions.length / 2) {
      throw new Error('Manipulation détectée. Session terminée pour sécurité.')
    }
  }
  return validQuestions
}

// --- API Logic ---
// 1. Fetch questions (5 par batch)
const fetchQuestions = async (limit = 5) => {
  try {
    const response = await fetch(
      `/quiz-sessions/${props.quizSessionId}/next-questions?limit=${limit}`,
    )
    if (!response.ok) {
      const errorData = await response.json().catch(() => ({}))
      throw new Error(
        errorData.error || 'Erreur lors du chargement des questions',
      )
    }
    const data = await response.json()
    return data || []
  } catch (err) {
    throw new Error(err.message)
  }
}

// Fetch initial questions
const fetchInitialQuestions = async () => {
  loading.value = true
  error.value = null
  try {
    const data = await fetchQuestions(5)
    if (data.length === 0) {
      throw new Error('Aucune question reçue.')
    }
    // Pour les questions initiales, on les ajoute directement
    questions.value = data
    console.log(
      '🎯 Questions initiales chargées:',
      data.map((q) => q.id),
    )
  } catch (err) {
    error.value = err.message
  } finally {
    loading.value = false
  }
}

// Fetch more questions when needed
const fetchMoreQuestions = async () => {
  try {
    console.log(
      '📋 Questions en file:',
      questions.value.map((q) => q.id),
    )
    console.log(
      '✅ Questions traitées:',
      Array.from(processedQuestionIds.value),
    )

    const data = await fetchQuestions(5)

    if (data && data.length > 0) {
      // Validation et filtrage des doublons
      const validQuestions = validateAndAddQuestions(data)

      if (validQuestions.length > 0) {
        questions.value.push(...validQuestions)
        console.log(`➕ ${validQuestions.length} nouvelles questions ajoutées`)
        console.log(
          '🆕 Nouvelles questions:',
          validQuestions.map((q) => q.id),
        )
      } else {
        console.log('⚠️ Aucune nouvelle question valide à ajouter')
      }
    }
  } catch (err) {
    console.error(
      '❌ Erreur lors du chargement de questions supplémentaires:',
      err,
    )
    // Si c'est une erreur de sécurité, on redirige
    if (err.message.includes('Manipulation détectée')) {
      error.value = 'Session terminée pour des raisons de sécurité.'
      setTimeout(() => {
        window.location.href = `/quiz/play/sudden-death/game-over/${props.quizSessionId}`
      }, 2000)
    }
  }
}

// 2. Prepare the answer slot for the current question
const prepareNextQuestion = async () => {
  selectedAnswer.value = null
  answerSubmitted.value = false
  lastAnswerResult.value = null
  quizSessionAnswerId.value = null
  isSubmitting.value = false

  if (!currentQuestion.value) {
    finishQuiz()
    return
  }

  try {
    const response = await fetch(
      `/quiz-sessions/${props.quizSessionId}/create-answer`,
      {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ questionId: currentQuestion.value.id }),
      },
    )
    if (!response.ok) {
      const errorData = await response.json().catch(() => ({}))
      throw new Error(
        errorData.error || 'Erreur lors de la préparation de la réponse',
      )
    }
    const data = await response.json()
    quizSessionAnswerId.value = data.quizSessionAnswerId

    await nextTick()
    startTimer()
  } catch (err) {
    error.value = err.message
  }
}

// 3. Submit the user's selected answer
const submitAnswer = async () => {
  if (
    answerSubmitted.value ||
    !quizSessionAnswerId.value ||
    !selectedAnswer.value
  )
    return

  isSubmitting.value = true
  answerSubmitted.value = true
  stopTimer()

  // Haptic feedback on mobile
  if (navigator.vibrate) {
    navigator.vibrate(100)
  }

  try {
    const response = await fetch(
      `/quiz-sessions/${props.quizSessionId}/submit-answer`,
      {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          quizSessionAnswerId: quizSessionAnswerId.value,
          questionId: currentQuestion.value.id,
          proposalId: selectedAnswer.value.id,
        }),
      },
    )

    if (!response.ok) {
      const errorData = await response.json().catch(() => ({}))
      throw new Error(errorData.error || "Erreur lors de l'envoi de la réponse")
    }

    const result = await response.json()
    const pointsEarned = result.score - totalScore.value
    totalScore.value = result.score

    const correctProposal = currentQuestion.value.proposals.find(
      (p) => p.isCorrect,
    )

    lastAnswerResult.value = {
      correct: result.isCorrect,
      pointsEarned: pointsEarned,
      timeInSeconds: elapsedTime.value,
      explanation:
        currentQuestion.value.explanation ||
        (result.isCorrect ? '' : 'Aucune explication fournie.'),
      correctProposal: correctProposal,
    }

    // REDIRECTION VERS GAME-OVER SI RÉPONSE INCORRECTE (MODE MORT SUBITE)
    if (!result.isCorrect) {
      setTimeout(() => {
        window.location.href = `/quiz/play/sudden-death/game-over/${props.quizSessionId}`
      }, 3000) // Délai de 3 secondes pour voir le feedback
    }
  } catch (err) {
    error.value = err.message
  } finally {
    isSubmitting.value = false
  }
}

// 4. Move to the next question or finish
const nextQuestion = () => {
  // Marquer la question actuelle comme traitée
  if (currentQuestion.value) {
    processedQuestionIds.value.add(currentQuestion.value.id)
    console.log('✅ Question terminée:', currentQuestion.value.id)
  }

  if (
    currentQuestionIndex.value >= questions.value.length - 1 &&
    remainingQuestions.value === 0
  ) {
    finishQuiz()
    return
  }

  currentQuestionIndex.value++
}

// 5. Finish the quiz and redirect
const finishQuiz = () => {
  window.location.href = `/quiz/${props.quizSessionId}/finish`
}

// 6. Abandon quiz
const abortQuiz = () => {
  if (confirm('Êtes-vous sûr de vouloir abandonner le quiz ?')) {
    window.location.href = '/quiz/restart'
  }
}

// --- UI Helpers ---
const selectProposal = (proposal) => {
  if (answerSubmitted.value) return

  selectedAnswer.value = proposal

  // Haptic feedback
  if (navigator.vibrate) {
    navigator.vibrate(50)
  }
}

const openImageModal = () => {
  showImageModal.value = true
}

const closeImageModal = () => {
  showImageModal.value = false
}

// --- Styling Helpers ---
const getDifficultyClass = (difficulty) => {
  if (!difficulty) return 'badge-info'
  const difficultyClasses = {
    1: 'badge-success',
    2: 'badge-info',
    3: 'badge-warning',
    4: 'badge-secondary',
    5: 'badge-error',
  }
  return difficultyClasses[difficulty.id] || 'badge-info'
}

const getProposalCardClass = (proposal) => {
  const baseClass =
    'flex items-center p-4 rounded-lg border-2 border-base-300 cursor-pointer transition-all duration-300 hover:scale-105 hover:shadow-xl hover:border-primary proposal-card'

  if (answerSubmitted.value) {
    if (lastAnswerResult.value?.correctProposal?.id === proposal.id) {
      return `${baseClass} ring-2 ring-success bg-success/10 border-success`
    }
    if (
      selectedAnswer.value?.id === proposal.id &&
      !lastAnswerResult.value?.correct
    ) {
      return `${baseClass} ring-2 ring-error bg-error/10 border-error`
    }
    return `${baseClass} opacity-70`
  }

  if (selectedAnswer.value?.id === proposal.id) {
    return `${baseClass} ring-2 ring-primary bg-primary/5 border-primary`
  }

  return `${baseClass} hover:bg-base-200`
}

// --- Fonctions de debug (optionnelles, à retirer en production) ---
const logQuestionState = () => {
  console.log('📊 État des questions:')
  console.log(
    "  - En file d'attente (questions):",
    questions.value.map((q) => q.id),
  )
  console.log(
    '  - Traitées (processedQuestionIds):',
    Array.from(processedQuestionIds.value),
  )
  console.log('  - Question actuelle:', currentQuestion.value?.id)
  console.log('  - Index actuel:', currentQuestionIndex.value)
}

// Watcher pour debug (à retirer en production)
watch(
  [currentQuestionIndex, questions],
  () => {
    logQuestionState()
  },
  { deep: true },
)

// Nettoyage au démontage
onUnmounted(() => {
  stopTimer()
  console.log('🧹 Composant démonté - État final:')
  logQuestionState()
})

// --- Lifecycle ---
onMounted(() => {
  fetchInitialQuestions()
})

onUnmounted(() => {
  stopTimer()
})
</script>

<template>
  <div class="min-h-screen bg-base-200 p-4 sm:p-6 lg:p-8">
    <div class="max-w-4xl mx-auto">
      <!-- Header -->
      <div class="text-center mb-8">
        <div class="flex justify-between items-center mb-6">
          <div class="text-left">
            <h1 class="text-2xl font-bold">
              <IconComponent
                icon-name="fa-graduation-cap"
                class="inline-block w-6 h-6 mr-2"
              />
              {{ pseudo }}
            </h1>
            <p class="text-base-content/70">
              Score:
              <span class="font-bold text-primary">{{ totalScore }}</span>
            </p>
          </div>

          <div class="text-right">
            <p class="text-lg font-semibold">
              Question N°: {{ currentQuestionNumber }}
            </p>
            <div class="text-sm text-base-content/70 mt-1">
              Temps: <span class="font-mono">{{ formattedTime }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="text-center">
        <div class="card bg-base-100 shadow-xl inline-block">
          <div class="card-body flex-row items-center">
            <span
              class="loading loading-spinner loading-md text-primary mr-3"
            ></span>
            <span class="text-base-content text-lg">Chargement...</span>
          </div>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="alert alert-error">
        <div class="text-center w-full">
          <h3 class="text-xl font-semibold mb-2">Erreur</h3>
          <p>{{ error }}</p>
          <button
            class="btn btn-error btn-outline mt-4"
            @click="fetchInitialQuestions"
          >
            Réessayer
          </button>
        </div>
      </div>

      <!-- Question Display -->
      <div v-else-if="currentQuestion" class="space-y-6">
        <!-- Question Card -->
        <div
          class="card bg-base-100 shadow-2xl border border-base-300 mb-8 animate-fade-in-up"
        >
          <div class="card-body">
            <!-- Difficulty and Category -->
            <div class="flex justify-between items-center mb-6">
              <div class="badge badge-info badge-lg gap-2">
                <IconComponent icon-name="fa-tag" class="w-4 h-4" />
                {{ currentQuestion.category?.name || 'Général' }}
              </div>
              <div
                class="badge badge-lg gap-2"
                :class="getDifficultyClass(currentQuestion.difficulty)"
              >
                <IconComponent icon-name="fa-chart-bar" class="w-4 h-4" />
                {{ currentQuestion.difficulty?.name || 'Normal' }}
              </div>
            </div>

            <!-- Question Image -->
            <figure
              v-if="currentQuestion.image || currentQuestion.imageName"
              class="mb-6 relative group cursor-pointer"
              @click="openImageModal"
            >
              <img
                :src="
                  currentQuestion.image ||
                  `/uploads/questions/${currentQuestion.imageName}`
                "
                alt="Image de la question"
                class="max-h-72 w-auto mx-auto rounded-lg shadow-md border-2 border-base-300 transition-all duration-300 group-hover:border-primary"
              />
              <div
                class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity"
              >
                <div
                  class="btn btn-circle btn-sm btn-ghost bg-black/20 text-white hover:bg-black/40"
                >
                  <IconComponent icon-name="pr-search-plus" class="w-4 h-4" />
                </div>
              </div>
            </figure>

            <!-- Question Content -->
            <div class="text-center mb-8">
              <h2 class="text-2xl font-bold leading-relaxed">
                {{ currentQuestion.content }}
              </h2>

              <!-- Hint -->
              <div v-if="currentQuestion.hint" class="mt-4">
                <div
                  class="collapse collapse-arrow border border-base-300 bg-base-200 max-w-md mx-auto"
                >
                  <input type="checkbox" />
                  <div class="collapse-title font-medium">
                    <IconComponent
                      icon-name="fa-lightbulb"
                      class="inline-block w-4 h-4"
                    />
                    💡 Voir l'indice
                  </div>
                  <div class="collapse-content">
                    <p>{{ currentQuestion.hint }}</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Proposals -->
            <div class="grid gap-4">
              <div
                v-for="(proposal, index) in currentQuestion.proposals"
                :key="proposal.id"
                :class="getProposalCardClass(proposal)"
                :style="{ animationDelay: `${index * 0.1}s` }"
                @click="selectProposal(proposal)"
              >
                <input
                  type="radio"
                  :value="proposal.id"
                  :checked="selectedAnswer?.id === proposal.id"
                  class="radio radio-primary"
                  :disabled="answerSubmitted"
                  @change="selectProposal(proposal)"
                />

                <div class="ml-4 flex-1 flex items-center gap-4">
                  <div
                    v-if="proposal.image || proposal.imageName"
                    class="avatar"
                  >
                    <div
                      class="w-16 h-16 rounded-lg border-2 border-base-300 overflow-hidden"
                    >
                      <img
                        :src="
                          proposal.image ||
                          `/uploads/proposals/${proposal.imageName}`
                        "
                        alt="Image de la réponse"
                        class="w-16 h-16 rounded-lg object-cover"
                      />
                    </div>
                  </div>
                  <span class="text-lg">{{ proposal.content }}</span>
                </div>

                <!-- Selection Indicator -->
                <div
                  v-if="selectedAnswer?.id === proposal.id"
                  class="flex-none selection-indicator transition-all duration-300"
                >
                  <IconComponent
                    icon-name="fa-check-circle"
                    class="w-6 h-6 text-primary animate-pulse"
                  />
                </div>
              </div>
            </div>

            <!-- Submit Button -->
            <div class="text-center mt-8">
              <button
                type="button"
                class="btn btn-primary btn-lg"
                :disabled="!selectedAnswer || answerSubmitted"
                @click="submitAnswer"
              >
                <span
                  v-if="isSubmitting"
                  class="loading loading-spinner loading-sm mr-2"
                ></span>
                <IconComponent
                  v-else
                  icon-name="fa-check-circle"
                  class="w-6 h-6 mr-2"
                />
                {{ isSubmitting ? 'Traitement...' : 'Valider ma réponse' }}
              </button>
            </div>

            <!-- Feedback after answer -->
            <div
              v-if="answerSubmitted && lastAnswerResult"
              class="mt-8 space-y-4"
            >
              <div
                class="card shadow-lg border"
                :class="
                  lastAnswerResult.correct
                    ? 'bg-success/10 border-success/50'
                    : 'bg-error/10 border-error/50'
                "
              >
                <div class="card-body p-6">
                  <div class="flex items-center space-x-3 mb-3">
                    <div
                      class="w-10 h-10 rounded-full flex items-center justify-center shadow-md"
                      :class="
                        lastAnswerResult.correct ? 'bg-success' : 'bg-error'
                      "
                    >
                      <IconComponent
                        :icon-name="
                          lastAnswerResult.correct ? 'fa-check' : 'fa-times'
                        "
                        class="w-6 h-6 text-white"
                      />
                    </div>
                    <div>
                      <div
                        class="text-xl font-bold"
                        :class="
                          lastAnswerResult.correct
                            ? 'text-success'
                            : 'text-error'
                        "
                      >
                        {{
                          lastAnswerResult.correct ? 'Correct !' : 'Incorrect'
                        }}
                      </div>
                      <div class="text-accent font-semibold text-lg">
                        +{{ lastAnswerResult.pointsEarned }} points ({{
                          lastAnswerResult.timeInSeconds
                        }}s)
                      </div>
                    </div>
                  </div>

                  <!-- Explanation -->
                  <div
                    v-if="lastAnswerResult.explanation"
                    class="alert bg-info/10 border-info/20"
                  >
                    <div>
                      <h4 class="font-semibold text-info mb-2">
                        Explication :
                      </h4>
                      <p class="text-base-content">
                        {{ lastAnswerResult.explanation }}
                      </p>
                    </div>
                  </div>

                  <!-- Correct answer if incorrect -->
                  <div
                    v-if="
                      !lastAnswerResult.correct &&
                      lastAnswerResult.correctProposal
                    "
                    class="alert alert-success mt-3"
                  >
                    <div>
                      <strong>Bonne réponse :</strong>
                      {{ lastAnswerResult.correctProposal.content }}
                    </div>
                  </div>
                </div>
              </div>

              <!-- Next Button (seulement si réponse correcte) -->
              <div v-if="lastAnswerResult.correct" class="text-center">
                <button
                  class="btn btn-primary btn-lg shadow-lg"
                  @click="nextQuestion"
                >
                  Question suivante
                  <IconComponent
                    icon-name="fa-arrow-right"
                    class="w-5 h-5 ml-2"
                  />
                </button>
              </div>

              <!-- Game Over Message (si réponse incorrecte) -->
              <div v-else class="text-center">
                <div class="alert alert-warning">
                  <div>
                    <IconComponent icon-name="fa-skull" class="w-6 h-6 mr-2" />
                    <span class="font-bold">Fin du jeu !</span>
                    <p class="mt-2">
                      Redirection vers l'écran de fin dans quelques secondes...
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="text-center">
          <button class="btn btn-ghost" @click="abortQuiz">
            <IconComponent icon-name="fa-home" class="w-5 h-5 mr-2" />
            Abandonner le quiz
          </button>
        </div>
      </div>
    </div>

    <!-- Image Modal -->
    <div v-if="showImageModal" class="modal modal-open">
      <div class="modal-box w-11/12 max-w-5xl">
        <button
          class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2"
          @click="closeImageModal"
        >
          ✕
        </button>
        <h3 class="font-bold text-lg mb-4">Image de la question</h3>
        <img
          :src="
            currentQuestion.image ||
            `/uploads/questions/${currentQuestion.imageName}`
          "
          alt="Question image"
          class="w-full h-auto rounded-lg"
        />
      </div>
      <div class="modal-backdrop" @click="closeImageModal"></div>
    </div>
  </div>
</template>

<style scoped>
@keyframes fade-in-up {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.animate-fade-in-up {
  opacity: 0;
  transform: translateY(20px);
  animation: fade-in-up 0.5s ease-out forwards;
}

.proposal-card {
  transition: all 0.3s ease;
}

.proposal-card:hover:not(.opacity-70) {
  transform: translateY(-2px) scale(1.02);
}

.selection-indicator {
  transition: all 0.3s ease;
}
</style>
