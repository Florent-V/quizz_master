<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

const props = defineProps({
  quizSessionId: { type: Number, required: true },
})

// --- STATE MANAGEMENT ---
const loading = ref(true)
const submitting = ref(false)
const current = ref(null)
const hasAnswered = ref(false)
const isCorrect = ref(false)
const chosenId = ref(null)
const quizSessionAnswerId = ref(null)
const isFinished = ref(false)
const errorMessage = ref(null)

// --- GLOBAL TIMER LOGIC ---
const TIME_LIMIT = 60 // Total time for the quiz in seconds
const timeLeft = ref(TIME_LIMIT)
const timerInterval = ref(null)

const timerPercentage = computed(() => (timeLeft.value / TIME_LIMIT) * 100)

const timerColorClass = computed(() => {
  if (timeLeft.value > TIME_LIMIT * 0.5) return 'text-success'
  if (timeLeft.value > TIME_LIMIT * 0.2) return 'text-warning'
  return 'text-error'
})

const isFlashing = computed(() => timeLeft.value <= 5 && timeLeft.value > 0)

function startTimer() {
  stopTimer() // Ensure no multiple intervals are running
  timerInterval.value = setInterval(() => {
    if (timeLeft.value > 0) {
      timeLeft.value--
    } else {
      stopTimer()
      redirectToFinish()
    }
  }, 1000)
}

function stopTimer() {
  clearInterval(timerInterval.value)
  timerInterval.value = null
}

function redirectToFinish() {
  window.location.href = `/quiz/${props.quizSessionId}/finish`
}

// --- DATA & UI LOGIC ---
const proposalsHaveImages = computed(() => {
  return current.value && current.value.proposals.some((p) => p.imageName)
})

function getImageUrl(imageName) {
  // IMPORTANT: Adjust this path based on your actual file structure for uploads
  return imageName ? `/uploads/images/quiz/${imageName}` : ''
}

async function loadNext() {
  loading.value = true
  hasAnswered.value = false
  isCorrect.value = false
  chosenId.value = null
  quizSessionAnswerId.value = null
  errorMessage.value = null

  try {
    const { data } = await axios.post(
      `/quiz-sessions/${props.quizSessionId}/next-one-question`,
    )
    current.value = data.question
    quizSessionAnswerId.value = data.quizSessionAnswerId
    loading.value = false
  } catch (error) {
    errorMessage.value = `Impossible de charger la question suivante. Le quiz est considéré comme terminé. (${error.message})`
    isFinished.value = true
    loading.value = false
    stopTimer()
  }
}

async function onChoose(proposal) {
  if (hasAnswered.value || submitting.value) return

  submitting.value = true
  chosenId.value = proposal.id
  errorMessage.value = null

  try {
    const { data } = await axios.post(
      `/quiz-sessions/${props.quizSessionId}/submit-answer`,
      {
        quizSessionAnswerId: quizSessionAnswerId.value,
        questionId: current.value.id,
        proposalId: proposal.id,
      },
    )
    isCorrect.value = !!data.isCorrect
    hasAnswered.value = true
  } catch (error) {
    errorMessage.value = `Une erreur est survenue lors de la soumission de votre réponse. (${error.message})`
  } finally {
    submitting.value = false
  }
}

// --- LIFECYCLE HOOKS ---
onMounted(() => {
  loadNext()
  startTimer()
})

onUnmounted(() => {
  stopTimer()
})

// --- DYNAMIC CLASSES ---
function getProposalClasses(p) {
  if (!hasAnswered.value) {
    return ['cursor-pointer', 'hover:border-primary', 'hover:scale-[1.02]']
  }

  if (isCorrect.value && chosenId.value === p.id) {
    return ['border-success', 'scale-[1.02]', 'shadow-lg', 'shadow-success/20']
  }
  if (!isCorrect.value && chosenId.value === p.id) {
    return ['border-error', 'scale-[1.02]', 'shadow-lg', 'shadow-error/20']
  }

  return ['opacity-50', 'cursor-not-allowed']
}
</script>

