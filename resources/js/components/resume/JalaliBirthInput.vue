<template>
  <PersianDatePicker
    :model-value="modelValue"
    format="jalali"
    mode="date"
    :label="label"
    :required="required"
    placeholder="روز / ماه / سال"
    :year-from="1300"
    :year-to="1405"
    @update:model-value="$emit('update:modelValue', $event)"
  />
</template>

<script setup>
import PersianDatePicker from '../ui/PersianDatePicker.vue'

defineProps({
  modelValue: { type: String, default: '' },
  label: { type: String, default: 'تاریخ تولد' },
  required: { type: Boolean, default: false },
})
defineEmits(['update:modelValue'])
</script>
