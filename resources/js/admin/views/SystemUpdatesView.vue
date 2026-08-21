<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold">به‌روزرسانی سیستم</h1>
        <p class="mt-1 text-sm text-slate-500">
          آپلود بسته ZIP رسمی JobAzmoon — اعتبارسنجی، بکاپ، نصب، مهاجرت و Health Check به‌صورت امن.
        </p>
      </div>
      <div class="rounded-xl bg-slate-900 px-4 py-3 text-white">
        <p class="text-xs text-slate-300">نسخه فعلی</p>
        <p class="text-xl font-black tracking-wide">v{{ status.current_version || '—' }}</p>
      </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
      <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-bold">آپلود بسته به‌روزرسانی</h2>
        <p class="mt-1 text-sm text-slate-500">فقط فایل‌های jobazmoon-update-vX.Y.Z.zip</p>
        <input
          ref="fileInput"
          type="file"
          accept=".zip,application/zip"
          class="mt-4 block w-full text-sm"
          @change="onFile"
        />
        <div class="mt-4 flex flex-wrap gap-2">
          <button class="btn-dark" :disabled="!file || busy" @click="validatePack">بررسی اولیه</button>
          <button class="btn-orange" :disabled="!file || !preflightOk || busy" @click="installPack">
            نصب به‌روزرسانی
          </button>
        </div>
        <p v-if="message" class="mt-3 text-sm text-slate-600">{{ message }}</p>
      </section>

      <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-bold">اطلاعات بسته / Preflight</h2>
        <div v-if="info" class="mt-3 space-y-2 text-sm">
          <p><span class="text-slate-500">نسخه جدید:</span> <strong>v{{ info.target_version }}</strong></p>
          <p><span class="text-slate-500">از نسخه:</span> v{{ info.current_version }}</p>
          <p v-if="info.manifest?.description"><span class="text-slate-500">توضیح:</span> {{ info.manifest.description }}</p>
          <p v-if="info.manifest?.release_date"><span class="text-slate-500">تاریخ:</span> {{ info.manifest.release_date }}</p>
          <p><span class="text-slate-500">PHP:</span> {{ info.manifest?.php }}</p>
          <p><span class="text-slate-500">Laravel:</span> {{ info.manifest?.laravel }}</p>
          <p><span class="text-slate-500">فایل‌ها / حذف / مهاجرت:</span>
            {{ info.files_count }} / {{ info.deleted_count }} / {{ info.migrations_count }}
          </p>
        </div>
        <ul v-if="preflightEntries.length" class="mt-4 grid grid-cols-2 gap-2 text-sm">
          <li
            v-for="row in preflightEntries"
            :key="row.key"
            class="rounded-lg px-3 py-2"
            :class="row.ok ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-700'"
          >
            {{ row.ok ? '✓' : '✗' }} {{ row.label }}
          </li>
        </ul>
        <p v-else class="mt-3 text-sm text-slate-400">پس از انتخاب ZIP، بررسی اولیه را اجرا کنید.</p>
      </section>
    </div>

    <section class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
      <p class="font-bold">نکته مهم دربارهٔ Database Rollback</p>
      <p class="mt-1 leading-6">
        بازگردانی دیتابیس با <code class="rounded bg-white px-1">migrate:rollback</code> انجام نمی‌شود.
        در صورت شکست نصب، سیستم از <strong>بکاپ کامل/SQL</strong> گرفته‌شده قبل از Update برای Restore استفاده می‌کند.
        اگر بکاپ DB در دسترس نباشد، وضعیت Rollback به‌صورت <strong>ناقص (PARTIAL)</strong> ثبت می‌شود و نباید فرض کنید دیتابیس کامل برگشته است.
      </p>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-3 flex items-center justify-between gap-2">
        <h2 class="text-lg font-bold">تاریخچه به‌روزرسانی</h2>
        <button class="act" :disabled="loading" @click="load">تازه‌سازی</button>
      </div>
      <DataTable :columns="columns" :rows="rows" :loading="loading" actions>
        <template #cell-version="{ row }">v{{ row.version }}</template>
        <template #cell-previous_version="{ row }">v{{ row.previous_version || '—' }}</template>
        <template #cell-status="{ row }">
          <span :class="statusClass(row.status)">{{ row.status }}</span>
          <span
            v-if="row.status === 'ROLLED_BACK' && row.rollback_complete === false"
            class="mr-1 text-xs text-amber-700"
          >(DB/فایل ناقص)</span>
        </template>
        <template #cell-started_at="{ row }">{{ formatDateTime(row.started_at) }}</template>
        <template #actions="{ row }">
          <button
            v-if="row.status === 'FAILED' || row.status === 'COMPLETED'"
            class="act text-amber-700"
            @click="rollback(row)"
          >
            Rollback
          </button>
        </template>
      </DataTable>
    </section>

    <section v-if="status.health" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 class="text-lg font-bold">Health</h2>
      <p class="mt-1 text-sm" :class="status.health.ok ? 'text-emerald-700' : 'text-red-600'">
        {{ status.health.ok ? 'سالم' : 'ناسالم' }}
      </p>
      <ul class="mt-3 grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
        <li v-for="(v, k) in status.health.checks || {}" :key="k" class="rounded-lg bg-slate-50 px-3 py-2">
          {{ k }}: <strong>{{ v }}</strong>
        </li>
      </ul>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import adminApi from '../api/client'
