<template>
  <svg
    xmlns="http://www.w3.org/2000/svg"
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.8"
    stroke-linecap="round"
    stroke-linejoin="round"
  >
    <path
      v-if="name === 'home'"
      d="M3 10.5 12 3l9 7.5V21a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1v-10.5z"
    />
    <path
      v-else-if="name === 'jobs'"
      d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M4 7h16v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7zM4 12h16"
    />
    <path
      v-else-if="name === 'exams'"
      d="M8 3h8v4H8zM7 7h10v14H7zM10 11h4M10 15h4"
    />
    <path
      v-else-if="name === 'pdf'"
      d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1zM14 3v5h5M9 13h6M9 17h4"
    />
    <path
      v-else-if="name === 'profile'"
      d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4zM4 21a8 8 0 0 1 16 0"
    />
    <circle v-else cx="12" cy="12" r="8" />
  </svg>
</template>

<script setup>
defineProps({
  name: { type: String, required: true },
  size: { type: [Number, String], default: 22 },
})
</script>
