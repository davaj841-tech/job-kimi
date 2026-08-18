<template>
  <label class="block">
    <span
      v-if="label"
      class="mb-1.5 block text-xs font-medium text-desk-muted"
    >
      {{ label }}
      <span v-if="required" class="text-brand">*</span>
    </span>
    <div class="grid grid-cols-2 gap-2">
      <select
        class="input-field px-1 text-center text-sm"
        :value="month"
        :disabled="disabled"
        @change="onMonth"
      >
        <option :value="0">ماه</option>
        <option
          v-for="m in 12"
          :key="m"
          :value="m"
        >
          {{ pad(m) }}
        </option>
      </select>
      <select
        class="input-field px-1 text-center text-sm"
        :value="year"
        :disabled="disabled"
        @change="onYear"
      >
        <option :value="0">سال</option>
        <option
          v-for="y in years"
          :key="y"
          :value="y"
        >
          {{ y }}
        </option>
      </select>
    </div>
  </label>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
  label: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  from: { type: Number, default: 1405 },
  to: { type: Number, default: 1330 },
})
const emit = defineEmits(['update:modelValue'])

const years = Array.from(
  { length: Math.max(0, props.from - props.to + 1) },
  (_, i) => props.from - i
)

const year = ref(0)
const month = ref(0)

function pad(n) {
  return String(n).padStart(2, '0')
}

function parseValue(val) {
  const m = String(val || '').match(/^(\d{4})-(\d{1,2})$/)
  if (!m) return { year: 0, month: 0 }
  return { year: Number(m[1]), month: Number(m[2]) }
}

watch(
  () => props.modelValue,
  (val) => {
    const parsed = parseValue(val)
    if (parsed.year && parsed.month) {
      year.value = parsed.year
      month.value = parsed.month
    }
    if (!val) {
      year.value = 0
      month.value = 0
    }
  },
  { immediate: true }
)

function emitComplete() {
  if (year.value && month.value) {
    emit('update:modelValue', `${year.value}-${pad(month.value)}`)
    return
  }
  if (!year.value && !month.value) {
    emit('update:modelValue', '')
  }
}

function onMonth(e) {
  month.value = Number(e.target.value)
  emitComplete()
}

function onYear(e) {
  year.value = Number(e.target.value)
  emitComplete()
}
</script>
