<script setup>
import { ref, computed, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
  questions: {
    type: Array,
    required: true,
  },
  quizSessionId: {
    type: Number,
    required: true,
  },
})

const currentQuestionIndex = ref(0)
const isAnswered = ref(false)
const score = ref(0)
const feedback = ref(null) // { isCorrect: bool, correctProposalId: int, selectedId: int }
const questionStartTime = ref(null)

const currentQuestion = computed(() => {
  if (props.questions && props.questions.length > currentQuestionIndex.value) {
    return props.questions[currentQuestionIndex.value]
  }
  return null
})

watch(
  currentQuestion,
  (newQuestion) => {
    if (newQuestion) {
      questionStartTime.value = Date.now()
    }
  },
  { immediate: true },
) // immediate: true to run on component mount for the first question

const handleAnswer = async (proposalId) => {
  if (isAnswered.value) return
  isAnswered.value = true

  try {
    const response = await axios.post(
      `/api/quiz_sessions/${props.quizSessionId}/answer`,
      {
        questionId: currentQuestion.value.id,
        proposalId: proposalId,
        askedAtTimestamp: questionStartTime.value,
      },
      { withCredentials: false },
    )

    feedback.value = {
      isCorrect: response.data.isCorrect,
      correctProposalId: response.data.correctProposalId,
      selectedId: proposalId,
    }
    score.value = response.data.score

    setTimeout(nextQuestion, 2000) // Wait 2s to show feedback
  } catch (error) {
    console.error('Error submitting answer:', error)
    // Optionally, show an error to the user
    // For now, let's proceed to the next question even if API fails, to not block the user
    setTimeout(nextQuestion, 2000)
  }
}

const getButtonClass = (proposalId) => {
  if (!isAnswered.value || !feedback.value) {
    return 'btn-outline'
  }

  const isSelected = proposalId === feedback.value.selectedId
  const isCorrect = proposalId === feedback.value.correctProposalId

  if (isCorrect) {
    return 'btn-success' // Always show correct answer in green
  }
  if (isSelected && !isCorrect) {
    return 'btn-error' // Show selected wrong answer in red
  }

  return 'btn-disabled' // Other non-selected, wrong answers
}

const nextQuestion = () => {
  if (currentQuestionIndex.value < props.questions.length - 1) {
    currentQuestionIndex.value++
    isAnswered.value = false
    feedback.value = null
  } else {
    finishQuiz()
  }
}

const finishQuiz = () => {
  window.location.href = `/quiz/${props.quizSessionId}/finish`
}

const abortQuiz = async () => {
  if (
    confirm(
      'Êtes-vous sûr de vouloir abandonner ? Votre progression ne sera pas sauvegardée.',
    )
  ) {
    try {
      await axios.post(`/quiz/${props.quizSessionId}/abort`)
      window.location.href = '/' // Redirect to homepage
    } catch (error) {
      console.error('Error aborting quiz:', error)
      // Optionally, show an error to the user
      alert(
        "Une erreur est survenue lors de l'abandon du quiz. Veuillez réessayer.",
      )
    }
  }
}
</script>

<template>
  <div
    class="card w-full max-w-2xl bg-base-100 shadow-xl transition-all duration-500"
  >
    <div class="card-body">
      <div>{{ questions.length }}</div>
      <div v-if="currentQuestion">
        <!-- Header: Score and Progress -->
        <div class="flex justify-between items-center mb-4">
          <div class="text-lg font-bold">
            Score: <span class="text-primary">{{ score }}</span>
          </div>
          <div class="text-sm text-base-content/70">
            Question {{ currentQuestionIndex + 1 }} / {{ questions.length }}
          </div>
        </div>
        <progress
          class="progress progress-primary w-full mb-4"
          :value="currentQuestionIndex + 1"
          :max="questions.length"
        ></progress>

        <!-- Question Image -->
        <figure
          v-if="currentQuestion.imageUrl"
          class="mb-4 bg-base-200 rounded-lg p-4"
        >
          <img
            :src="currentQuestion.imageUrl"
            alt="Question Image"
            class="rounded-lg max-h-64 mx-auto"
          />
        </figure>

        <!-- Question Content -->
        <h2 class="card-title text-2xl mb-6 text-center min-h-16">
          {{ currentQuestion.content }}
        </h2>

        <!-- Proposals -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <button
            v-for="proposal in currentQuestion.proposals"
            :key="proposal.id"
            class="btn btn-lg h-auto py-3 whitespace-normal"
            :class="getButtonClass(proposal.id)"
            :disabled="isAnswered"
            @click="handleAnswer(proposal.id)"
          >
            {{ proposal.content }}
          </button>
        </div>

        <!-- Abandon button -->
        <div class="text-center mt-6">
          <button class="btn btn-ghost" @click="abortQuiz">Abandonner</button>
        </div>
      </div>
      <div v-else class="text-center">
        <span class="loading loading-spinner loading-lg"></span>
        <p>Chargement du quiz...</p>
      </div>
    </div>
  </div>
</template>
