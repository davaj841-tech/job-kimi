<template>
  <PersianDatePicker
    :model-value="modelValue"
    format="month"
    mode="month"
    :label="label"
    :required="required"
    :disabled="disabled"
    :error="error"
    :year-from="to"
    :year-to="from"
    placeholder="ماه / سال"
    @update:model-value="$emit('update:modelValue', $event)"
  />
</template>

<script setup>
import PersianDatePicker from '../ui/PersianDatePicker.vue'

defineProps({
  modelValue: { type: String, default: '' },
  label: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  error: { type: String, default: '' },
  from: { type: Number, default: 1405 },
  to: { type: Number, default: 1330 },
})
defineEmits(['update:modelValue'])
</script>
