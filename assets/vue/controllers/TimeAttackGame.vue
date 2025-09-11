<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'

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
const score = ref(0)
const error = ref(null)
const timeLeft = ref(60)

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
      isSubmitting.value = false

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

function finishQuiz() {
  if (gameTimer) clearInterval(gameTimer)
  if (!error.value) {
    window.location.href = `/quiz/${props.quizSessionId}/finish`
  }
}

onMounted(() => {
  loadInitialQuestion()
  startGameTimer()
})

onBeforeUnmount(() => {
  if (gameTimer) clearInterval(gameTimer)
})
</script>

<template>
  <div class="quiz-container font-sans">
    <div class="max-w-3xl mx-auto p-4 sm:p-8">
      <header
        v-if="currentQuestion || (!loading && !error)"
        class="flex justify-between items-center mb-8 text-base-content"
      >
        <div class="text-center">
          <div class="text-sm opacity-60">SCORE</div>
          <div class="text-4xl font-bold">{{ score }}</div>
        </div>
        <div
          class="relative w-28 h-28 sm:w-32 sm:h-32 flex items-center justify-center"
        >
          <div
            class="absolute inset-0 rounded-full border-4 border-base-300"
          ></div>
          <div
            class="absolute inset-0 rounded-full border-4 border-primary transition-all duration-1000"
            :style="{
              clipPath: `inset(0 ${100 - (timeLeft / 60) * 100}% 0 0)`,
            }"
          ></div>
          <div
            class="absolute flex flex-col items-center justify-center font-mono"
          >
            <span class="countdown text-3xl sm:text-4xl">
              <span :style="`--value:${timeLeft}`"></span>
            </span>
            <span class="text-xs opacity-60 -mt-2">sec</span>
          </div>
        </div>
      </header>

      <main class="relative min-h-[28rem]">
        <Transition name="fade" mode="out-in">
          <div
            v-if="loading && !currentQuestion"
            key="loading"
            class="text-center p-10"
          >
            <span class="loading loading-dots loading-lg text-primary"></span>
          </div>

          <div
            v-else-if="currentQuestion"
            :key="currentQuestion.id"
            class="card bg-base-200 border-2 transition-all duration-500"
            :class="difficultyGlowClass"
          >
            <div class="card-body p-5 sm:p-8">
              <div class="flex justify-between items-center mb-4">
                <div class="badge badge-ghost">
                  {{ currentQuestion.category.name }}
                </div>
                <div class="badge" :class="difficultyBadgeClass">
                  {{ currentQuestion.difficulty.name }}
                </div>
              </div>

              <h2 class="card-title text-2xl sm:text-3xl my-6 font-bold">
                {{ currentQuestion.content }}
              </h2>

              <!-- Proposals -->
              <div class="grid grid-cols-1 gap-3">
                <button
                  v-for="proposal in currentQuestion.proposals"
                  :key="proposal.id"
                  :disabled="isSubmitting"
                  class="btn btn-xl h-auto min-h-fit text-wrap p-4 justify-start transition-all duration-200 btn-outline"
                  :class="{
                    '!btn-success !text-success-content animate-pulse':
                      answerStatus === 'correct' &&
                      selectedProposal === proposal.id,
                    '!btn-error !text-error-content animate-pulse':
                      answerStatus === 'incorrect' &&
                      selectedProposal === proposal.id,
                    'opacity-50':
                      selectedProposal !== null &&
                      selectedProposal !== proposal.id,
                  }"
                  @click="selectAnswer(proposal.id)"
                >
                  {{ proposal.content }}
                </button>
              </div>
            </div>
          </div>

          <div
            v-else-if="error"
            key="error"
            class="alert alert-error shadow-lg"
          >
            <span>{{ error }}</span>
          </div>

          <div v-else key="gameover" class="text-center p-10">
            <h2 class="text-4xl font-bold mb-4">Temps écoulé !</h2>
            <p>Vous allez être redirigé vers les résultats...</p>
            <span
              class="loading loading-spinner loading-lg mt-4 text-primary"
            ></span>
          </div>
        </Transition>
      </main>
    </div>
  </div>
</template>

<style>
.quiz-container {
  background-color: hsl(var(--b1));
  background-image: radial-gradient(hsl(var(--b2) / 0.75) 1px, transparent 1px);
  background-size: 2rem 2rem;
  min-height: 100vh;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.4s ease-in-out;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
