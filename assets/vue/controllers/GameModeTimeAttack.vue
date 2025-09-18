<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import IconComponent from '../Components/IconComponent.vue'
import { useQuizSession } from '../Composables/useQuizSession'

const props = defineProps({
  quizSessionId: {
    type: Number,
    required: true,
  },
})

const loading = ref(true)
const currentQuestion = ref(null)
const quizSessionAnswerId = ref(null)
const questionNumber = ref(0)
const selectedProposal = ref(null)
const isSubmitting = ref(false)
const answerStatus = ref('') // 'correct' or 'incorrect'
const goodAnswerId = ref(null)
const score = ref(0)
const error = ref(null)
const timeLeft = ref(60)
const showHint = ref(false)
const isProposalModalOpen = ref(false)
const proposalModalImageUrl = ref('')

const { finishQuiz, abortQuiz } = useQuizSession(props.quizSessionId)

let gameTimer = null
let nextQuestionPromise = null

// Computed properties for dynamic styling, compatible with DaisyUI themes
const difficultyBadgeClass = computed(() => {
  if (!currentQuestion.value?.difficulty?.name) return 'badge-ghost'
  switch (currentQuestion.value.difficulty.name.toLowerCase()) {
    case 'facile':
      return 'badge-success'
    case 'moyen':
      return 'badge-warning'
    case 'difficile':
      return 'badge-error'
    default:
      return 'badge-ghost'
  }
})

const difficultyGlowClass = computed(() => {
  if (!currentQuestion.value?.difficulty?.name) return 'border-base-300'
  switch (currentQuestion.value.difficulty.name.toLowerCase()) {
    case 'facile':
      return 'border-success shadow-[0_0_15px_hsl(var(--su)/0.5)]'
    case 'moyen':
      return 'border-warning shadow-[0_0_15px_hsl(var(--wa)/0.5)]'
    case 'difficile':
      return 'border-error shadow-[0_0_15px_hsl(var(--er)/0.5)]'
    default:
      return 'border-base-300'
  }
})

function fetchNextQuestion() {
  nextQuestionPromise = fetch(
    `/quiz-sessions/${props.quizSessionId}/next-questions?limit=1`,
  ).then((response) => {
    if (!response.ok) {
      if (response.status === 404) {
        return null // End of quiz
      }
      // Throw an error to be caught later
      return response.json().then((errorData) => {
        throw new Error(
          errorData.error ||
            'Erreur lors du chargement de la question suivante',
        )
      })
    }
    return response.json()
  })
}

async function loadInitialQuestion() {
  loading.value = true
  error.value = null
  currentQuestion.value = null

  try {
    fetchNextQuestion() // Prefetch the first question
    const questions = await nextQuestionPromise

    if (!questions || questions.length === 0) {
      error.value =
        'Ce quiz ne contient aucune question. Impossible de démarrer.'
      if (gameTimer) clearInterval(gameTimer)
      return
    }

    currentQuestion.value = questions[0]
    questionNumber.value++
    showHint.value = false
    await createAnswer()
    fetchNextQuestion() // Prefetch the second question
  } catch (err) {
    error.value = err.message
  } finally {
    loading.value = false
  }
}

async function createAnswer() {
  if (!currentQuestion.value) return

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
      const errorData = await response.json()
      throw new Error(
        errorData.error || 'Erreur lors de la création de la réponse',
      )
    }

    const data = await response.json()
    quizSessionAnswerId.value = data.quizSessionAnswerId
  } catch (err) {
    error.value = err.message
  }
}

