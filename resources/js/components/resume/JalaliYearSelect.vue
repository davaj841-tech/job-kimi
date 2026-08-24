<template>
  <label class="block">
    <span v-if="label" class="mb-1.5 block text-xs font-medium text-desk-muted">
      {{ label }}
      <span v-if="required" class="text-brand">*</span>
    </span>
    <select
      class="input-field"
      :value="modelValue || ''"
      :required="required"
      @change="onChange"
    >
      <option value="">{{ placeholder }}</option>
      <option v-for="y in years" :key="y" :value="y">
        {{ y }}
      </option>
    </select>
  </label>
</template>

<script setup>
const props = defineProps({
  modelValue: { type: [Number, String, null], default: null },
  label: { type: String, default: '' },
  placeholder: { type: String, default: 'انتخاب سال' },
  required: { type: Boolean, default: false },
  from: { type: Number, default: 1405 },
  to: { type: Number, default: 1330 },
})
const emit = defineEmits(['update:modelValue'])

const years = Array.from(
  { length: Math.max(0, props.from - props.to + 1) },
  (_, i) => props.from - i
)

function onChange(e) {
  const v = e.target.value
  emit('update:modelValue', v === '' ? null : Number(v))
}
</script>
