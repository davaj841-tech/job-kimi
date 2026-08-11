<template>
  <section class="bg-white py-10 sm:py-12">
    <div class="mx-auto max-w-7xl px-4">
      <div
        class="flex flex-col items-stretch justify-between gap-6 rounded-2xl border border-surface-line bg-gradient-to-l from-slate-50 to-brand-soft/40 p-6 sm:flex-row sm:items-center sm:p-8"
      >
        <div class="text-right">
          <h2 class="mb-1 text-xl font-black text-desk-dark sm:text-2xl">خبرنامه جاب‌آزمون</h2>
          <p class="text-sm leading-7 text-desk-muted">
            از آخرین استخدام‌ها و آزمون‌ها زودتر باخبر شوید.
          </p>
        </div>
        <form
          class="flex w-full max-w-md flex-row-reverse gap-2"
          @submit.prevent="submit"
        >
          <button
            type="submit"
            class="shrink-0 rounded-xl bg-brand px-5 py-3 text-sm font-bold text-white transition hover:bg-brand-dark disabled:opacity-60"
            :disabled="submitting"
          >
            عضویت
          </button>
          <input
            v-model="contact"
            type="text"
            required
            placeholder="ایمیل یا موبایل"
            class="h-12 w-full rounded-xl border border-surface-line bg-white px-4 text-sm text-desk-text outline-none ring-brand focus:ring-2"
          />
        </form>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref } from 'vue'
import { useToast } from '../../composables/useToast'
import api from '../../api/client'
import { apiErrorMessage } from '../../utils/format'

const toast = useToast()
const contact = ref('')
const submitting = ref(false)

async function submit() {
  submitting.value = true
  try {
    await api.post('/newsletter/subscribe', { contact: contact.value })
    toast.success('عضویت در خبرنامه ثبت شد.')
    contact.value = ''
  } catch (e) {
    toast.error(apiErrorMessage(e, 'ثبت عضویت ناموفق بود.'))
  } finally {
    submitting.value = false
  }
}
</script>
