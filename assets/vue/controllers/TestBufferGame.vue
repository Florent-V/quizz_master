<script setup>
import { ref } from 'vue'
import axios from 'axios'

const props = defineProps({ quizSessionId: { type: Number, required: true } })

const buffer = ref([]) // file d'attente
const current = ref(null) // question affichée

function statusLabel(s) {
  if (s === 'correct') return 'Bonne réponse'
  if (s === 'wrong') return 'Mauvaise réponse'
  if (s === 'pending') return 'Envoi…'
  return ''
}

function optimisticClass(proposalId) {
  if (!current.value) return ''
  if (current.value.finished && current.value.chosenId === proposalId) {
    return current.value.status === 'correct' ? 'btn-success' : 'btn-error'
  }
  if (current.value.locked && current.value.chosenId === proposalId)
    return 'btn-active'
  return ''
}

async function prefetch(limit = 3) {
  const { data } = await axios.get(
    `/api/quiz-session/${props.quizSessionId}/next-questions`,
    { params: { limit } },
  )
  const items = data.map((q) => ({
    ...q,
    tempId: crypto.randomUUID(),
    quizSessionAnswerId: null,
    locked: false,
    finished: false,
    chosenId: null,
    status: null,
  }))
  buffer.value.push(...items)
  if (!current.value) nextFromBuffer()
}

function nextFromBuffer() {
  if (buffer.value.length === 0) return
  current.value = buffer.value.shift()

  // Déclarer l’affichage → create-answer (optimistic: on n’attend pas pour autoriser le clic)
  axios
    .post(`/api/quiz-session/${props.quizSessionId}/create-answer`, {
      questionId: current.value.id,
      tempId: current.value.tempId,
    })
    .then(({ data }) => {
      // réconciliation
      if (current.value && current.value.tempId === data.tempId) {
        current.value.quizSessionAnswerId = data.quizSessionAnswerId
      }
      // si l’utilisateur a déjà cliqué très vite, et qu’on a bufferisé, on déclenche l’envoi maintenant
      maybeFlushBufferedAnswer()
    })
    .catch(() => {
      // en cas d’erreur back, on bloque la question
      if (current.value) {
        current.value.locked = true
        current.value.status = 'error'
        current.value.finished = true
      }
    })
}

let bufferedClick = null // {proposalId} si user clique avant d’avoir l’answerId

function answerOptimistic(q, proposal) {
  if (q.locked || q.finished) return
  q.locked = true
  q.chosenId = proposal.id
  q.status = 'pending'

  // si on n’a pas encore l’answerId, bufferiser
  if (!q.quizSessionAnswerId) {
    bufferedClick = { proposalId: proposal.id }
    return
  }

  // sinon, envoyer direct
  sendSubmit(q.quizSessionAnswerId, proposal.id, q)
}

function maybeFlushBufferedAnswer() {
  if (!current.value || !current.value.quizSessionAnswerId || !bufferedClick)
    return
  const { proposalId } = bufferedClick
  bufferedClick = null
  sendSubmit(current.value.quizSessionAnswerId, proposalId, current.value)
}

function sendSubmit(answerId, proposalId, q) {
  axios
    .post(`/api/quiz-session/${props.quizSessionId}/submit-answer`, {
      quizSessionAnswerId: answerId,
      proposalId,
    })
    .then(({ data }) => {
      q.status = data.isCorrect ? 'correct' : 'wrong'
      q.finished = true
    })
    .catch(() => {
      // rollback basique
      q.locked = false
      q.finished = false
      q.status = null
      q.chosenId = null
    })
}

// bootstrap
prefetch(3)
</script>
<template>
  <div class="max-w-2xl mx-auto p-4 space-y-4">
    <div class="flex items-center justify-between">
      <div class="text-sm opacity-70">Buffer: {{ buffer.length }}</div>
      <button class="btn btn-sm" @click="prefetch(3)">Précharger +3</button>
    </div>

    <div v-if="!current" class="flex flex-col items-center gap-2">
      <span class="loading loading-spinner" />
      <p>Chargement…</p>
    </div>

    <div v-else class="card bg-base-200 shadow-md p-6 space-y-4">
      <div class="text-lg font-bold">{{ current.content }}</div>

      <div class="grid grid-cols-1 gap-3">
        <button
          v-for="p in current.proposals"
          :key="p.id"
          class="btn btn-outline justify-start"
          :class="optimisticClass(p.id)"
          :disabled="current.locked"
          @click="answerOptimistic(current, p)"
        >
          {{ p.label }}
        </button>
      </div>

      <div class="flex items-center justify-between pt-2">
        <div
          v-if="current.status"
          class="badge"
          :class="
            current.status === 'correct'
              ? 'badge-success'
              : current.status === 'wrong'
                ? 'badge-error'
                : ''
          "
        >
          {{ statusLabel(current.status) }}
        </div>
        <button
          class="btn btn-primary"
          :disabled="!current.finished"
          @click="nextFromBuffer"
        >
          Question suivante
        </button>
      </div>
    </div>
  </div>
</template>
