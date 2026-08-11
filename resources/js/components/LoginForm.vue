<template>
  <form class="space-y-3" dir="rtl" @submit.prevent="onSubmit">
    <label class="mb-1 block text-xs font-bold text-ink-soft"
      >شماره موبایل</label
    >
    <input
      v-model="mobile"
      type="tel"
      class="input-field"
      dir="ltr"
      autocomplete="tel"
      required
      placeholder="09123456789"
    />
    <button type="submit" class="btn-primary w-full">ارسال کد</button>
  </form>
</template>

<script setup lang="ts">
import { ref } from 'vue'

const emit = defineEmits<{
  submit: [mobile: string]
}>()

const mobile = ref('')

function onSubmit(): void {
  emit('submit', mobile.value)
}
</script>
