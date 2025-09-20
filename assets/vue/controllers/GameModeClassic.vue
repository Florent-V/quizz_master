<script setup>
import { ref, onMounted, onBeforeUnmount, computed, nextTick, watch } from 'vue'
import IconComponent from '../Components/IconComponent.vue'
import TimerComponent from '../Components/TimerComponent.vue'
import { useQuizSession } from '../Composables/useQuizSession'

// Props
const props = defineProps({
  quizSessionId: {
    type: Number,
    required: true,
  },
})

// --- State ---
const questions = ref([])
const currentQuestionIndex = ref(0)
const totalQuestions = ref(0)
const totalScore = ref(0)
const loading = ref(true)
const error = ref(null)
const selectedAnswer = ref(null)
const answerSubmitted = ref(false)
const lastAnswerResult = ref(null)
const quizSessionAnswerId = ref(null)
const timerRef = ref(null)
const showHint = ref(false)
const isModalOpen = ref(false)
const modalImageUrl = ref('')

const { finishQuiz, abortQuiz } = useQuizSession(props.quizSessionId)

// --- Computed ---
const currentQuestion = computed(() => {
  return questions.value[currentQuestionIndex.value] || null
})

const currentQuestionNumber = computed(() => {
  return currentQuestionIndex.value + 1
})

// --- Watchers ---
watch(currentQuestion, async (newQuestion) => {
  if (newQuestion) {
    // This is triggered when the question changes (including the first one after loading).
    await prepareNextQuestion()
  }
})

// --- API Logic ---

// 1. Fetch all questions at the beginning
const fetchAllQuestions = async () => {
  loading.value = true
  error.value = null
  try {
    // Route: FetchNextQuestions.php
    const response = await fetch(
      `/api/quiz-session/${props.quizSessionId}/next-questions?limit=20`,
    )
    if (!response.ok) {
      const errorData = await response.json().catch(() => ({}))
      throw new Error(
        errorData.error || 'Erreur lors du chargement des questions',
      )
    }
    const data = await response.json()
    if (!data || data.length === 0) {
      throw new Error('Aucune question reçue.')
    }
    questions.value = data
    totalQuestions.value = questions.value.length
    // The watcher on `currentQuestion` will now trigger `prepareNextQuestion`.
  } catch (err) {
    error.value = err.message
    // console.error('Erreur fetchAllQuestions:', err)
  } finally {
    loading.value = false
  }
}

// 2. Prepare the answer slot for the current question
const prepareNextQuestion = async () => {
  showHint.value = false
  selectedAnswer.value = null
  answerSubmitted.value = false
  lastAnswerResult.value = null
  quizSessionAnswerId.value = null

  if (!currentQuestion.value) {
    // console.log('Fin du quiz, plus de questions.')
    if (totalQuestions.value > 0) {
      finishQuiz()
    }
    return
  }

  try {
    // Route: CreateAnswer.php
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

    // We must wait for the next DOM update cycle for the timerRef to be available.
    // This is crucial because the v-if="loading" has just been removed.
    await nextTick()
    timerRef.value?.start() // Start timer via component ref
  } catch (err) {
    error.value = err.message
    // console.error('Erreur prepareNextQuestion:', err)
  }
}

