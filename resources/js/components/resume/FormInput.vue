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
      :dir="isLtr ? 'ltr' : undefined"
      :lang="isLtr ? 'en' : undefined"
      :inputmode="inputMode"
      :autocomplete="autocomplete"
      :autocapitalize="isLtr ? 'off' : undefined"
      :spellcheck="isLtr ? false : undefined"
      class="input-field disabled:opacity-50"
      :class="isLtr ? 'text-left' : ''"
      @input="$emit('update:modelValue', $event.target.value)"
    />
  </label>
</template>

<script setup>
import { computed } from 'vue'

defineOptions({ inheritAttrs: false })

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  label: { type: String, required: true },
  type: { type: String, default: 'text' },
  placeholder: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  maxlength: { type: [Number, String], default: null },
  autocomplete: { type: String, default: undefined },
})

defineEmits(['update:modelValue'])

const isLtr = computed(() =>
  ['email', 'tel', 'url', 'password'].includes(props.type)
)
const inputMode = computed(() => {
  if (props.type === 'email') return 'email'
  if (props.type === 'tel') return 'tel'
  return undefined
})
</script>
