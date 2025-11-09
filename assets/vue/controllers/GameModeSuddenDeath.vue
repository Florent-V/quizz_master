<script setup>
import { ref, onMounted, computed, nextTick, watch, onUnmounted } from 'vue'
import IconComponent from '../Components/IconComponent.vue'
import { useQuizSession } from '../Composables/useQuizSession'

// Props
const props = defineProps({
  quizSessionId: {
    type: String,
    required: true,
  },
})

// --- State ---
const questionsQueue = ref([]) // Queue de questions à traiter
const processedQuestionIds = ref(new Set()) // IDs des questions déjà traitées
const questionCounter = ref(0) // Compteur de questions traitées
const totalScore = ref(0)
const loading = ref(true)
const error = ref(null)
const selectedAnswer = ref(null)
const answerSubmitted = ref(false)
const lastAnswerResult = ref(null)
const quizSessionAnswerId = ref(null)
const goodAnswerId = ref(null)
const noMoreQuestionsAvailable = ref(false) // Flag pour arrêter les appels API
// Timer state
const startTime = ref(null)
const elapsedTime = ref(0)
const timerInterval = ref(null)
// UI state
const isSubmitting = ref(false)
const showImageModal = ref(false)
const isProposalModalOpen = ref(false)
const proposalModalImageUrl = ref('')

const { finishQuiz, abortQuiz } = useQuizSession(props.quizSessionId)

// --- Computed ---
const currentQuestion = computed(() => {
  return questionsQueue.value[0] || null
})

const currentQuestionNumber = computed(() => {
  return questionCounter.value + 1
})

const formattedTime = computed(() => {
  const mins = Math.floor(elapsedTime.value / 60)
  const secs = elapsedTime.value % 60
  return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
})

const remainingQuestionsInQueue = computed(() => {
  return questionsQueue.value.length
})

// --- Watchers ---
watch(currentQuestion, async (newQuestion) => {
  if (newQuestion) {
    await prepareNextQuestion()
  }
})

