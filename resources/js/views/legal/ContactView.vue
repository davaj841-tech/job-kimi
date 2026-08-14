<template>
  <PageShell
    :title="page?.title || 'تماس با ما'"
    subtitle="پیام شما را می‌خوانیم و در اولین فرصت پاسخ می‌دهیم."
  >
    <div class="grid gap-8 lg:grid-cols-2">
      <form
        class="space-y-3 rounded-2xl border border-surface-line bg-white p-5"
        @submit.prevent="submit"
      >
        <div>
          <label class="mb-1 block text-sm font-bold text-ink">نام</label>
          <input v-model="form.name" required class="field" placeholder="نام *" />
        </div>
        <div>
          <label class="mb-1 block text-sm font-bold text-ink" dir="ltr">Email</label>
          <input
            v-model="form.email"
            type="email"
            required
            class="field text-left"
            dir="ltr"
            lang="en"
            inputmode="email"
            autocomplete="email"
            autocapitalize="off"
            spellcheck="false"
            placeholder="Email *"
          />
        </div>
        <div>
          <label class="mb-1 block text-sm font-bold text-ink">موضوع</label>
          <select v-model="form.subject" required class="field">
            <option disabled value="">موضوع *</option>
            <option value="support">پشتیبانی</option>
            <option value="complaint">شکایت</option>
            <option value="suggestion">پیشنهاد</option>
            <option value="partnership">همکاری</option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-sm font-bold text-ink">پیام</label>
          <textarea
            v-model="form.message"
            required
            rows="5"
            class="field min-h-[120px] py-2"
            placeholder="پیام شما *"
          />
        </div>
        <p v-if="error" class="text-sm text-red-500">{{ error }}</p>
        <p v-if="success" class="text-sm text-emerald-600">{{ success }}</p>
        <p
          v-if="trackingCode"
          class="rounded-xl bg-emerald-50 px-3 py-2 text-sm font-bold text-emerald-800"
          dir="ltr"
        >
          شماره پیگیری: {{ trackingCode }}
        </p>
        <button type="submit" class="btn-primary w-full" :disabled="sending">
          {{ sending ? 'در حال ارسال...' : 'ارسال پیام' }}
        </button>
      </form>

      <div class="space-y-5">
        <div
          v-if="page?.content"
          class="prose-legal rounded-2xl border border-surface-line bg-white p-5 text-sm leading-7"
          v-html="page.content"
        />

        <div
          class="rounded-2xl border border-surface-line bg-white p-5 text-sm leading-7"
        >
          <p v-if="supportEmail">
            <strong dir="ltr">Email:</strong>
            <a :href="`mailto:${supportEmail}`" class="text-brand" dir="ltr">{{
              supportEmail
            }}</a>
          </p>
          <p v-if="supportPhone">
            <strong>تلفن:</strong>
            <a :href="`tel:${supportPhone}`" dir="ltr">{{ supportPhone }}</a>
          </p>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row">
          <a
            :href="whatsappUrl || undefined"
            :target="whatsappUrl ? '_blank' : undefined"
            :rel="whatsappUrl ? 'noopener noreferrer' : undefined"
            class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-[#25D366] py-3 text-sm font-bold text-white"
            :class="whatsappUrl ? '' : 'pointer-events-none opacity-50'"
          >
            <span aria-hidden="true" v-html="waIcon" />
            واتساپ
          </a>
          <a
            :href="instagramUrl || undefined"
            :target="instagramUrl ? '_blank' : undefined"
            :rel="instagramUrl ? 'noopener noreferrer' : undefined"
            class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-brand py-3 text-sm font-bold text-white"
            :class="instagramUrl ? '' : 'pointer-events-none opacity-50'"
          >
            <span aria-hidden="true" v-html="igIcon" />
            اینستاگرام
          </a>
        </div>
      </div>
    </div>
  </PageShell>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import PageShell from '../../components/layout/PageShell.vue'
import api from '../../api/client'
import { apiErrorMessage, unwrapItem } from '../../utils/format'
import { useSiteTheme } from '../../composables/useSiteTheme'

const form = reactive({ name: '', email: '', subject: '', message: '' })
const sending = ref(false)
const error = ref('')
const success = ref('')
const trackingCode = ref('')
const page = ref(null)
const supportEmail = ref('')
const supportPhone = ref('')
const { whatsappUrl, instagramUrl, ensureLoaded } = useSiteTheme()

const waIcon = `<svg viewBox="0 0 24 24" width="18" height="18"><path fill="#fff" d="M12 2.1A9.9 9.9 0 0 0 3.4 17L2 22l5.2-1.4A9.9 9.9 0 1 0 12 2.1Zm5.7 14.3c-.2.7-1.3 1.2-1.8 1.3-.5.1-1 .2-3.3-.7-2.7-1.1-4.5-3.8-4.6-4-.1-.2-1-1.3-1-2.5s.6-1.8.9-2c.2-.2.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 1.9c.1.2 0 .4-.1.5l-.4.5c-.2.2-.4.4-.2.7.2.4.9 1.5 2 2.4 1.3 1.1 2.4 1.4 2.7 1.6.4.2.6.1.8-.1l.6-.7c.2-.2.4-.2.7-.1l2 .9c.3.1.5.2.5.4 0 .1 0 .8-.3 1.5Z"/></svg>`
const igIcon = `<svg viewBox="0 0 24 24" width="18" height="18" fill="none"><rect x="3" y="3" width="18" height="18" rx="5" stroke="#fff" stroke-width="1.8"/><circle cx="12" cy="12" r="4" stroke="#fff" stroke-width="1.8"/><circle cx="17.2" cy="6.8" r="1" fill="#fff"/></svg>`

onMounted(async () => {
  void ensureLoaded()
  try {
    const { data } = await api.get('/settings/public')
    const s = data?.data || {}
    supportEmail.value = s.support_email || ''
    supportPhone.value = s.support_phone || ''
  } catch {
    // ignore
  }
  try {
    const { data } = await api.get('/pages/contact')
    page.value = unwrapItem(data)
  } catch {
    page.value = null
  }
})

async function submit() {
  sending.value = true
  error.value = ''
  success.value = ''
  trackingCode.value = ''
  try {
    const { data } = await api.post('/contact', { ...form })
    trackingCode.value = data?.data?.tracking_code || ''
    success.value = trackingCode.value
      ? `پیام شما ثبت شد. شماره پیگیری: ${trackingCode.value}`
      : 'پیام شما با موفقیت ارسال شد.'
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
