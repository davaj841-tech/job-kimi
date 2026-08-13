<template>
  <label class="block" :class="$attrs.class">
    <span class="mb-1.5 block text-xs font-medium text-desk-muted">
      {{ label }}
      <span v-if="required" class="text-brand">*</span>
    </span>
    <input
      :value="modelValue"
      :type="type"
      :placeholder="placeholder"
      :required="required"
      :disabled="disabled"
      :maxlength="maxlength || undefined"
      class="input-field disabled:opacity-50"
      @input="$emit('update:modelValue', $event.target.value)"
    />
  </label>
</template>

<script setup>
defineOptions({ inheritAttrs: false })
defineProps({
  modelValue: { type: [String, Number], default: '' },
  label: { type: String, required: true },
  type: { type: String, default: 'text' },
  placeholder: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  maxlength: { type: [Number, String], default: null },
})
defineEmits(['update:modelValue'])
</script>
