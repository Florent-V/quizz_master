<template>
  <label class="swap swap-rotate btn btn-ghost btn-circle">
    <input type="checkbox" :checked="theme === 'dark'" @change="toggleTheme" />

    <!-- sun icon -->
    <svg
      class="swap-off fill-current w-5 h-5"
      xmlns="http://www.w3.org/2000/svg"
      viewBox="0 0 512 512"
    >
      <path
        fill="var(--ci-primary-color, currentColor)"
        d="M256,104c-83.813,0-152,68.187-152,152s68.187,152,152,152,152-68.187,152-152S339.813,104,256,104Zm0,272A120,120,0,1,1,376,256,120.136,120.136,0,0,1,256,376Z"
        class="ci-primary"
      />
      <rect
        width="32"
        height="48"
        x="240"
        y="16"
        fill="var(--ci-primary-color, currentColor)"
        class="ci-primary"
      />
      <rect
        width="32"
        height="48"
        x="240"
        y="448"
        fill="var(--ci-primary-color, currentColor)"
        class="ci-primary"
      />
      <rect
        width="48"
        height="32"
        x="448"
        y="240"
        fill="var(--ci-primary-color, currentColor)"
        class="ci-primary"
      />
      <rect
        width="48"
        height="32"
        x="16"
        y="240"
        fill="var(--ci-primary-color, currentColor)"
        class="ci-primary"
      />
      <rect
        width="32"
        height="45.255"
        x="400"
        y="393.373"
        fill="var(--ci-primary-color, currentColor)"
        class="ci-primary"
        transform="rotate(-45 416 416)"
      />
      <rect
        width="32.001"
        height="45.255"
        x="80"
        y="73.373"
        fill="var(--ci-primary-color, currentColor)"
        class="ci-primary"
        transform="rotate(-45 96 96)"
      />
      <rect
        width="45.255"
        height="32"
        x="73.373"
        y="400"
        fill="var(--ci-primary-color, currentColor)"
        class="ci-primary"
        transform="rotate(-45.001 96.002 416.003)"
      />
      <rect
        width="45.255"
        height="32.001"
        x="393.373"
        y="80"
        fill="var(--ci-primary-color, currentColor)"
        class="ci-primary"
        transform="rotate(-45 416 96)"
      />
    </svg>

    <!-- moon icon -->
    <svg
      class="swap-on fill-current w-5 h-5 bi bi-moon-stars"
      xmlns="http://www.w3.org/2000/svg"
      width="16"
      height="16"
      fill="currentColor"
      viewBox="0 0 16 16"
    >
      <path
        d="M6 .278a.768.768 0 0 1 .08.858 7.208 7.208 0 0 0-.878 3.46c0 4.021 3.278 7.277 7.318 7.277.527 0 1.04-.055 1.533-.16a.787.787 0 0 1 .81.316.733.733 0 0 1-.031.893A8.349 8.349 0 0 1 8.344 16C3.734 16 0 12.286 0 7.71 0 4.266 2.114 1.312 5.124.06A.752.752 0 0 1 6 .278zM4.858 1.311A7.269 7.269 0 0 0 1.025 7.71c0 4.02 3.279 7.276 7.319 7.276a7.316 7.316 0 0 0 5.205-2.162c-.337.042-.68.063-1.029.063-4.61 0-8.343-3.714-8.343-8.29 0-1.167.242-2.278.681-3.286z"
      ></path>
      <path
        d="M10.794 3.148a.217.217 0 0 1 .412 0l.387 1.162c.173.518.579.924 1.097 1.097l1.162.387a.217.217 0 0 1 0 .412l-1.162.387a1.734 1.734 0 0 0-1.097 1.097l-.387 1.162a.217.217 0 0 1-.412 0l-.387-1.162A1.734 1.734 0 0 0 9.31 6.593l-1.162-.387a.217.217 0 0 1 0-.412l1.162-.387a1.734 1.734 0 0 0 1.097-1.097l.387-1.162zM13.863.099a.145.145 0 0 1 .274 0l.258.774c.115.346.386.617.732.732l.774.258a.145.145 0 0 1 0 .274l-.774.258a1.156 1.156 0 0 0-.732.732l-.258.774a.145.145 0 0 1-.274 0l-.258-.774a1.156 1.156 0 0 0-.732-.732l-.774-.258a.145.145 0 0 1 0-.274l.774.258c.346-.115.617-.386.732-.732L13.863.1z"
      ></path>
    </svg>
  </label>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const theme = ref('light')

function applyTheme(selectedTheme) {
  document.documentElement.setAttribute('data-theme', selectedTheme)
  localStorage.setItem('theme', selectedTheme)
  theme.value = selectedTheme
}

const toggleTheme = () => {
  const newTheme = theme.value === 'light' ? 'dark' : 'light'
  applyTheme(newTheme)
}

onMounted(() => {
  const savedTheme = localStorage.getItem('theme')
  if (savedTheme) {
    applyTheme(savedTheme)
  } else {
    applyTheme('light') // Default theme
  }
})
</script>
