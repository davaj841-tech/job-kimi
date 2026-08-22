<template>
  <div
    class="exam-timer"
    :class="timerClass"
    data-testid="exam-timer"
    role="timer"
    :aria-label="`زمان باقی‌مانده ${display}`"
  >
    <span class="exam-timer__label">زمان باقی‌مانده</span>
    <span class="exam-timer__value font-mono tabular-nums" data-testid="exam-timer-value">
      {{ display }}
    </span>
    <span
      v-if="isWarning"
      class="exam-timer__warn"
      data-testid="exam-timer-warning"
    >
      کمتر از ۵ دقیقه مانده
    </span>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'

const WARNING_MS = 5 * 60 * 1000

const props = withDefaults(
  defineProps<{
    /** Unix timestamp (ms) when the exam ends */
    endsAt: number
    /** Tick interval in ms */
    intervalMs?: number
  }>(),
  {
    intervalMs: 1000,
  }
)

const emit = defineEmits<{
  warning: []
  expired: []
}>()

const now = ref(Date.now())
let timer: ReturnType<typeof setInterval> | undefined
let warned = false
let expiredEmitted = false

const remainingMs = computed(() => Math.max(0, Number(props.endsAt) - now.value))

const display = computed(() => {
  const totalSec = Math.floor(remainingMs.value / 1000)
  const h = Math.floor(totalSec / 3600)
  const m = Math.floor((totalSec % 3600) / 60)
  const s = totalSec % 60
  const pad = (n: number) => String(n).padStart(2, '0')
  if (h > 0) return `${pad(h)}:${pad(m)}:${pad(s)}`
  return `${pad(m)}:${pad(s)}`
})

const isWarning = computed(
  () => remainingMs.value > 0 && remainingMs.value <= WARNING_MS
)

const timerClass = computed(() => {
  if (remainingMs.value <= 0) return 'exam-timer--expired'
  if (isWarning.value) return 'exam-timer--warning'
  return 'exam-timer--ok'
})

function tick(): void {
  now.value = Date.now()

  if (!warned && remainingMs.value > 0 && remainingMs.value <= WARNING_MS) {
    warned = true
    emit('warning')
  }

  if (!expiredEmitted && remainingMs.value <= 0) {
    expiredEmitted = true
    emit('expired')
    stop()
  }
}

function stop(): void {
  if (timer) {
    clearInterval(timer)
    timer = undefined
  }
}

function start(): void {
  stop()
  warned = false
  expiredEmitted = false
  tick()
  if (remainingMs.value > 0) {
    timer = setInterval(tick, props.intervalMs)
  }
}

watch(
  () => props.endsAt,
  () => start()
)

onMounted(() => start())
onUnmounted(() => stop())

defineExpose({
  remainingMs,
  isWarning,
  display,
  tick,
  stop,
  start,
})
</script>
