<template>
  <section class="bg-white py-10 sm:py-12">
    <div class="mx-auto max-w-7xl px-4">
      <div
        class="flex flex-col items-stretch justify-between gap-6 rounded-2xl border border-surface-line bg-gradient-to-l from-slate-50 to-brand-soft/40 p-6 sm:flex-row sm:items-center sm:p-8"
      >
        <div class="text-right">
          <h2 class="mb-1 text-xl font-black text-desk-dark sm:text-2xl">
            خبرنامه جاب‌آزمون
          </h2>
          <p class="text-sm leading-7 text-desk-muted">
            از آخرین استخدام‌ها و آزمون‌ها زودتر باخبر شوید.
          </p>
          <p
            v-if="successMessage"
            class="mt-2 text-sm font-bold text-emerald-700"
            role="status"
          >
            {{ successMessage }}
          </p>
        </div>
        <form
          class="flex w-full max-w-md flex-col gap-2"
          @submit.prevent="submit"
        >
          <div class="flex flex-row-reverse gap-2">
            <button
              type="submit"
              class="shrink-0 rounded-xl bg-brand px-5 py-3 text-sm font-bold text-white transition hover:bg-brand-dark disabled:opacity-60"
              :disabled="submitting || !contact.trim()"
            >
              {{ submitting ? '...' : 'عضویت' }}
            </button>
            <input
              v-model="contact"
              type="text"
              required
              placeholder="ایمیل یا موبایل"
              class="h-12 w-full rounded-xl border bg-white px-4 text-sm text-desk-text outline-none ring-brand focus:ring-2"
              :class="fieldError ? 'border-brand' : 'border-surface-line'"
              :disabled="submitting"
              @input="fieldError = ''"
            />
          </div>
          <p v-if="fieldError" class="text-right text-xs text-brand">
            {{ fieldError }}
          </p>
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
const successMessage = ref('')
const fieldError = ref('')

async function submit() {
  if (submitting.value) return

  const value = contact.value.trim()
  if (!value) {
    fieldError.value = 'ایمیل یا موبایل الزامی است.'
    return
  }

  submitting.value = true
  successMessage.value = ''
  fieldError.value = ''

  try {
    const { data } = await api.post('/newsletter/subscribe', { contact: value })
    const msg = data?.message || 'عضویت در خبرنامه ثبت شد.'
    successMessage.value = msg
    toast.success(msg)
    contact.value = ''
  } catch (e) {
    const errors = e.response?.data?.errors
    if (errors?.contact?.[0]) {
      fieldError.value = errors.contact[0]
    } else {
      toast.error(apiErrorMessage(e, 'ثبت عضویت ناموفق بود.'))
    }
  } finally {
    submitting.value = false
  }
}
</script>
