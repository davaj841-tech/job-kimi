<template>
  <div class="space-y-5">
    <h1 class="text-2xl font-bold text-gray-800">سرعت سایت</h1>
    <p class="text-sm text-slate-500">
      با بهینه‌سازی کش، قالب و گرم‌کردن API، بارگذاری سایت و اپ سریع‌تر می‌شود.
    </p>

    <div class="grid gap-4 md:grid-cols-2">
      <div class="rounded-2xl bg-white p-5 shadow-sm">
        <h2 class="mb-2 font-bold">افزایش سرعت</h2>
        <p class="mb-4 text-xs text-slate-500">
          یک‌بار کش قالب، مسیرها و داده‌های پرتکرار را آماده می‌کند.
        </p>
        <button class="btn-orange" :disabled="busy" @click="boost">
          {{ busy ? 'در حال اجرا...' : 'افزایش سرعت سایت' }}
        </button>
      </div>
      <div class="rounded-2xl bg-white p-5 shadow-sm">
        <h2 class="mb-2 font-bold">سرعت خودکار</h2>
        <p class="mb-4 text-xs text-slate-500">
          هر ساعت در پس‌زمینه بهینه‌سازی تکرار می‌شود.
        </p>
        <button class="btn-muted" :disabled="busy" @click="toggleAuto">
          {{ auto ? 'خاموش کردن سرعت خودکار' : 'فعال‌سازی سرعت خودکار' }}
        </button>
        <p
          class="mt-2 text-xs font-bold"
          :class="auto ? 'text-emerald-600' : 'text-slate-400'"
        >
          وضعیت: {{ auto ? 'فعال' : 'خاموش' }}
        </p>
      </div>
    </div>

    <p v-if="lastBoost" class="text-xs text-slate-500">
      آخرین بهینه‌سازی: {{ lastBoost }}
    </p>
    <ul v-if="log.length" class="list-disc pr-5 text-sm text-slate-600">
      <li v-for="(item, i) in log" :key="i">{{ item }}</li>
    </ul>
    <p v-if="message" class="text-sm font-bold text-emerald-700">
      {{ message }}
    </p>
    <p v-if="error" class="text-sm text-red-500">{{ error }}</p>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import adminApi from '../api/client'
import { apiErrorMessage } from '../../utils/format'

const busy = ref(false)
const auto = ref(false)
const lastBoost = ref('')
const log = ref([])
const message = ref('')
const error = ref('')

onMounted(load)

async function load() {
  try {
    const { data } = await adminApi.get('/admin/performance')
    const row = data.data || {}
    auto.value = Boolean(row.auto)
    lastBoost.value = row.last_boost_at || ''
  } catch (e) {
    error.value = apiErrorMessage(e)
  }
}

async function boost() {
  busy.value = true
  error.value = ''
  message.value = ''
  try {
    const { data } = await adminApi.post('/admin/performance/boost')
    log.value = data.data || []
    message.value = data.message || 'انجام شد'
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e)
  } finally {
    busy.value = false
  }
}

async function toggleAuto() {
  busy.value = true
  error.value = ''
  try {
    const { data } = await adminApi.post('/admin/performance/auto')
    auto.value = Boolean(data.data?.auto)
    log.value = data.data?.log || []
    message.value = data.message
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e)
  } finally {
    busy.value = false
  }
}
</script>

<style scoped>
.btn-orange {
  @apply rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50;
}
.btn-muted {
  @apply rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50;
}
</style>
