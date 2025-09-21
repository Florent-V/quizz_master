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
const lastAnswerScore = ref(0)
const showScoreAnimation = ref(false)
const error = ref(null)
const timeLeft = ref(600)
const showHint = ref(false)
const isProposalModalOpen = ref(false)
const proposalModalImageUrl = ref('')

const { finishQuiz, abortQuiz } = useQuizSession(props.quizSessionId)

let gameTimer = null
let nextQuestionPromise = null

// Computed properties for dynamic styling
const difficultyBadgeClass = computed(() => {
  if (!currentQuestion.value?.difficulty?.id)
    return 'border-gray-500 text-gray-400'
  switch (currentQuestion.value.difficulty.id) {
    case 1:
      return 'border-green-500/50 bg-green-500/10 text-green-400' // Facile
    case 2:
      return 'border-blue-500/50 bg-blue-500/10 text-blue-400' // Normal
    case 3:
      return 'border-yellow-500/50 bg-yellow-500/10 text-yellow-400' // Moyen
    case 4:
      return 'border-orange-500/50 bg-orange-500/10 text-orange-400' // Difficile
    case 5:
      return 'border-red-500/50 bg-red-500/10 text-red-400' // Expert
    default:
      return 'border-gray-500/50 bg-gray-500/10 text-gray-400'
  }
})

