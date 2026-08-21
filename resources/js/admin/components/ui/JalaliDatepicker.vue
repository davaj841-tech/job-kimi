<template>
  <PersianDatePicker
    :model-value="modelValue"
    format="iso"
    mode="date"
    :label="label"
    :error="error"
    placeholder="انتخاب تاریخ"
    :year-from="1390"
    :year-to="1410"
    @update:model-value="$emit('update:modelValue', $event)"
  />
</template>

<script setup>
import PersianDatePicker from '../../../components/ui/PersianDatePicker.vue'

defineProps({
  modelValue: { type: String, default: '' },
  label: { type: String, default: '' },
  error: { type: String, default: '' },
})
defineEmits(['update:modelValue'])
</script>
