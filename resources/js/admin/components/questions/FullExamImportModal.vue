<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
  >
    <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
      <div class="mb-4 flex items-center justify-between">
        <h3 class="text-lg font-bold">ورود آزمون کامل (اکسل)</h3>
        <button type="button" @click="$emit('close')">✕</button>
      </div>

      <div class="mb-4">
        <button
          type="button"
          class="rounded-xl bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-800"
          :disabled="!!downloading"
          @click="downloadSample"
        >
          {{ downloading ? '...' : 'دانلود فایل نمونه XLSX' }}
        </button>
      </div>

      <p class="mb-3 text-sm leading-6 text-slate-500">
        این ورود جدا از «ورود Excel» سوالات است. فایل نمونه دو شیت دارد:
        <strong>آزمون</strong> (نام، مدت، نمره، …) و
        <strong>سوالات</strong> (درس، متن، گزینه‌ها، پاسخ). آزمون جدید ساخته
        می‌شود و سوالات به آن اضافه می‌شوند — نیازی به انتخاب آزمون قبلی نیست.
      </p>

      <input
        type="file"
        accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
        class="mb-4 block w-full text-sm"
        @change="onFile"
      />

      <div v-if="result" class="mb-4 rounded-xl bg-slate-50 p-3 text-sm">
        <p v-if="result.exam" class="font-bold text-slate-800">
          آزمون: {{ result.exam.title }}
          <span class="font-normal text-slate-500"
            >(شناسه {{ result.exam.id }})</span
          >
        </p>
        <p>
          سوالات واردشده:
          {{ result.created ?? result.success ?? result.imported ?? '—' }}
        </p>
        <p>رد شده: {{ result.skipped ?? result.failed ?? 0 }}</p>
        <p v-if="(result.duplicates ?? 0) > 0" class="text-amber-600">
          تکراری: {{ result.duplicates }}
        </p>
        <ul
          v-if="result.errors?.length"
          class="mt-2 max-h-28 overflow-y-auto text-xs text-red-500"
        >
          <li v-for="(err, i) in result.errors.slice(0, 15)" :key="i">
            {{ err }}
          </li>
        </ul>
      </div>

      <p v-if="error" class="mb-3 text-sm text-red-500">{{ error }}</p>

      <div class="flex justify-end gap-2">
        <button
          type="button"
          class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-bold"
          @click="$emit('close')"
        >
          بستن
        </button>
        <button
          type="button"
          class="rounded-xl bg-orange-500 px-4 py-2 text-sm font-bold text-white disabled:opacity-50"
          :disabled="!file || loading"
          @click="importFile"
        >
          {{ loading ? 'در حال ورود...' : 'شروع ورود آزمون' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import adminApi from '../../api/client'

const props = defineProps({
  open: Boolean,
})
const emit = defineEmits(['close', 'imported'])

const file = ref(null)
const loading = ref(false)
const downloading = ref(false)
const error = ref('')
const result = ref(null)

watch(
  () => props.open,
  (v) => {
    if (!v) return
    file.value = null
    error.value = ''
    result.value = null
    loading.value = false
  }
)

function onFile(e) {
  file.value = e.target.files?.[0] || null
  result.value = null
  error.value = ''
}

async function downloadSample() {
  downloading.value = true
  error.value = ''
  try {
    const response = await adminApi.get('/admin/questions/import-exam-sample', {
      responseType: 'blob',
    })
    const url = URL.createObjectURL(new Blob([response.data]))
    const a = document.createElement('a')
    a.href = url
    a.download = 'exam-full-import-sample.xlsx'
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    error.value = e.response?.data?.message || 'دانلود نمونه ناموفق بود.'
  } finally {
    downloading.value = false
  }
}

function importFile() {
  if (!file.value) {
    error.value = 'فایل الزامی است.'
    return
  }
  const name = (file.value.name || '').toLowerCase()
  if (!/\.(xlsx|xls|csv)$/.test(name)) {
    error.value = 'فقط فایل‌های xlsx یا csv مجاز هستند.'
    return
  }
  loading.value = true
  error.value = ''
  emit('imported', { file: file.value })
}

defineExpose({
  setResult(payload) {
    result.value = payload
    loading.value = false
  },
  setError(msg) {
    error.value = msg
    loading.value = false
  },
  setLoading(v) {
    loading.value = !!v
  },
})
</script>
