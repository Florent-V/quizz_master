<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
  quizSessionId: {
    type: Number,
    required: true,
  },
})

const loading = ref(false)
const currentQuestion = ref(null)
const questionNumber = ref(1)
const totalQuestions = ref(0)
const selectedProposal = ref(null)
const showResult = ref(false)
const isCorrect = ref(false)
const correctProposalId = ref(null)
const explanation = ref(null)
const isLastQuestion = ref(false)
const score = ref(0)
const error = ref(null)
const showHint = ref(false)
const timeLeft = ref(30)
let timer = null
let startTime = null

async function loadQuestion() {
  loading.value = true
  error.value = null
  showHint.value = false
  showResult.value = false
  selectedProposal.value = null
  timeLeft.value = 30
  startTime = Date.now()

  try {
    const response = await fetch(
      `/api/quiz/${props.quizSessionId}/question/${questionNumber.value}`,
    )

    if (!response.ok) {
      const errorData = await response.json()
      throw new Error(
        errorData.error || 'Erreur lors du chargement de la question',
      )
    }

    const data = await response.json()
    currentQuestion.value = data
    totalQuestions.value = data.totalQuestions

    if (data.hasAnswered) {
      questionNumber.value++
      if (questionNumber.value <= totalQuestions.value) {
        await loadQuestion()
        return
      }
      finishQuiz()
      return
    }

    startTimer()
  } catch (err) {
    error.value = err.message
  } finally {
    loading.value = false
  }
}

function startTimer() {
  timer = setInterval(() => {
    timeLeft.value--
    if (timeLeft.value <= 0) {
      clearInterval(timer)
      selectAnswer(null)
    }
  }, 1000)
}

async function selectAnswer(proposalId) {
  if (selectedProposal.value !== null) return

  selectedProposal.value = proposalId

  if (timer) {
    clearInterval(timer)
  }

  const timeSpent = Math.floor((Date.now() - startTime) / 1000)

  try {
    const response = await fetch(`/api/quiz/${props.quizSessionId}/answer`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        questionNumber: questionNumber.value,
        proposalId: proposalId,
        timeSpent: timeSpent,
      }),
    })

    if (!response.ok) {
      const errorData = await response.json()
      throw new Error(errorData.error || 'Erreur lors de la soumission')
    }

    const result = await response.json()
    isCorrect.value = result.isCorrect
    correctProposalId.value = result.correctProposalId
    explanation.value = result.explanation
    isLastQuestion.value = result.isLastQuestion
    score.value = result.currentScore
    showResult.value = true
  } catch (err) {
    error.value = err.message
  }
}

function nextQuestion() {
  questionNumber.value++
  loadQuestion()
}

function finishQuiz() {
  window.location.href = `/quiz/results/${props.quizSessionId}`
}

onMounted(loadQuestion)

onBeforeUnmount(() => {
  if (timer) clearInterval(timer)
})
</script>