async function selectAnswer(proposalId) {
  if (isSubmitting.value) return

  isSubmitting.value = true
  selectedProposal.value = proposalId

  const submitPromise = fetch(
    `/quiz-sessions/${props.quizSessionId}/submit-answer`,
    {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        quizSessionAnswerId: quizSessionAnswerId.value,
        questionId: currentQuestion.value.id,
        proposalId: proposalId,
      }),
    },
  )

  try {
    const [submitResponse, nextQuestions] = await Promise.all([
      submitPromise,
      nextQuestionPromise,
    ])

    if (!submitResponse.ok) {
      const errorData = await submitResponse.json()
      throw new Error(errorData.error || 'Erreur lors de la soumission')
    }

    const result = await submitResponse.json()
    score.value = result.score
    answerStatus.value = result.isCorrect ? 'correct' : 'incorrect'
    if (result.goodAnswerId) {
      goodAnswerId.value = result.goodAnswerId
    }

    setTimeout(() => {
      if (timeLeft.value <= 0) {
        if (!error.value) finishQuiz()
        return
      }

      if (!nextQuestions || nextQuestions.length === 0) {
        finishQuiz()
        return
      }

      currentQuestion.value = nextQuestions[0]
      questionNumber.value++

      selectedProposal.value = null
      answerStatus.value = ''
      goodAnswerId.value = null
      isSubmitting.value = false
      showHint.value = false

      createAnswer()
      fetchNextQuestion()
    }, 400)
  } catch (err) {
    error.value = err.message
    isSubmitting.value = false
  }
}