<template>
  <div
    class="min-h-screen bg-base-200 flex items-center justify-center p-4 font-sans"
  >
    <!-- INITIAL LOADING STATE -->
    <div
      v-if="!current && !isFinished"
      class="card bg-base-100 w-full max-w-4xl shadow-xl rounded-2xl"
    >
      <div
        class="card-body flex flex-col items-center justify-center p-10 gap-4 text-lg"
      >
        <span class="loading loading-spinner loading-lg text-primary"></span>
        <p>Préparation du quiz...</p>
      </div>
    </div>

    <!-- FINISHED STATE -->
    <div
      v-else-if="isFinished"
      class="card bg-base-100 w-full max-w-2xl shadow-xl animate-fade-in"
    >
      <div class="card-body items-center text-center">
        <div v-if="errorMessage" role="alert" class="alert alert-warning mb-4">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-6 w-6 shrink-0 stroke-current"
            fill="none"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
            />
          </svg>
          <span>{{ errorMessage }}</span>
        </div>
        <h2 class="card-title text-2xl">Quiz terminé !</h2>
        <p>Vous avez répondu à toutes les questions.</p>
        <div class="card-actions justify-end mt-4">
          <button class="btn btn-primary" @click="redirectToFinish">
            Voir les résultats
          </button>
        </div>
      </div>
    </div>

    <!-- GAME INTERFACE -->
    <div
      v-else-if="current"
      class="card bg-base-100 w-full max-w-4xl shadow-xl rounded-2xl animate-fade-in-slow relative"
    >
      <!-- LOADER OVERLAY -->
      <div
        v-if="loading"
        class="absolute inset-0 bg-base-100/80 backdrop-blur-sm rounded-2xl flex flex-col items-center justify-center z-10 animate-fade-in"
      >
        <span class="loading loading-spinner loading-lg text-primary"></span>
        <p class="mt-4 text-lg font-bold">Chargement...</p>
      </div>

      <div class="card-body p-4 md:p-6">
        <!-- ERROR ALERT -->
        <div
          v-if="errorMessage"
          role="alert"
          class="alert alert-error mb-4 animate-fade-in"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-6 w-6 shrink-0 stroke-current"
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
          <span>{{ errorMessage }}</span>
          <button class="btn btn-ghost btn-sm" @click="errorMessage = null">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-5 w-5"
              viewBox="0 0 20 20"
              fill="currentColor"
            >
              <path
                fill-rule="evenodd"
                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                clip-rule="evenodd"
              />
            </svg>
          </button>
        </div>

        <!-- Header: Info + Timer -->
        <div class="flex justify-between items-start gap-4 mb-4">
          <div class="flex flex-wrap gap-2">
            <div class="badge badge-primary">{{ current.category.name }}</div>
            <div class="badge badge-secondary">
              {{ current.difficulty.name }}
            </div>
          </div>
          <div
            class="relative w-24 h-24 flex-shrink-0 flex items-center justify-center"
            :class="{ flashing: isFlashing }"
          >
            <div
              class="radial-progress transition-all"
              :class="timerColorClass"
              :style="{
                '--value': timerPercentage,
                '--size': '6rem',
                '--thickness': '8px',
              }"
              role="progressbar"
            ></div>
            <span class="absolute text-2xl font-bold mono">{{ timeLeft }}</span>
          </div>
        </div>

        <div class="divider -my-2"></div>

        <!-- Question Image -->
        <figure
          v-if="current.imageName"
          class="my-4 bg-base-200 rounded-lg overflow-hidden"
        >
          <img
            :src="getImageUrl(current.imageName)"
            alt="Image de la question"
            class="w-full h-auto max-h-96 object-contain"
          />
        </figure>

        <!-- Question Text -->
        <h2
          class="text-2xl md:text-3xl font-bold text-center leading-tight my-6"
        >
          {{ current.content }}
        </h2>

        <!-- Proposals with Images -->
        <div
          v-if="proposalsHaveImages"
          class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6"
        >
          <div
            v-for="p in current.proposals"
            :key="p.id"
            class="card card-compact bg-base-200 border-2 border-transparent transition-all duration-300 ease-in-out"
            :class="getProposalClasses(p)"
            @click="onChoose(p)"
          >
            <figure>
              <img
                :src="getImageUrl(p.imageName)"
                :alt="p.content"
                class="w-full h-40 object-cover"
              />
            </figure>
            <div class="card-body text-center">
              <p class="font-semibold">{{ p.content }}</p>
            </div>
          </div>
        </div>

        <!-- Proposals without Images -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-6">
          <button
            v-for="p in current.proposals"
            :key="p.id"
            class="btn btn-lg h-auto py-3 text-wrap"
            :class="{
              'btn-outline': !hasAnswered,
              'btn-success animate-pulse':
                hasAnswered && isCorrect && chosenId === p.id,
              'btn-error': hasAnswered && !isCorrect && chosenId === p.id,
              'btn-disabled': hasAnswered && chosenId !== p.id,
            }"
            :disabled="hasAnswered || submitting"
            @click="onChoose(p)"
          >
            <span
              v-if="submitting && chosenId === p.id"
              class="loading loading-spinner"
            ></span>
            {{ p.content }}
          </button>
        </div>

        <!-- Feedback & Next Action -->
        <div v-if="hasAnswered" class="animate-fade-in mt-6 space-y-4">
          <div
            class="alert shadow-md rounded-lg"
            :class="isCorrect ? 'alert-success' : 'alert-error'"
          >
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
                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
            <div>
              <h3 class="font-bold">
                {{ isCorrect ? 'Bonne réponse !' : 'Mauvaise réponse' }}
              </h3>
              <p v-if="current.explanation" class="text-sm">
                {{ current.explanation }}
              </p>
            </div>
          </div>

          <div class="card-actions justify-end">
            <button class="btn btn-primary btn-wide" @click="loadNext">
              Question Suivante
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
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
  animation: fadeIn 0.5s ease-out forwards;
}

@keyframes fadeInSlow {
  from {
    opacity: 0;
    transform: scale(0.98);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}
.animate-fade-in-slow {
  animation: fadeInSlow 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
}

@keyframes flash-anim {
  0%,
  100% {
    transform: scale(1);
    box-shadow: 0 0 0 0 rgba(255, 0, 0, 0.7);
  }
  50% {
    transform: scale(1.1);
    box-shadow: 0 0 10px 15px rgba(255, 0, 0, 0);
  }
}

.flashing .radial-progress {
  animation: flash-anim 1s infinite;
}

.text-wrap {
  white-space: normal;
}
</style>
