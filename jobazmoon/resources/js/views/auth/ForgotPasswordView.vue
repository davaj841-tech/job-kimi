<template>
  <div class="flex min-h-dvh flex-col bg-white px-5 py-10">
    <div class="mb-10 text-center">
      <h1 class="text-3xl font-black text-brand">جاب‌آزمون</h1>
      <p class="mt-2 text-sm text-ink-muted">بازنشانی رمز عبور از طریق ایمیل</p>
    </div>

    <form v-if="!sent" class="space-y-4" @submit.prevent="submit">
      <label class="block text-sm font-medium">ایمیل</label>
      <input v-model="email" type="email" required class="input-field text-left" dir="ltr" placeholder="you@example.com" />
      <button class="btn-primary" :disabled="loading">ارسال لینک بازنشانی</button>
    </form>

    <p v-else class="rounded-xl bg-green-50 p-4 text-center text-sm text-green-800">
      لینک بازنشانی به ایمیل شما ارسال شد
    </p>

    <p v-if="error" class="mt-4 text-center text-sm text-brand">{{ error }}</p>

    <RouterLink to="/login" class="mt-8 text-center text-sm text-ink-muted underline">بازگشت به ورود</RouterLink>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import api from '../../api/client';

const email = ref('');
const loading = ref(false);
const sent = ref(false);
const error = ref('');

async function submit() {
  error.value = '';
  loading.value = true;
  try {
    await api.post('/auth/forgot-password', { email: email.value.trim() });
    sent.value = true;
  } catch (e) {
    error.value = e.response?.data?.message || 'ارسال ناموفق بود.';
  } finally {
    loading.value = false;
  }
}
</script>