function startGameTimer() {
  gameTimer = setInterval(() => {
    timeLeft.value--
    if (timeLeft.value <= 0) {
      finishQuiz()
    }
  }, 1000)
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

onMounted(() => {
  loadInitialQuestion()
  startGameTimer()
  window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  if (gameTimer) clearInterval(gameTimer)
  window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <div class="quiz-container font-sans">
    <div class="max-w-4xl mx-auto p-4 sm:p-8">
      <!-- Header avec score et timer -->
      <header
        v-if="currentQuestion || (!loading && !error)"
        class="flex justify-between items-center mb-12 text-base-content"
      >
        <!-- Score Section -->
        <div class="score-card">
          <div
            class="flex items-center gap-3 bg-base-200 rounded-2xl p-6 shadow-lg border border-base-300"
          >
            <div class="p-3 bg-primary/10 rounded-xl">
              <svg
                class="w-6 h-6 text-primary"
                fill="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"
                />
              </svg>
            </div>
            <div>
              <div class="text-sm opacity-60 font-medium">SCORE</div>
              <div class="text-3xl font-bold text-primary">{{ score }}</div>
            </div>
          </div>
        </div>

        <!-- Timer Section -->
        <div class="timer-container">
          <div class="relative w-32 h-32 flex items-center justify-center">
            <!-- Background circle -->
            <div
              class="absolute inset-0 rounded-full border-4 border-base-300"
            ></div>
            <!-- Progress circle -->
            <div
              class="absolute inset-0 rounded-full border-4 transition-all duration-1000"
              :class="
                timeLeft <= 10 ? 'border-error animate-pulse' : 'border-primary'
              "
              :style="{
                clipPath: `inset(0 ${100 - (timeLeft / 60) * 100}% 0 0)`,
              }"
            ></div>
            <!-- Timer content -->
            <div class="absolute flex flex-col items-center justify-center">
              <div class="flex items-center gap-1">
                <svg
                  class="w-5 h-5 opacity-60"
                  fill="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"
                  />
                </svg>
                <span
                  class="countdown text-2xl font-mono font-bold"
                  :class="timeLeft <= 10 ? 'text-error' : ''"
                >
                  <span :style="`--value:${timeLeft}`"></span>
                </span>
              </div>
              <span class="text-xs opacity-60 font-medium">secondes</span>
            </div>
          </div>
        </div>
      </header>

      <!-- Main Quiz Content -->
      <main class="relative min-h-[32rem]">
        <Transition name="slide-fade" mode="out-in">
          <!-- Loading State -->
          <div
            v-if="loading && !currentQuestion"
            key="loading"
            class="text-center p-12"
          >
            <div class="loading-container">
              <div
                class="p-8 bg-base-200 rounded-3xl shadow-xl border border-base-300"
              >
                <div class="flex flex-col items-center gap-4">
                  <div
                    class="w-16 h-16 bg-primary/10 rounded-2xl flex items-center justify-center"
                  >
                    <svg
                      class="w-8 h-8 text-primary animate-spin"
                      fill="none"
                      viewBox="0 0 24 24"
                    >
                      <circle
                        class="opacity-25"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        stroke-width="4"
                      ></circle>
                      <path
                        class="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                      ></path>
                    </svg>
                  </div>
                  <div class="text-xl font-semibold">Chargement du quiz...</div>
                  <div class="text-sm opacity-60">
                    Préparation de votre première question
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Question Display -->
          <div
            v-else-if="currentQuestion"
            :key="currentQuestion.id"
            class="question-card"
            :class="{
              'answer-correct': answerStatus === 'correct',
              'answer-incorrect': answerStatus === 'incorrect',
            }"
          >
            <div
              class="card bg-base-100 border-2 transition-all duration-500 rounded-3xl shadow-2xl overflow-hidden"
              :class="difficultyGlowClass"
            >
              <!-- Question Header -->
              <div
                class="card-header bg-gradient-to-r from-base-200 to-base-300 p-6 border-b border-base-300"
              >
                <div class="flex justify-between items-center">
                  <!-- Category -->
                  <div class="flex items-center gap-2">
                    <div class="p-2 bg-base-content/10 rounded-lg">
                      <svg
                        class="w-5 h-5"
                        fill="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"
                        />
                      </svg>
                    </div>
                    <div>
                      <div
                        class="badge badge-lg badge-ghost badge-outline font-medium"
                      >
                        {{ currentQuestion.category.name }}
                      </div>
                    </div>
                  </div>

                  <!-- Difficulty -->
                  <div class="flex items-center gap-2">
                    <svg
                      class="w-5 h-5 opacity-60"
                      fill="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"
                      />
                    </svg>
                    <div
                      class="badge badge-lg badge-soft badge-outline font-medium"
                      :class="difficultyBadgeClass"
                    >
                      {{ currentQuestion.difficulty.name }}
                    </div>
                  </div>
                </div>
              </div>

              <!-- Question Content -->
              <div class="card-body p-8">
                <div class="question-number mb-4">
                  <div
                    class="text-sm opacity-60 font-medium flex items-center gap-2"
                  >
                    <svg
                      class="w-4 h-4"
                      fill="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"
                      />
                    </svg>
                    Question {{ questionNumber }}
                  </div>
                </div>

                <!-- Image -->
                <div
                  v-if="currentQuestion.imageUrl"
                  class="mb-8 flex justify-center"
                >
                  <img
                    :src="currentQuestion.imageUrl"
                    alt="Image pour la question"
                    class="rounded-2xl shadow-lg object-contain border-2 border-base-300/30"
                    style="max-height: 35vh; max-width: 100%"
                  />
                </div>

                <h2
                  class="question-title text-2xl sm:text-3xl mb-6 font-bold text-base-content leading-relaxed"
                >
                  {{ currentQuestion.content }}
                </h2>

                <!-- Hint Section -->
                <div v-if="currentQuestion.hint" class="mb-8 text-center">
                  <button
                    v-if="!showHint"
                    class="btn btn-sm btn-outline btn-accent gap-2 transition-all hover:scale-105 hover:shadow-md"
                    @click="showHint = true"
                  >
                    <IconComponent icon-name="fa-lightbulb" />
                    Indice
                  </button>
                  <div
                    v-else
                    class="text-left p-4 rounded-2xl bg-info/10 border border-info/20 animate-fade-in"
                  >
                    <p class="flex items-center gap-2 font-bold text-info">
                      <IconComponent icon-name="fa-solid fa-lightbulb" />
                      <span>Indice</span>
                    </p>
                    <p class="mt-2 text-base-content/90">
                      {{ currentQuestion.hint }}
                    </p>
                  </div>
                </div>

                <!-- Proposals -->
                <div
                  class="proposals-grid grid grid-cols-1 md:grid-cols-2 gap-4"
                >
                  <button
                    v-for="(proposal, index) in currentQuestion.proposals"
                    :key="proposal.id"
                    :disabled="isSubmitting"
                    class="proposal-btn btn h-auto min-h-fit text-wrap p-6 justify-start transition-all duration-300 btn-outline border-2 rounded-xl font-medium text-left hover:scale-[1.02] hover:shadow-lg"
                    :class="{
                      'proposal-selected-correct':
                        (answerStatus === 'correct' &&
                          selectedProposal === proposal.id) ||
                        (answerStatus === 'incorrect' &&
                          goodAnswerId === proposal.id),
                      'proposal-selected-incorrect':
                        answerStatus === 'incorrect' &&
                        selectedProposal === proposal.id &&
                        goodAnswerId !== proposal.id,
                      'opacity-40 scale-95':
                        selectedProposal !== null &&
                        selectedProposal !== proposal.id &&
                        goodAnswerId !== proposal.id,
                      'hover:border-primary hover:bg-primary/5':
                        !isSubmitting && selectedProposal === null,
                    }"
                    @click="selectAnswer(proposal.id)"
                  >
                    <div class="flex items-center gap-4 w-full">
                      <!-- Option Letter -->
                      <div
                        class="flex-shrink-0 w-8 h-8 rounded-full border-2 flex items-center justify-center font-bold text-sm"
                        :class="
                          (selectedProposal === proposal.id &&
                            answerStatus === 'correct') ||
                          (answerStatus === 'incorrect' &&
                            goodAnswerId === proposal.id)
                            ? 'bg-success text-success-content border-success'
                            : selectedProposal === proposal.id &&
                                answerStatus === 'incorrect'
                              ? 'bg-error text-error-content border-error'
                              : 'border-current'
                        "
                      >
                        {{ String.fromCharCode(65 + index) }}
                      </div>
                      <!-- Proposal Text -->
                      <div class="flex-1 text-base sm:text-lg">
                        {{ proposal.content }}
                      </div>

                      <!-- Proposal Image -->
                      <div v-if="proposal.imageUrl" class="ml-auto">
                        <img
                          :src="proposal.imageUrl"
                          alt="Image de la proposition"
                          class="w-16 h-16 rounded-lg object-cover border-2 border-base-300/50 cursor-pointer transition-transform hover:scale-110"
                          @click.stop="openProposalModal(proposal.imageUrl)"
                        />
                      </div>

                      <!-- Status Icon -->
                      <div class="flex-shrink-0">
                        <!-- Show Checkmark for the correct answer (selected or revealed) -->
                        <svg
                          v-if="
                            (selectedProposal === proposal.id &&
                              answerStatus === 'correct') ||
                            (answerStatus === 'incorrect' &&
                              goodAnswerId === proposal.id)
                          "
                          class="w-6 h-6 text-success"
                          fill="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path
                            d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"
                          />
                        </svg>
                        <!-- Show Cross for the incorrectly selected answer -->
                        <svg
                          v-else-if="
                            selectedProposal === proposal.id &&
                            answerStatus === 'incorrect'
                          "
                          class="w-6 h-6 text-error"
                          fill="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path
                            d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 13.59L15.59 17 12 13.41 8.41 17 7 15.59 10.59 12 7 8.41 8.41 7 12 10.59 15.59 7 17 8.41 13.41 12 17 15.59z"
                          />
                        </svg>
                      </div>
                    </div>
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
              </div>
            </div>
          </div>

          <!-- Error State -->
          <div v-else-if="error" key="error" class="error-container">
            <div
              class="alert alert-error shadow-xl rounded-3xl border-2 border-error/20 p-8"
            >
              <div class="flex items-center gap-4">
                <svg
                  class="w-8 h-8 text-error"
                  fill="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 13.59L15.59 17 12 13.41 8.41 17 7 15.59 10.59 12 7 8.41 8.41 7 12 10.59 15.59 7 17 8.41 13.41 12 17 15.59z"
                  />
                </svg>
                <div>
                  <div class="font-bold text-lg">Erreur</div>
                  <div class="text-sm opacity-90">{{ error }}</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Game Over -->
          <div v-else key="gameover" class="text-center p-12">
            <div
              class="gameover-container bg-base-200 rounded-3xl shadow-xl border border-base-300 p-12"
            >
              <div class="flex flex-col items-center gap-6">
                <div
                  class="w-20 h-20 bg-warning/10 rounded-3xl flex items-center justify-center"
                >
                  <svg
                    class="w-12 h-12 text-warning"
                    fill="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"
                    />
                  </svg>
                </div>
                <div>
                  <h2 class="text-4xl font-bold mb-2">Temps écoulé !</h2>
                  <p class="text-lg opacity-70">
                    Redirection vers les résultats...
                  </p>
                </div>
                <div
                  class="loading loading-spinner loading-lg text-primary"
                ></div>
              </div>
            </div>
          </div>
        </Transition>
      </main>
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

