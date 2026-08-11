<template>
  <div class="inline-flex items-center gap-1" role="radiogroup">
    <button
      v-for="n in 5"
      :key="n"
      type="button"
      class="text-xl leading-none transition"
      :class="n <= display ? 'text-amber-400' : 'text-slate-300'"
      :disabled="readonly"
      @click="set(n)"
      @mouseenter="hover = n"
      @mouseleave="hover = 0"
    >
      ★
    </button>
    <span v-if="showValue" class="mr-2 text-xs text-slate-500">{{
      fa(modelValue || avg)
    }}</span>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { toFaDigits } from '../utils/format'

const props = defineProps({
  modelValue: { type: Number, default: 0 },
  avg: { type: Number, default: 0 },
  readonly: Boolean,
  showValue: Boolean,
})
const emit = defineEmits(['update:modelValue'])
const hover = ref(0)
const display = computed(
  () => hover.value || props.modelValue || Math.round(props.avg || 0)
)

function set(n) {
  if (props.readonly) return
  emit('update:modelValue', n)
}
function fa(n) {
  return toFaDigits(Number(n || 0).toFixed(1))
}
</script>
