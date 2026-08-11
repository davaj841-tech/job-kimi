<template>
  <PageShell title="تماس با ما" subtitle="پیام شما را می‌خوانیم و در اولین فرصت پاسخ می‌دهیم.">

    <div class="grid gap-8 lg:grid-cols-2">
      <form
        class="space-y-3 rounded-2xl border border-surface-line bg-white p-5"
        @submit.prevent="submit"
      >
        <input v-model="form.name" required class="field" placeholder="نام *" />
        <input
          v-model="form.email"
          type="email"
          required
          class="field"
          dir="ltr"
          placeholder="ایمیل *"
        />
        <select v-model="form.subject" required class="field">
          <option disabled value="">موضوع *</option>
          <option value="support">پشتیبانی</option>
          <option value="complaint">شکایت</option>
          <option value="suggestion">پیشنهاد</option>
          <option value="partnership">همکاری</option>
        </select>
        <textarea
          v-model="form.message"
          required
          rows="5"
          class="field min-h-[120px] py-2"
          placeholder="پیام شما *"
        />
        <p v-if="error" class="text-sm text-red-500">{{ error }}</p>
        <p v-if="success" class="text-sm text-emerald-600">{{ success }}</p>
        <button type="submit" class="btn-primary w-full" :disabled="sending">
          {{ sending ? 'در حال ارسال...' : 'ارسال پیام' }}
        </button>
      </form>

      <div class="space-y-5">
        <div
          class="rounded-2xl border border-surface-line bg-white p-5 text-sm leading-7"
        >
          <p><strong>ایمیل:</strong> support@jobazmoon.ir</p>
          <p><strong>تلفن:</strong> ۰۲۱-۹۱۰۰۰۰۰۰</p>
          <p>
            <strong>آدرس:</strong> تهران، خیابان نمونه، پلاک ۱ (placeholder)
          </p>
        </div>

        <div class="flex gap-3">
          <a
            href="https://t.me/jobazmoon"
            target="_blank"
            rel="noopener"
            class="flex-1 rounded-xl bg-[#0a1c33] py-3 text-center text-sm font-bold text-white"
            >تلگرام</a
          >
          <a
            href="https://instagram.com/jobazmoon"
            target="_blank"
            rel="noopener"
            class="flex-1 rounded-xl bg-brand py-3 text-center text-sm font-bold text-white"
            >اینستاگرام</a
          >
        </div>

        <div
          class="flex h-48 items-center justify-center rounded-2xl bg-gray-200 text-sm text-ink-muted"
        >
          نقشه (placeholder)
        </div>
      </div>
    </div>
  </PageShell>
</template>

<script setup>
import { reactive, ref } from 'vue'
import PageShell from '../../components/layout/PageShell.vue'
import api from '../../api/client'
import { apiErrorMessage } from '../../utils/format'

const form = reactive({ name: '', email: '', subject: '', message: '' })
const sending = ref(false)
const error = ref('')
const success = ref('')

async function submit() {
  sending.value = true
  error.value = ''
  success.value = ''
  try {
    await api.post('/contact', { ...form })
    success.value = 'پیام شما با موفقیت ارسال شد.'
    form.name = ''
    form.email = ''
    form.subject = ''
    form.message = ''
  } catch (e) {
    error.value = apiErrorMessage(e, 'ارسال ناموفق بود.')
  } finally {
    sending.value = false
  }
}
</script>

