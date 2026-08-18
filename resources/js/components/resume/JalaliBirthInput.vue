<template>
  <label class="block">
    <span class="mb-1.5 block text-xs font-medium text-desk-muted">
      {{ label }}
      <span v-if="required" class="text-brand">*</span>
    </span>
    <div class="grid grid-cols-3 gap-2">
      <select
        v-model.number="day"
        class="input-field px-1 text-center text-sm"
        :required="required"
      >
        <option :value="0">روز</option>
        <option
          v-for="d in 31"
          :key="d"
          :value="d"
        >
          {{ pad(d) }}
        </option>
      </select>
      <select
        v-model.number="month"
        class="input-field px-1 text-center text-sm"
        :required="required"
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
        v-model.number="year"
        class="input-field px-1 text-center text-sm"
        :required="required"
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
import { computed, ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
  label: { type: String, default: 'تاریخ تولد' },
  required: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue'])

const years = Array.from({ length: 80 }, (_, i) => 1405 - i)
const day = ref(0)
const month = ref(0)
const year = ref(0)

function pad(n) {
  return String(n).padStart(2, '0')
}

function parse(val) {
  const m = String(val || '').match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/)
  if (!m) {
    day.value = 0
    month.value = 0
    year.value = 0
    return
  }
  day.value = Number(m[1])
  month.value = Number(m[2])
  year.value = Number(m[3])
}

parse(props.modelValue)
watch(
  () => props.modelValue,
  (v) => parse(v)
)

const display = computed(() => {
  if (!day.value || !month.value || !year.value) return ''
  return `${pad(day.value)}/${pad(month.value)}/${year.value}`
})

watch([day, month, year], () => {
  emit('update:modelValue', display.value)
})
</script>