// 3. Submit the user's selected answer
const submitAnswer = async (proposal) => {
  if (answerSubmitted.value || !quizSessionAnswerId.value) return

  selectedAnswer.value = proposal
  answerSubmitted.value = true
  timerRef.value?.stop() // Stop timer via component ref

  try {
    // Route: SubmitAnswer.php
    const response = await fetch(
      `/api/quiz-session/${props.quizSessionId}/submit-answer`,
      {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          quizSessionAnswerId: quizSessionAnswerId.value,
          questionId: currentQuestion.value.id,
          proposalId: proposal.id,
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

    // Find the correct proposal using the `goodAnswerId` from the response.
    const correctProposal = currentQuestion.value.proposals.find(
      (p) => p.id === result.goodAnswerId,
    )

    lastAnswerResult.value = {
      correct: result.isCorrect,
      pointsEarned: pointsEarned,
      timeInSeconds: result.timeSpent,
      explanation:
        currentQuestion.value.explanation ||
        (result.isCorrect ? '' : 'Aucune explication fournie.'),
      correctProposal: correctProposal,
    }
  } catch (err) {
    error.value = err.message
    // console.error('Erreur submitAnswer:', err)
  }
}

// 4. Move to the next question or finish
const nextQuestion = () => {
  if (currentQuestionIndex.value >= totalQuestions.value - 1) {
    finishQuiz()
    return
  }

  currentQuestionIndex.value++
  // The watcher on `currentQuestion` will now trigger `prepareNextQuestion`.
}

// --- Modal Logic ---
const openModal = (imageUrl) => {
  modalImageUrl.value = imageUrl
  isModalOpen.value = true
}

const closeModal = () => {
  isModalOpen.value = false
  modalImageUrl.value = ''
}

const handleKeydown = (e) => {
  if (e.key === 'Escape' && isModalOpen.value) {
    closeModal()
  }
}

// --- Lifecycle ---
onMounted(() => {
  fetchAllQuestions()
  window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
})

// --- Helpers ---
const getDifficultyClass = (difficulty) => {
  if (!difficulty) return 'bg-base-300 text-base-content'
  const difficultyClasses = {
    1: 'bg-green-500/20 text-green-400 border border-green-500/50',
    2: 'bg-blue-500/20 text-blue-400 border border-blue-500/50',
    3: 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/50',
    4: 'bg-orange-500/20 text-orange-400 border border-orange-500/50',
    5: 'bg-red-500/20 text-red-400 border border-red-500/50',
  }
  return difficultyClasses[difficulty.id] || 'bg-base-300 text-base-content'
}

const getProposalClass = (proposal) => {
  if (!answerSubmitted.value) {
    return 'bg-base-200 border-primary/30 hover:bg-primary/10 hover:border-primary/60 text-base-content cursor-pointer shadow-sm hover:shadow-md'
  }

  // While waiting for the API response
  if (!lastAnswerResult.value) {
    if (selectedAnswer.value?.id === proposal.id) {
      return 'bg-info/30 border-info text-info-content shadow-md' // Neutral "selected" color
    }
    // For other proposals, keep the initial style but without hover effects as they are disabled.
    return 'bg-base-200 border-primary/30 text-base-content shadow-sm'
  }

  // After API response
  if (lastAnswerResult.value.correctProposal?.id === proposal.id) {
    return 'bg-green-500/30 border-green-400 text-green-100 shadow-md' // Correct answer
  }

  if (
    selectedAnswer.value?.id === proposal.id &&
    !lastAnswerResult.value.correct
  ) {
    return 'bg-red-500/30 border-red-400 text-red-100 shadow-md' // Selected wrong answer
  }

  return 'bg-base-200/50 border-base-300 text-base-content/70' // Other non-selected answers
}

const getLetterClass = (proposal) => {
  if (!answerSubmitted.value) {
    return 'bg-primary text-primary-content group-hover:bg-accent group-hover:text-accent-content'
  }

  // While waiting for the API response
  if (!lastAnswerResult.value) {
    if (selectedAnswer.value?.id === proposal.id) {
      return 'bg-info text-info-content'
    }
    // Keep initial style (without hover)
    return 'bg-primary text-primary-content'
  }

  // After API response
  if (lastAnswerResult.value.correctProposal?.id === proposal.id) {
    return 'bg-green-500 text-white'
  }
  if (
    selectedAnswer.value?.id === proposal.id &&
    !lastAnswerResult.value.correct
  ) {
    return 'bg-red-500 text-white'
  }
  return 'bg-neutral text-neutral-content'
}

const getTextClass = (proposal) => {
  if (!answerSubmitted.value) {
    return 'text-base-content group-hover:text-base-content font-medium'
  }

  // While waiting for the API response
  if (!lastAnswerResult.value) {
    if (selectedAnswer.value?.id === proposal.id) {
      return 'text-info-content font-medium'
    }
    // Keep initial style
    return 'text-base-content font-medium'
  }

  // After API response
  if (lastAnswerResult.value.correctProposal?.id === proposal.id) {
    return 'text-green-100 font-medium'
  }
  if (
    selectedAnswer.value?.id === proposal.id &&
    !lastAnswerResult.value.correct
  ) {
    return 'text-red-100 font-medium'
  }
  return 'text-base-content/70'
}
</script>

<template>
  <div
    class="w-full flex-grow h-full overflow-y-auto bg-gradient-to-br from-base-200 via-base-300 to-primary/20 p-4"
  >
    <!-- Header avec score et progression -->
    <div class="w-full max-w-4xl mx-auto px-4 sm:px-0 mb-8">
      <div class="card bg-base-100 shadow-2xl border border-primary/20">
        <div class="card-body">
          <div class="flex justify-between items-center mb-4">
            <h1
              class="text-3xl font-bold bg-gradient-to-r from-primary to-secondary bg-clip-text text-transparent"
            >
              Quiz Game
            </h1>
            <div class="text-right">
              <div class="text-2xl font-bold text-accent">
                {{ totalScore }} pts
              </div>
              <div class="text-sm text-base-content/70">
                Question {{ currentQuestionNumber }}/{{ totalQuestions }}
              </div>
            </div>
          </div>

          <!-- Barre de progression -->
          <div class="w-full bg-base-300 rounded-full h-3">
            <div
              class="bg-gradient-to-r from-primary to-secondary h-3 rounded-full transition-all duration-500 ease-out"
              :style="{
                width: `${(currentQuestionNumber / totalQuestions) * 100}%`,
              }"
            ></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Zone de jeu -->
    <div class="max-w-4xl mx-auto">
      <!-- État de chargement -->
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

      <!-- Erreur -->
      <div v-else-if="error" class="alert alert-error">
        <div class="text-center w-full">
          <h3 class="text-xl font-semibold mb-2">Erreur</h3>
          <p>{{ error }}</p>
          <button
            class="btn btn-error btn-outline mt-4"
            @click="fetchAllQuestions"
          >
            Réessayer
          </button>
        </div>
      </div>

      <!-- Question actuelle -->
      <div v-else-if="currentQuestion" class="space-y-6">
        <!-- Carte de la question -->
        <div class="card bg-base-100 shadow-2xl border border-accent/20 w-full">
          <div class="card-body p-8">
            <!-- Niveau de difficulté -->
            <div class="flex items-center justify-between mb-6">
              <div class="flex items-center flex-wrap gap-2">
                <div
                  v-if="currentQuestion.category?.name"
                  class="badge badge-lg badge-soft badge-outline px-4 py-3 font-medium badge-primary"
                >
                  {{ currentQuestion.category.name }}
                </div>
                <div
                  class="badge badge-lg px-4 py-3 font-medium"
                  :class="getDifficultyClass(currentQuestion.difficulty)"
                >
                  {{ currentQuestion.difficulty?.name }} -
                  {{ currentQuestion.difficulty?.basePoints }} pts
                </div>
              </div>

              <TimerComponent ref="timerRef" :is-paused="answerSubmitted" />
            </div>

            <!-- Image de la question si elle existe -->
            <div v-if="currentQuestion.imageUrl" class="mb-6">
              <img
                :src="currentQuestion.imageUrl"
                :alt="'Image pour la question'"
                class="w-full h-auto max-w-md mx-auto rounded-lg shadow-lg"
              />
            </div>

            <!-- Texte de la question -->
            <h2
              class="text-2xl font-bold text-base-content mb-6 leading-relaxed"
            >
              {{ currentQuestion.content }}
            </h2>

            <!-- Hint section -->
            <div v-if="currentQuestion.hint" class="mb-6 text-center">
              <!-- Button to show hint -->
              <button
                v-if="!showHint"
                class="btn btn-sm btn-ghost text-accent items-center inline-flex"
                @click="showHint = true"
              >
                <IconComponent icon-name="fa-lightbulb" class="mr-2" />
                <span>Indice</span>
              </button>

              <!-- The hint itself -->
              <div v-else class="alert bg-info/10 border-info/20 text-left">
                <div class="flex-1">
                  <h4 class="font-semibold text-info mb-2 flex items-center">
                    <IconComponent icon-name="fa-lightbulb" class="mr-2" />
                    <span>Indice :</span>
                  </h4>
                  <p class="text-base-content">{{ currentQuestion.hint }}</p>
                </div>
              </div>
            </div>

            <!-- Propositions -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <button
                v-for="(proposal, index) in currentQuestion.proposals"
                :key="proposal.id"
                :disabled="answerSubmitted"
                class="group relative p-6 text-left rounded-xl border-2 transition-all duration-300 hover:scale-[1.02] transform"
                :class="getProposalClass(proposal, index)"
                @click="submitAnswer(proposal)"
              >
                <!-- Indicateur de lettre -->
                <div class="flex items-start space-x-4">
                  <div
                    class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold transition-all duration-300"
                    :class="getLetterClass(proposal, index)"
                  >
                    {{ String.fromCharCode(65 + index) }}
                  </div>

                  <div class="flex-1">
                    <p
                      class="text-lg transition-all duration-300"
                      :class="getTextClass(proposal)"
                    >
                      {{ proposal.content }}
                    </p>

                    <!-- Image de la proposition si elle existe -->
                    <div v-if="proposal.imageUrl" class="mt-3">
                      <img
                        :src="proposal.imageUrl"
                        :alt="'Image pour la proposition'"
                        class="w-32 h-32 object-cover rounded-lg shadow-md cursor-pointer transition-opacity hover:opacity-80"
                        @click.stop="openModal(proposal.imageUrl)"
                      />
                    </div>
                  </div>
                </div>

                <!-- Animation de sélection -->
                <div
                  v-if="selectedAnswer?.id === proposal.id && answerSubmitted"
                  class="absolute inset-0 rounded-xl animate-pulse"
                  :class="
                    !lastAnswerResult
                      ? 'bg-info/20'
                      : lastAnswerResult.correct
                        ? 'bg-green-500/20'
                        : 'bg-red-500/20'
                  "
                ></div>
              </button>
            </div>

            <!-- Abandon button -->
            <div class="text-center mt-6">
              <button class="btn btn-ghost" @click="abortQuiz">
                <IconComponent icon-name="fa-flag-checkered" />
                <span class="ml-2">Abandonner</span>
                <IconComponent icon-name="fa-flag-checkered" />
              </button>
            </div>

            <!-- Feedback après réponse -->
            <div
              v-if="answerSubmitted && lastAnswerResult"
              class="mt-8 space-y-4"
            >
              <div
                class="card shadow-lg border"
                :class="
                  lastAnswerResult.correct
                    ? 'bg-green-500/10 border-green-400/50'
                    : 'bg-red-500/10 border-red-400/50'
                "
              >
                <div class="card-body p-6">
                  <div class="flex items-center space-x-3 mb-3">
                    <div
                      class="w-10 h-10 rounded-full flex items-center justify-center shadow-md"
                      :class="
                        lastAnswerResult.correct ? 'bg-green-500' : 'bg-red-500'
                      "
                    >
                      <svg
                        class="w-6 h-6 text-white"
                        fill="currentColor"
                        viewBox="0 0 20 20"
                      >
                        <path
                          v-if="lastAnswerResult.correct"
                          fill-rule="evenodd"
                          d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                          clip-rule="evenodd"
                        />
                        <path
                          v-else
                          fill-rule="evenodd"
                          d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                          clip-rule="evenodd"
                        />
                      </svg>
                    </div>
                    <div>
                      <div
                        class="text-xl font-bold"
                        :class="
                          lastAnswerResult.correct
                            ? 'text-green-600'
                            : 'text-red-600'
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

                  <!-- Explication -->
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

                  <!-- Bonne réponse si incorrecte -->
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

              <!-- Bouton suivant -->
              <div class="text-center">
                <button
                  class="btn btn-primary btn-lg shadow-lg"
                  @click="nextQuestion"
                >
                  {{
                    currentQuestionNumber >= totalQuestions
                      ? 'Terminer'
                      : 'Question suivante'
                  }}
                  <svg
                    class="w-5 h-5 ml-2"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M13 7l5 5m0 0l-5 5m5-5H6"
                    />
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Image Modal -->
    <div
      v-if="isModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-80 p-4 transition-opacity duration-300"
      @click="closeModal"
    >
      <div
        class="relative flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-base-100 p-4 shadow-xl"
        @click.stop
      >
        <img
          :src="modalImageUrl"
          alt="Image en grand"
          class="h-full w-full object-contain"
        />
        <button
          class="btn btn-ghost btn-circle absolute top-3 right-3 bg-base-100/50 hover:bg-base-100/80"
          @click="closeModal"
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