<template>
  <div class="max-w-4xl mx-auto">
    <!-- Loading -->
    <div v-if="loading" class="text-center">
      <span class="loading loading-spinner loading-lg"></span>
      <p class="mt-2">Chargement...</p>
    </div>

    <!-- Quiz Progress -->
    <div v-else-if="currentQuestion" class="space-y-6">
      <!-- Progress bar -->
      <div class="w-full bg-gray-200 rounded-full h-2">
        <div
          class="bg-primary h-2 rounded-full transition-all duration-300"
          :style="`width: ${(questionNumber / totalQuestions) * 100}%`"
        ></div>
      </div>

      <!-- Question counter -->
      <div class="text-center">
        <span class="badge badge-primary badge-lg">
          Question {{ questionNumber }} / {{ totalQuestions }}
        </span>
        <div class="text-sm text-gray-500 mt-1">
          Score: {{ score }} / {{ questionNumber - 1 }}
        </div>
      </div>

      <!-- Timer -->
      <div v-if="!showResult" class="text-center">
        <div class="countdown text-2xl font-bold">
          <span :style="`--value:${Math.floor(timeLeft / 60)}`"></span>:
          <span :style="`--value:${timeLeft % 60}`"></span>
        </div>
      </div>

      <!-- Question -->
      <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
          <div v-if="currentQuestion.image" class="mb-4">
            <img
              :src="currentQuestion.image"
              alt="Question image"
              class="max-w-full h-auto rounded-lg mx-auto"
            />
          </div>

          <h2 class="card-title text-xl mb-4">{{ currentQuestion.content }}</h2>

          <div
            v-if="currentQuestion.hint && showHint"
            class="alert alert-info mb-4"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              class="stroke-current shrink-0 w-6 h-6"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0
                   11-18 0 9 9 0 0118 0z"
              ></path>
            </svg>
            <span>{{ currentQuestion.hint }}</span>
          </div>

          <!-- Proposals -->
          <div v-if="!showResult" class="space-y-3">
            <button
              v-for="proposal in currentQuestion.proposals"
              :key="proposal.id"
              :disabled="selectedProposal !== null"
              class="btn btn-outline w-full justify-start text-left p-4 h-auto min-h-fit"
              :class="{
                'btn-primary': selectedProposal === proposal.id,
                'btn-disabled':
                  selectedProposal !== null && selectedProposal !== proposal.id,
              }"
              @click="selectAnswer(proposal.id)"
            >
              <div class="flex items-center space-x-3 w-full">
                <div v-if="proposal.image" class="flex-shrink-0">
                  <img
                    :src="proposal.image"
                    alt="Proposal image"
                    class="w-16 h-16 object-cover rounded"
                  />
                </div>
                <span class="flex-1">{{ proposal.content }}</span>
              </div>
            </button>
          </div>

          <!-- Result -->
          <div v-if="showResult" class="space-y-4">
            <div class="space-y-3">
              <button
                v-for="proposal in currentQuestion.proposals"
                :key="proposal.id"
                class="btn w-full justify-start text-left p-4 h-auto min-h-fit"
                :class="{
                  'btn-success': proposal.id === correctProposalId,
                  'btn-error':
                    selectedProposal === proposal.id &&
                    proposal.id !== correctProposalId,
                  'btn-outline':
                    proposal.id !== correctProposalId &&
                    selectedProposal !== proposal.id,
                }"
                disabled
              >
                <div class="flex items-center space-x-3 w-full">
                  <div v-if="proposal.image" class="flex-shrink-0">
                    <img
                      :src="proposal.image"
                      alt="Proposal image"
                      class="w-16 h-16 object-cover rounded"
                    />
                  </div>
                  <span class="flex-1">{{ proposal.content }}</span>
                  <div
                    v-if="proposal.id === correctProposalId"
                    class="flex-shrink-0"
                  >
                    ✓
                  </div>
                  <div
                    v-if="
                      selectedProposal === proposal.id &&
                      proposal.id !== correctProposalId
                    "
                    class="flex-shrink-0"
                  >
                    ✗
                  </div>
                </div>
              </button>
            </div>

            <!-- Explanation -->
            <div v-if="explanation" class="alert alert-info">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                class="stroke-current shrink-0 w-6 h-6"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M13 16h-1v-4h-1m1-4h.01M21
                     12a9 9 0 11-18 0 9 9 0 0118
                     0z"
                ></path>
              </svg>
              <span>{{ explanation }}</span>
            </div>

            <!-- Result message -->
            <div
              class="alert"
              :class="isCorrect ? 'alert-success' : 'alert-error'"
            >
              <span v-if="isCorrect">✅ Bonne réponse !</span>
              <span v-else>❌ Mauvaise réponse</span>
            </div>

            <!-- Next button -->
            <div class="text-center">
              <button
                v-if="!isLastQuestion"
                class="btn btn-primary"
                @click="nextQuestion"
              >
                Question suivante
              </button>
              <button v-else class="btn btn-success" @click="finishQuiz">
                Voir les résultats
              </button>
            </div>
          </div>

          <!-- Hint button -->
          <div
            v-if="currentQuestion.hint && !showHint && !showResult"
            class="text-center mt-4"
          >
            <button class="btn btn-sm btn-ghost" @click="showHint = true">
              💡 Afficher l'indice
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="alert alert-error">
      <span>{{ error }}</span>
    </div>
  </div>
</template>
