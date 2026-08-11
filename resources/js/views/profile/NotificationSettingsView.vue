<template>
  <div class="mx-auto max-w-xl px-4 py-6">
    <h1 class="mb-4 text-xl font-black">تنظیمات اعلان‌ها</h1>
    <div
      v-for="channel in channels"
      :key="channel.key"
      class="card-soft mb-4 p-4"
    >
      <h2 class="mb-3 text-sm font-bold">{{ channel.label }}</h2>
      <label
        v-for="t in types"
        :key="channel.key + t.key"
        class="mb-2 flex items-center justify-between text-sm"
      >
        <span>{{ t.label }}</span>
        <input
          v-model="prefs[channel.key][t.key]"
          type="checkbox"
          class="h-4 w-4"
        />
      </label>
    </div>
    <button class="btn-primary" :disabled="saving" @click="save">
      {{ saving ? '...' : 'ذخیره' }}
    </button>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import api from '../../api/client'
import { useToast } from '../../composables/useToast'
import { apiErrorMessage, unwrapItem } from '../../utils/format'

const toast = useToast()
const saving = ref(false)
const types = [
  { key: 'exam_completed', label: 'نتیجه آزمون' },
  { key: 'subscription_expiring', label: 'انقضای اشتراک' },
  { key: 'job_post_approved', label: 'تایید آگهی' },
  { key: 'pdf_purchased', label: 'خرید PDF' },
  { key: 'wallet_charged', label: 'شارژ کیف پول' },
  { key: 'admin_reply', label: 'پاسخ پشتیبانی' },
]
const channels = [
  { key: 'email', label: 'ایمیل' },
  { key: 'sms', label: 'پیامک' },
  { key: 'push', label: 'پوش (PWA)' },
]
const prefs = reactive({
  email: Object.fromEntries(types.map((t) => [t.key, true])),
  sms: Object.fromEntries(types.map((t) => [t.key, false])),
  push: Object.fromEntries(types.map((t) => [t.key, true])),
})

onMounted(async () => {
  const { data } = await api.get('/notification-preferences')
  const d = unwrapItem(data) || {}
  Object.assign(prefs.email, d.email || {})
  Object.assign(prefs.sms, d.sms || {})
  Object.assign(prefs.push, d.push || {})
})

async function save() {
  saving.value = true
  try {
    await api.put('/notification-preferences', prefs)
    toast.success('ذخیره شد')
  } catch (e) {
    toast.error(apiErrorMessage(e))
  } finally {
    saving.value = false
  }
}
</script>