function fetchNextQuestion() {
  nextQuestionPromise = fetch(
    `/api/quiz-session/${props.quizSessionId}/next-questions?limit=1`,
  ).then((response) => {
    if (!response.ok) {
      if (response.status === 404) {
        return null // End of quiz
      }
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
      `/api/quiz-session/${props.quizSessionId}/create-answer`,
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
    `/api/quiz-session/${props.quizSessionId}/submit-answer`,
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
    score.value = result.totalScore
    lastAnswerScore.value = result.answerScore
    if (result.isCorrect && result.answerScore > 0) {
      showScoreAnimation.value = true
      setTimeout(() => {
        showScoreAnimation.value = false
      }, 2000)
    }
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
    }, 1200) // Increased delay to allow for animations
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
  <div class="quiz-container overflow-y-scroll">
    <!-- Floating Score Animation -->
    <Transition name="score-pop">
      <div
        v-if="showScoreAnimation"
        class="floating-score font-bold bg-gradient-to-r from-primary to-secondary bg-clip-text text-transparent"
      >
        +{{ lastAnswerScore }}
      </div>
    </Transition>

    <!-- Main content -->
    <div class="relative z-10 max-w-4xl mx-auto p-4 sm:p-6">
      <!-- Header -->
      <header
        v-if="currentQuestion || (!loading && !error)"
        class="sticky top-4 z-20 w-full mb-8"
      >
        <div
          class="navbar bg-base-100/50 backdrop-blur-lg rounded-2xl shadow-lg border border-base-content/10"
        >
          <div class="navbar-start">
            <div class="flex items-center gap-2">
              <IconComponent
                icon-name="fa-solid fa-stopwatch"
                class="text-primary"
              />
              <span class="countdown font-mono text-2xl">
                <span :style="`--value:${timeLeft}`"></span>s
              </span>
            </div>
          </div>
          <div class="navbar-center">
            <h1
              class="text-3xl font-bold bg-gradient-to-r from-primary to-secondary bg-clip-text text-transparent"
            >
              Time Attack
            </h1>
          </div>
          <div class="navbar-end">
            <div class="flex items-center gap-2">
              <span class="font-bold text-2xl text-primary">{{ score }}</span>
              <IconComponent
                icon-name="fa-solid fa-star"
                class="text-yellow-400"
              />
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
            class="absolute inset-0 flex items-center justify-center"
          >
            <div
              class="text-center p-8 bg-base-100/50 backdrop-blur-md rounded-2xl shadow-2xl"
            >
              <span class="loading loading-ball loading-lg text-primary"></span>
              <h2 class="mt-4 text-xl font-bold">Chargement du quiz...</h2>
              <p class="text-base-content/70">Préparez-vous !</p>
            </div>
          </div>

          <!-- Question Display -->
          <div
            v-else-if="currentQuestion"
            :key="currentQuestion.id"
            class="space-y-6"
          >
            <!-- Question Info -->
            <div class="flex justify-between items-center">
              <div class="badge badge-lg" :class="difficultyBadgeClass">
                {{ currentQuestion.difficulty.name }}
              </div>
              <div class="text-sm font-medium text-base-content/60">
                Question {{ questionNumber }}
              </div>
              <div class="badge badge-lg badge-outline border-base-content/20">
                {{ currentQuestion.category.name }}
              </div>
            </div>

            <!-- Question Card -->
            <div
              class="card bg-base-100/60 backdrop-blur-xl shadow-2xl border border-base-content/10"
            >
              <div class="card-body items-center text-center p-8">
                <img
                  v-if="currentQuestion.imageUrl"
                  :src="currentQuestion.imageUrl"
                  alt="Image de la question"
                  class="max-h-60 rounded-lg shadow-lg mb-6"
                />
                <h2 class="card-title text-3xl leading-relaxed">
                  {{ currentQuestion.content }}
                </h2>
                <div v-if="currentQuestion.hint" class="mt-4">
                  <div class="tooltip" data-tip="Cliquez pour révéler l'indice">
                    <button
                      class="btn btn-circle btn-ghost btn-sm"
                      @click="showHint = !showHint"
                    >
                      <IconComponent icon-name="fa-solid fa-lightbulb" />
                    </button>
                  </div>
                  <p
                    v-if="showHint"
                    class="mt-2 p-2 bg-info/10 text-info rounded-lg animate-fade-in"
                  >
                    {{ currentQuestion.hint }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Proposals Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <button
                v-for="(proposal, index) in currentQuestion.proposals"
                :key="proposal.id"
                :disabled="isSubmitting"
                class="btn h-auto min-h-[4rem] text-wrap p-4 justify-start transition-all duration-300 rounded-xl font-semibold text-left"
                :class="{
                  glass: !isSubmitting,
                  'btn-success !border-success ring-2 ring-success':
                    (answerStatus === 'correct' &&
                      selectedProposal === proposal.id) ||
                    (answerStatus === 'incorrect' &&
                      goodAnswerId === proposal.id),
                  'btn-error !border-error ring-2 ring-error':
                    answerStatus === 'incorrect' &&
                    selectedProposal === proposal.id &&
                    goodAnswerId !== proposal.id,
                  'opacity-50':
                    isSubmitting &&
                    selectedProposal !== proposal.id &&
                    goodAnswerId !== proposal.id,
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
                  <div class="flex-1 text-base">
                    {{ proposal.content }}
                  </div>

                  <!-- Proposal Image -->
                  <img
                    v-if="proposal.imageUrl"
                    :src="proposal.imageUrl"
                    alt=""
                    class="w-12 h-12 rounded-lg object-cover cursor-pointer"
                    @click.stop="openProposalModal(proposal.imageUrl)"
                  />

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

          <!-- Error State -->
          <div
            v-else-if="error"
            key="error"
            class="absolute inset-0 flex items-center justify-center"
          >
            <div role="alert" class="alert alert-error">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                class="stroke-current shrink-0 h-6 w-6"
                fill="none"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"
                />
              </svg>
              <span>{{ error }}</span>
            </div>
          </div>

          <!-- Game Over -->
          <div
            v-else
            key="gameover"
            class="absolute inset-0 flex items-center justify-center"
          >
            <div
              class="text-center p-8 bg-base-100/50 backdrop-blur-md rounded-2xl shadow-2xl"
            >
              <h2 class="text-4xl font-bold mb-2">Temps écoulé !</h2>
              <p class="text-lg opacity-70">
                Redirection vers les résultats...
              </p>
              <span
                class="loading loading-dots loading-lg text-primary mt-4"
              ></span>
            </div>
          </div>
        </Transition>
      </main>
    </div>

    <!-- Proposal Image Modal -->
    <div
      v-if="isProposalModalOpen"
      class="modal modal-open"
      @click="closeProposalModal"
    >
      <div class="modal-box" @click.stop>
        <img
          :src="proposalModalImageUrl"
          alt="Image de la proposition"
          class="w-full h-auto rounded-lg"
        />
        <div class="modal-action">
          <button class="btn" @click="closeProposalModal">Fermer</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.floating-score {
  position: fixed;
  top: 20%;
  left: 50%;
  transform: translateX(-50%);
  font-size: 4rem; /* 64px */
  font-weight: 800; /* extrabold */
  pointer-events: none;
  z-index: 50;
  white-space: nowrap;
}

.score-pop-enter-active {
  transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.score-pop-leave-active {
  transition: all 0.3s cubic-bezier(0.755, 0.05, 0.855, 0.06);
}
.score-pop-enter-from {
  opacity: 0;
  transform: translateX(-50%) translateY(50px) scale(0.5);
}
.score-pop-leave-to {
  opacity: 0;
  transform: translateX(-50%) translateY(-100px) scale(1.5);
}

.slide-fade-enter-active,
.slide-fade-leave-active {
  transition: all 0.5s cubic-bezier(0.55, 0, 0.1, 1);
}
.slide-fade-enter-from {
  opacity: 0;
  transform: scale(0.95);
}
.slide-fade-leave-to {
  opacity: 0;
  transform: scale(1.05);
}

.animate-fade-in {
  animation: fadeIn 0.5s ease-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

@keyframes pulse-slow {
  50% {
    opacity: 0.6;
  }
}
.animate-pulse-slow {
  animation: pulse-slow 4s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
.animation-delay-2000 {
  animation-delay: 1s;
}
</style>
