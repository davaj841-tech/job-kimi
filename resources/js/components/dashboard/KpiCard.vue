<template>
  <Card
    class="flex items-center gap-4 p-4 opacity-0 motion-safe:animate-[fadeSlideUp_0.4s_ease-out_forwards]"
    :style="{ animationDelay: `${delay}ms` }"
  >
    <div
      class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl"
      :class="iconBg"
    >
      <component :is="icon" class="h-6 w-6" :class="iconColor" />
    </div>
    <div class="min-w-0">
      <p class="text-sm text-ink-muted dark:text-slate-400">{{ label }}</p>
      <p
        class="truncate text-xl font-bold text-ink dark:text-white sm:text-2xl"
      >
        {{ displayValue }}
      </p>
      <p v-if="trend" class="mt-0.5 text-xs" :class="trendColor">{{ trend }}</p>
    </div>
  </Card>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, type Component } from 'vue'
import Card from '../ui/Card.vue'
import { toFaDigits } from '@/utils/format'

const props = withDefaults(
  defineProps<{
    label: string
    value: number
    suffix?: string
    prefix?: string
    icon: Component
    iconBg?: string
    iconColor?: string
    trend?: string
    trendColor?: string
    delay?: number
    animate?: boolean
  }>(),
  {
    suffix: '',
    prefix: '',
    iconBg: 'bg-slate-100 dark:bg-slate-800',
    iconColor: 'text-ink',
    delay: 0,
    animate: true,
  }
)

const animated = ref(0)

onMounted(() => {
  if (!props.animate || props.value === 0) {
    animated.value = props.value
    return
  }
  const target = props.value
  const duration = 800
  const start = performance.now()
  const step = (now: number) => {
    const t = Math.min(1, (now - start) / duration)
    animated.value = Math.round(target * (1 - Math.pow(1 - t, 3)))
    if (t < 1) requestAnimationFrame(step)
  }
  requestAnimationFrame(step)
})

const displayValue = computed(() => {
  const n = props.animate ? animated.value : props.value
  const formatted = toFaDigits(Number.isInteger(n) ? n : n.toFixed(1))
  return `${props.prefix}${formatted}${props.suffix}`
})
</script>

<style scoped>
@keyframes fadeSlideUp {
  from {
    opacity: 0;
    transform: translateY(12px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