watch(remainingQuestionsInQueue, (remaining) => {
  // Charger plus de questions quand il en reste 3 ou moins (et que l'API peut encore en fournir)
  if (remaining <= 3 && !loading.value && !noMoreQuestionsAvailable.value) {
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
// Validation et ajout des nouvelles questions (sans erreur, on ignore juste les doublons)
const validateAndAddQuestions = (newQuestions) => {
  const validQuestions = []

  // Récupérer les IDs des questions actuellement en file d'attente
  const currentQueueIds = new Set(questionsQueue.value.map((q) => q.id))

  newQuestions.forEach((question) => {
    const isAlreadyProcessed = processedQuestionIds.value.has(question.id)
    const isInCurrentQueue = currentQueueIds.has(question.id)

    // Si la question n'est ni traitée ni dans la queue, on l'ajoute
    if (!isAlreadyProcessed && !isInCurrentQueue) {
      validQuestions.push(question)
    }
  })

  return validQuestions
}

// --- API Logic ---
// 1. Fetch questions (5 par batch)
const fetchQuestions = async (limit = 5) => {
  try {
    const response = await fetch(
      `/api/quiz-session/${props.quizSessionId}/next-questions?limit=${limit}`,
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
      noMoreQuestionsAvailable.value = true
      throw new Error('Aucune question disponible.')
    }
    questionsQueue.value = data
  } catch (err) {
    error.value = err.message
  } finally {
    loading.value = false
  }
}

// Fetch more questions when needed
const fetchMoreQuestions = async () => {
  if (noMoreQuestionsAvailable.value) {
    return
  }

  try {
    const data = await fetchQuestions(5)

    if (!data || data.length === 0) {
      // Plus de questions disponibles depuis l'API
      noMoreQuestionsAvailable.value = true
      return
    }

    const validQuestions = validateAndAddQuestions(data)
    if (validQuestions.length > 0) {
      questionsQueue.value.push(...validQuestions)
    }
  } catch (err) {
    console.error('Erreur lors du chargement des questions:', err.message)
  }
}

// 2. Prepare the answer slot for the current question
const prepareNextQuestion = async () => {
  selectedAnswer.value = null
  answerSubmitted.value = false
  lastAnswerResult.value = null
  quizSessionAnswerId.value = null
  isSubmitting.value = false
  goodAnswerId.value = null // Reset

  if (!currentQuestion.value) {
    finishQuiz()
    return
  }

  try {
    const response = await fetch(
      `/api/quiz-session/${props.quizSessionId}/create-answer`,
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

  if (navigator.vibrate) {
    navigator.vibrate(100)
  }

  try {
    const response = await fetch(
      `/api/quiz-session/${props.quizSessionId}/submit-answer`,
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
    if (result.goodAnswerId) {
      goodAnswerId.value = result.goodAnswerId
    }

    const pointsEarned = result.answerScore
    totalScore.value = result.totalScore

    const correctProposal = goodAnswerId.value
      ? currentQuestion.value.proposals.find((p) => p.id === goodAnswerId.value)
      : null

    lastAnswerResult.value = {
      correct: result.isCorrect,
      pointsEarned: pointsEarned,
      timeInSeconds: elapsedTime.value,
      explanation:
        currentQuestion.value.explanation ||
        (result.isCorrect ? '' : 'Aucune explication fournie.'),
      correctProposal: correctProposal,
    }

    if (!result.isCorrect) {
      setTimeout(() => {
        finishQuiz()
      }, 3000)
    }
  } catch (err) {
    error.value = err.message
  } finally {
    isSubmitting.value = false
  }
}

// 4. Move to the next question or finish
const nextQuestion = () => {
  if (currentQuestion.value) {
    // Ajouter la question aux questions traitées
    processedQuestionIds.value.add(currentQuestion.value.id)
    // Incrémenter le compteur
    questionCounter.value++
  }

  // Retirer la première question de la queue
  questionsQueue.value.shift()

  // Si la queue est vide, terminer le quiz
  if (questionsQueue.value.length === 0) {
    finishQuiz()
  }
}

// --- UI Helpers ---
const selectProposal = (proposal) => {
  if (answerSubmitted.value) return
  selectedAnswer.value = proposal
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

const openProposalModal = (imageUrl) => {
  proposalModalImageUrl.value = imageUrl
  isProposalModalOpen.value = true
}

const closeProposalModal = () => {
  isProposalModalOpen.value = false
  proposalModalImageUrl.value = ''
}

const handleKeydown = (e) => {
  if (e.key === 'Escape' && isProposalModalOpen.value) {
    closeProposalModal()
  }
}

// --- Styling Helpers ---
const getDifficultyClass = (difficulty) => {
  if (!difficulty) return 'badge-info'
  const difficultyClasses = {
    1: 'badge-success',
    2: 'badge-info',
    3: 'badge-accent',
    4: 'badge-warning',
    5: 'badge-error',
  }
  return difficultyClasses[difficulty.id] || 'badge-info'
}

// --- Lifecycle ---
onMounted(() => {
  fetchInitialQuestions()
  window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  stopTimer()
  window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <div class="min-h-screen bg-base-200 p-4 sm:p-6 lg:p-8">
    <div class="max-w-4xl mx-auto">
      <!-- Header -->
      <div class="card bg-base-100 shadow-2xl mb-8 border border-primary/20">
        <div class="card-body p-6">
          <div class="flex justify-between items-center">
            <!-- Game Mode Title -->
            <h1
              class="text-3xl font-bold bg-gradient-to-r from-primary to-secondary bg-clip-text text-transparent"
            >
              Mort Subite
            </h1>

            <!-- Stats -->
            <div class="flex items-center gap-6 text-right">
              <!-- Total Score -->
              <div class="flex flex-col items-center">
                <div class="text-3xl font-extrabold text-primary">
                  {{ totalScore }}
                </div>
                <div
                  class="text-xs uppercase font-semibold text-base-content/70 tracking-wider"
                >
                  Score
                </div>
              </div>
              <!-- Question Number -->
              <div class="flex flex-col items-center">
                <div class="text-3xl font-extrabold text-secondary">
                  {{ currentQuestionNumber }}
                </div>
                <div
                  class="text-xs uppercase font-semibold text-base-content/70 tracking-wider"
                >
                  Question
                </div>
              </div>
              <!-- Timer -->
              <div class="flex flex-col items-center">
                <div class="font-mono text-3xl font-extrabold text-accent">
                  {{ formattedTime }}
                </div>
                <div
                  class="text-xs uppercase font-semibold text-base-content/70 tracking-wider"
                >
                  Temps
                </div>
              </div>
            </div>
          </div>
          <!-- Points earned for last question -->
          <div
            v-if="lastAnswerResult && lastAnswerResult.correct"
            class="text-center mt-4 p-2 rounded-lg bg-success/10"
          >
            <p class="text-lg font-semibold text-success animate-pulse">
              +{{ lastAnswerResult.pointsEarned }} points !
            </p>
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
              <div
                class="badge badge-info badge-lg badge-soft badge-outline gap-2"
              >
                <IconComponent icon-name="fa-tag" class="w-4 h-4" />
                {{ currentQuestion.category?.name || 'Général' }}
              </div>
              <div
                class="badge badge-lg badge-soft badge-outline gap-2"
                :class="getDifficultyClass(currentQuestion.difficulty)"
              >
                <IconComponent icon-name="fa-chart-bar" class="w-4 h-4" />
                {{ currentQuestion.difficulty?.name || 'Normal' }}
              </div>
            </div>

            <!-- Question Image -->
            <figure
              v-if="currentQuestion.imageUrl"
              class="mb-6 relative group cursor-pointer"
              @click="openImageModal"
            >
              <img
                :src="currentQuestion.imageUrl"
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
                    Voir l'indice
                  </div>
                  <div class="collapse-content">
                    <p>{{ currentQuestion.hint }}</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Proposals -->
            <div class="grid md:grid-cols-2 gap-4">
              <div
                v-for="(proposal, index) in currentQuestion.proposals"
                :key="proposal.id"
                class="flex items-center p-4 rounded-lg border-2 border-base-300 cursor-pointer transition-all duration-300 proposal-card"
                :style="{ animationDelay: `${index * 0.1}s` }"
                :class="{
                  // --- After submission ---
                  // Correct answer (selected or revealed) -> GREEN
                  'ring-2 ring-success bg-success/10 border-success':
                    answerSubmitted &&
                    lastAnswerResult &&
                    ((lastAnswerResult.correct &&
                      selectedAnswer.id === proposal.id) ||
                      (!lastAnswerResult.correct &&
                        goodAnswerId === proposal.id)),
                  // Incorrectly selected answer -> RED
                  'ring-2 ring-error bg-error/10 border-error':
                    answerSubmitted &&
                    lastAnswerResult &&
                    !lastAnswerResult.correct &&
                    selectedAnswer.id === proposal.id,
                  // Fade out other proposals after submission
                  'opacity-50':
                    answerSubmitted &&
                    selectedAnswer.id !== proposal.id &&
                    goodAnswerId !== proposal.id,

                  // --- Before submission ---
                  // Neutral selection highlight
                  'ring-2 ring-neutral':
                    !answerSubmitted && selectedAnswer?.id === proposal.id,

                  // --- Base classes ---
                  'hover:border-primary hover:scale-105': !answerSubmitted,
                }"
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
                  <div v-if="proposal.imageUrl" class="avatar">
                    <div
                      class="w-16 h-16 rounded-lg border-2 border-base-300 overflow-hidden cursor-pointer group/avatar"
                      @click.stop="openProposalModal(proposal.imageUrl)"
                    >
                      <img
                        :src="proposal.imageUrl"
                        alt="Image de la réponse"
                        class="w-full h-full object-cover transition-transform duration-300 group-hover/avatar:scale-110"
                      />
                    </div>
                  </div>
                  <span class="text-lg">{{ proposal.content }}</span>
                </div>

                <!-- Status Icon -->
                <div
                  class="flex-none selection-indicator transition-all duration-300 ml-auto"
                >
                  <!-- Show Checkmark for the correct answer (selected or revealed) -->
                  <IconComponent
                    v-if="
                      answerSubmitted &&
                      lastAnswerResult &&
                      ((lastAnswerResult.correct &&
                        selectedAnswer.id === proposal.id) ||
                        (!lastAnswerResult.correct &&
                          goodAnswerId === proposal.id))
                    "
                    icon-name="fa-check-circle"
                    class="w-6 h-6 text-success"
                  />
                  <!-- Show Cross for the incorrectly selected answer -->
                  <IconComponent
                    v-else-if="
                      answerSubmitted &&
                      lastAnswerResult &&
                      !lastAnswerResult.correct &&
                      selectedAnswer.id === proposal.id
                    "
                    icon-name="fa-times-circle"
                    class="w-6 h-6 text-error"
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

    <!-- Question Image Modal -->
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
          :src="currentQuestion.imageUrl"
          alt="Question image"
          class="w-full h-auto rounded-lg"
        />
      </div>
      <div class="modal-backdrop" @click="closeImageModal"></div>
    </div>

    <!-- Proposal Image Modal -->
    <div
      v-if="isProposalModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-80 p-4 transition-opacity duration-300"
      @click="closeProposalModal"
    >
      <div
        class="relative flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-base-100 p-4 shadow-xl"
        @click.stop
      >
        <img
          :src="proposalModalImageUrl"
          alt="Image en grand"
          class="h-full w-full object-contain"
        />
        <button
          class="btn btn-ghost btn-circle absolute top-3 right-3 bg-base-100/50 hover:bg-base-100/80"
          @click="closeProposalModal"
        >
          <svg
            class="w-6 h-6"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M6 18L18 6M6 6l12 12"
            ></path>
          </svg>
        </button>
      </div>
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

.selection-indicator {
  transition: all 0.3s ease;
}
</style>
