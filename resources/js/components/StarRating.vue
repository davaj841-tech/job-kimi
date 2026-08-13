<template>
  <div
    class="inline-flex items-center gap-0.5"
    role="radiogroup"
    :aria-label="readonly ? 'امتیاز' : 'امتیازدهی'"
  >
    <button
      v-for="n in 5"
      :key="n"
      type="button"
      class="leading-none transition"
      :class="[
        compact ? 'text-sm' : 'text-lg',
        n <= display ? 'text-amber-400' : 'text-slate-300 dark:text-slate-600',
        readonly ? 'cursor-default' : 'cursor-pointer hover:scale-110',
      ]"
      :disabled="readonly"
      @click="set(n)"
      @mouseenter="hover = n"
      @mouseleave="hover = 0"
    >
      ★
    </button>
    <span
      v-if="showValue"
      class="mr-1 text-[11px] tabular-nums text-desk-muted"
    >
      {{ fa(avg || modelValue) }}
      <template v-if="count"> ({{ faInt(count) }})</template>
    </span>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { toFaDigits } from '../utils/format'

const props = defineProps({
  modelValue: { type: Number, default: 0 },
  avg: { type: Number, default: 0 },
  count: { type: Number, default: 0 },
  readonly: Boolean,
  showValue: Boolean,
  compact: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue'])
const hover = ref(0)
const display = computed(() => {
  if (hover.value) return hover.value
  if (props.modelValue) return props.modelValue
  const a = Number(props.avg) || 0
  return a > 0 ? Math.round(a) : 0
})

function set(n) {
  if (props.readonly) return
  emit('update:modelValue', n)
}
function fa(n) {
  return toFaDigits(Number(n || 0).toFixed(1))
}
function faInt(n) {
  return toFaDigits(Number(n || 0))
}
</script>