<style>
.quiz-container {
  background: linear-gradient(135deg, hsl(var(--b1)) 0%, hsl(var(--b2)) 100%);
  background-attachment: fixed;
  min-height: 100vh;
  position: relative;
}

.quiz-container::before {
  content: '';
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-image:
    radial-gradient(circle at 25% 25%, hsl(var(--p) / 0.1) 0%, transparent 50%),
    radial-gradient(circle at 75% 75%, hsl(var(--s) / 0.1) 0%, transparent 50%);
  pointer-events: none;
  z-index: 0;
}

.quiz-container > * {
  position: relative;
  z-index: 1;
}

/* Animations pour les transitions */
.slide-fade-enter-active,
.slide-fade-leave-active {
  transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
}

.slide-fade-enter-from {
  opacity: 0;
  transform: translateY(30px) scale(0.95);
}

.slide-fade-leave-to {
  opacity: 0;
  transform: translateY(-30px) scale(0.95);
}

/* Animation pour les bonnes réponses */
@keyframes correctPulse {
  0%,
  100% {
    transform: scale(1);
    box-shadow: 0 0 20px hsl(var(--su) / 0.3);
  }
  50% {
    transform: scale(1.02);
    box-shadow: 0 0 30px hsl(var(--su) / 0.6);
  }
}

