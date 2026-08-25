<template>
  <div
    class="h-2.5 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700"
  >
    <div
      class="h-2.5 rounded-full transition-all duration-1000 ease-out"
      :class="colorClass"
      :style="{ width: `${clamped}%` }"
    />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    percent: number
    color?: string
  }>(),
  {
    color: 'brand',
  }
)

const clamped = computed(() =>
  Math.max(0, Math.min(100, Number(props.percent) || 0))
)

const colorClass = computed(() => {
  const map: Record<string, string> = {
    brand: 'bg-gradient-to-l from-brand to-brand-dark',
    green: 'bg-emerald-500',
    amber: 'bg-amber-500',
    blue: 'bg-sky-500',
    orange: 'bg-desk-orange',
  }
  return map[props.color] || props.color || map.brand
})
</script>
