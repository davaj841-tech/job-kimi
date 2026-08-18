<template>
  <div class="flex min-h-dvh flex-col bg-white px-5 py-10">
    <div class="mb-10 text-center">
      <h1 class="text-3xl font-black text-brand">جاب‌آزمون</h1>
      <p class="mt-2 text-sm text-ink-muted">تعیین رمز عبور جدید</p>
    </div>

    <form class="space-y-4" @submit.prevent="submit">
      <label class="block text-sm font-medium">رمز جدید</label>
      <PasswordInput
        v-model="password"
        input-class="input-field text-left"
        required
        autocomplete="new-password"
      />
      <label class="block text-sm font-medium">تکرار رمز</label>
      <PasswordInput
        v-model="password_confirmation"
        input-class="input-field text-left"
        required
        autocomplete="new-password"
      />
      <button class="btn-primary" :disabled="loading">ذخیره رمز عبور</button>
    </form>

    <p v-if="message" class="mt-4 text-center text-sm text-green-700">
      {{ message }}
    </p>
    <p v-if="error" class="mt-4 text-center text-sm text-brand">{{ error }}</p>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import PasswordInput from '../../components/PasswordInput.vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../../api/client'

const route = useRoute()
const router = useRouter()
const password = ref('')
const password_confirmation = ref('')
const loading = ref(false)
const message = ref('')
const error = ref('')

async function submit() {
  error.value = ''
  message.value = ''
  loading.value = true
  try {
    const { data } = await api.post('/auth/reset-password', {
      email: route.query.email,
      token: route.query.token,
      password: password.value,
      password_confirmation: password_confirmation.value,
    })
    message.value = data.message || 'رمز عبور با موفقیت تغییر کرد.'
    setTimeout(() => router.replace('/login'), 1500)
  } catch (e) {
    error.value = e.response?.data?.message || 'بازنشانی ناموفق بود.'
  } finally {
    loading.value = false
  }
}
</script>