@keyframes incorrectShake {
  0%,
  100% {
    transform: translateX(0) scale(1);
    box-shadow: 0 0 20px hsl(var(--er) / 0.3);
  }
  25% {
    transform: translateX(-5px) scale(1.01);
    box-shadow: 0 0 25px hsl(var(--er) / 0.5);
  }
  75% {
    transform: translateX(5px) scale(1.01);
    box-shadow: 0 0 25px hsl(var(--er) / 0.5);
  }
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.animate-fade-in {
  animation: fadeIn 0.4s ease-out;
}

/* Classes pour les réponses correctes/incorrectes */
.answer-correct {
  animation: correctPulse 0.6s ease-in-out 0.1s;
}

.answer-incorrect {
  animation: incorrectShake 0.6s ease-in-out 0.1s;
}

.proposal-selected-correct {
  @apply bg-success text-success-content border-success;
  animation: correctPulse 0.4s ease-in-out;
  box-shadow: 0 0 25px hsl(var(--su) / 0.4) !important;
}

.proposal-selected-incorrect {
  @apply bg-error text-error-content border-error;
  animation: incorrectShake 0.4s ease-in-out;
  box-shadow: 0 0 25px hsl(var(--er) / 0.4) !important;
}

/* Amélioration des boutons de proposition */
.proposal-btn {
  background: linear-gradient(145deg, hsl(var(--b1)), hsl(var(--b2)));
  border: 2px solid hsl(var(--bc) / 0.2);
  box-shadow: 0 4px 15px hsl(var(--bc) / 0.1);
}

.proposal-btn:hover:not(:disabled) {
  background: linear-gradient(145deg, hsl(var(--b2)), hsl(var(--b3)));
  border-color: hsl(var(--p) / 0.5);
}

/* Timer amélioré */
.timer-container {
  position: relative;
}

.timer-container::before {
  content: '';
  position: absolute;
  inset: -10px;
  border-radius: 50%;
  background: conic-gradient(
    from 0deg,
    hsl(var(--p) / 0.1),
    hsl(var(--p) / 0.2),
    hsl(var(--p) / 0.1)
  );
  animation: rotate 10s linear infinite;
  z-index: -1;
}

@keyframes rotate {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

/* Score card amélioré */
.score-card {
  position: relative;
}

.score-card::before {
  content: '';
  position: absolute;
  inset: -2px;
  border-radius: 18px;
  background: linear-gradient(135deg, hsl(var(--p) / 0.3), hsl(var(--s) / 0.3));
  z-index: -1;
}

/* Loading amélioré */
.loading-container {
  position: relative;
}

.loading-container::before {
  content: '';
  position: absolute;
  inset: -20px;
  border-radius: 30px;
  background: conic-gradient(
    from 0deg,
    transparent,
    hsl(var(--p) / 0.1),
    transparent
  );
  animation: rotate 3s linear infinite;
  z-index: -1;
}
</style>