import DataTable from '../components/ui/DataTable.vue'
import { formatDateTime, apiErrorMessage, unwrapList } from '../../utils/format'
import { useToast } from '../../composables/useToast'

const toast = useToast()
const fileInput = ref(null)
const file = ref(null)
const busy = ref(false)
const loading = ref(false)
const message = ref('')
const info = ref(null)
const status = ref({ current_version: '', health: null })
const rows = ref([])

const columns = [
  { key: 'version', label: 'نسخه' },
  { key: 'previous_version', label: 'قبل' },
  { key: 'status', label: 'وضعیت' },
  { key: 'started_at', label: 'شروع' },
  { key: 'duration_ms', label: 'ms' },
]

const labels = {
  package: 'Package',
  manifest: 'Manifest',
  version: 'Version',
  php: 'PHP',
  laravel: 'Laravel',
  permissions: 'Permissions',
  files: 'Files',
  checksum: 'Checksum',
  backup: 'Backup',
  database: 'Database',
}

const preflightEntries = computed(() => {
  const pf = info.value?.preflight || {}
  return Object.keys(labels).map((key) => ({
    key,
    label: labels[key],
    ok: Boolean(pf[key]),
  }))
})

const preflightOk = computed(() => {
  if (!info.value?.preflight) return false
  return preflightEntries.value.every((r) => r.ok)
})

onMounted(load)

async function load() {
  loading.value = true
  try {
    const [st, hist] = await Promise.all([
      adminApi.get('/admin/system-updates/status'),
      adminApi.get('/admin/system-updates/history'),
    ])
    status.value = st.data?.data || st.data || {}
    rows.value = unwrapList(hist.data)
  } catch (e) {
    toast.error(apiErrorMessage(e))
  } finally {
    loading.value = false
  }
}

function onFile(e) {
  file.value = e.target.files?.[0] || null
  info.value = null
  message.value = ''
}

function formData() {
  const fd = new FormData()
  fd.append('file', file.value)
  return fd
}

async function validatePack() {
  if (!file.value) return
  busy.value = true
  message.value = ''
  try {
    const { data } = await adminApi.post('/admin/system-updates/validate', formData(), {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    info.value = data.data || data
    message.value = data.message || 'بسته معتبر است.'
    toast.success(message.value)
  } catch (e) {
    info.value = null
    message.value = apiErrorMessage(e)
    toast.error(message.value)
  } finally {
    busy.value = false
  }
}

async function installPack() {
  if (!file.value || !preflightOk.value) return
  if (!confirm('نصب به‌روزرسانی شروع شود؟ قبل از نصب بکاپ گرفته می‌شود.')) return
  busy.value = true
  message.value = ''
  try {
    const { data } = await adminApi.post('/admin/system-updates/install', formData(), {
      headers: { 'Content-Type': 'multipart/form-data' },
      timeout: 600000,
    })
    message.value = data.message || 'نصب موفق'
    toast.success(message.value)
    file.value = null
    if (fileInput.value) fileInput.value.value = ''
    info.value = null
    await load()
  } catch (e) {
    message.value = apiErrorMessage(e)
    toast.error(message.value)
    await load()
  } finally {
    busy.value = false
  }
}

async function rollback(row) {
  if (!confirm(`Rollback نسخه v${row.version}؟`)) return
  try {
    await adminApi.post(`/admin/system-updates/${row.id}/rollback`)
    toast.success('Rollback انجام شد')
    await load()
  } catch (e) {
    toast.error(apiErrorMessage(e))
  }
}

function statusClass(s) {
  if (s === 'COMPLETED') return 'text-emerald-700 font-bold'
  if (s === 'FAILED' || s === 'ROLLED_BACK') return 'text-red-600 font-bold'
  return 'text-slate-700'
}
</script>
