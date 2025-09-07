<script setup>
import { ref, onUnmounted } from 'vue'

const props = defineProps({
  isPaused: {
    type: Boolean,
    default: false,
  },
})

const timer = ref(0)
let startTime = null
let interval = null

const formatTime = (seconds) => {
  const mins = Math.floor(seconds / 60)
  const secs = seconds % 60
  return `${mins}:${secs.toString().padStart(2, '0')}`
}

const start = () => {
  stop() // Ensure no multiple intervals
  timer.value = 0
  startTime = new Date()
  interval = setInterval(() => {
    if (!props.isPaused && startTime) {
      timer.value = Math.floor((new Date() - startTime) / 1000)
    }
  }, 1000)
}

const stop = () => {
  if (interval) {
    clearInterval(interval)
    interval = null
  }
}

onUnmounted(() => {
  stop()
})

defineExpose({
  start,
  stop,
})
</script>

<template>
  <div class="flex items-center space-x-2 text-primary">
    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
      <path
        fill-rule="evenodd"
        d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
        clip-rule="evenodd"
      />
    </svg>
    <span class="text-lg font-mono font-bold">{{ formatTime(timer) }}</span>
  </div>
</template>
