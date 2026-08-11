<template>
  <div
    class="p-3 rounded-xl border"
    :class="boxClass"
  >
    <component
      :is="icon"
      class="mb-2 h-5 w-5"
      :class="iconClass"
    />
    <p class="text-xs text-desk-muted">{{ label }}</p>
    <p
      class="mt-0.5 text-sm font-bold"
      :class="valueClass"
    >
      {{ value }}
    </p>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  icon: { type: [Object, Function], required: true },
  label: { type: String, required: true },
  value: { type: String, default: '—' },
  highlight: { type: Boolean, default: false },
  alert: { type: Boolean, default: false },
})

const boxClass = computed(() => {
  if (props.alert) return 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/10'
  if (props.highlight) return 'border-brand/30 bg-brand-soft dark:border-brand/40 dark:bg-brand/10'
  return 'border-surface-line bg-slate-50 dark:border-slate-700 dark:bg-slate-800/60'
})

const iconClass = computed(() => {
  if (props.alert) return 'text-red-500'
  if (props.highlight) return 'text-brand'
  return 'text-desk-muted'
})

const valueClass = computed(() => {
  if (props.alert) return 'text-red-700 dark:text-red-400'
  if (props.highlight) return 'text-brand'
  return 'text-desk-text dark:text-white'
})
</script>
