<template>
  <div class="relative">
    <input
      :value="modelValue"
      :type="visible ? 'text' : 'password'"
      :class="inputClass"
      :placeholder="placeholder"
      :required="required"
      :autocomplete="autocomplete"
      :dir="dir"
      :lang="lang"
      class="pe-10"
      @input="$emit('update:modelValue', $event.target.value)"
      @keyup.enter="$emit('enter')"
    />
    <button
      type="button"
      class="absolute left-2 top-1/2 -translate-y-1/2 rounded-md p-1 text-slate-400 hover:text-slate-700"
      :aria-label="visible ? 'مخفی کردن رمز' : 'نمایش رمز'"
      @click="visible = !visible"
    >
      <EyeSlashIcon v-if="visible" class="h-5 w-5" />
      <EyeIcon v-else class="h-5 w-5" />
    </button>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline'

defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  required: { type: Boolean, default: false },
  autocomplete: { type: String, default: 'current-password' },
  dir: { type: String, default: 'ltr' },
  lang: { type: String, default: 'en' },
  inputClass: { type: String, default: 'input-field' },
})

defineEmits(['update:modelValue', 'enter'])

const visible = ref(false)
</script>
