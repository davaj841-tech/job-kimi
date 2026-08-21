<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
  >
    <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
      <div class="mb-4 flex items-center justify-between">
        <h3 class="text-lg font-bold">ورود فایل سوالات</h3>
        <button type="button" @click="$emit('close')">✕</button>
      </div>

      <div class="mb-4">
        <label class="mb-1 block text-xs font-medium text-slate-600"
          >آزمون *</label
        >
        <select v-model="examId" required class="field">
          <option disabled value="">ابتدا آزمون را انتخاب کنید</option>
          <option v-for="e in exams" :key="e.id" :value="e.id">
            {{ e.title }}
          </option>
        </select>
      </div>

      <div class="mb-4 flex flex-wrap gap-2">
        <button
          type="button"
          class="rounded-xl bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700"
          :disabled="downloading"
          @click="downloadSample('xlsx')"
        >
          {{ downloading === 'xlsx' ? '...' : 'دانلود نمونه XLSX' }}
        </button>
        <button
          type="button"
          class="rounded-xl bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700"
          :disabled="downloading"
          @click="downloadSample('csv')"
        >
          {{ downloading === 'csv' ? '...' : 'دانلود نمونه CSV' }}
        </button>
      </div>

      <p class="mb-3 text-sm leading-6 text-slate-500">
        ۱) آزمون را انتخاب کنید ۲) نمونه را دانلود و پر کنید ۳) فایل را آپلود کنید.
        ستون‌های فارسی مجاز: نام‌آزمون، درس، متن‌سوال، گزینه‌ها، پاسخ‌صحیح، توضیحات، سطح، سال، منبع.
        پاسخ: الف/ب/ج/د — سطح خالی = متوسط.
      </p>

      <input
        type="file"
        accept=".xlsx,.xls,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
        class="mb-4 block w-full text-sm"
        @change="onFile"
      />

      <div
        v-if="previewRows.length"
        class="mb-4 overflow-x-auto rounded-xl border border-slate-100"
      >
        <table class="min-w-full text-xs">
          <thead class="bg-slate-50">
            <tr>
              <th
                v-for="h in previewHeaders"
                :key="h"
                class="px-2 py-2 text-right"
              >
                {{ h }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, i) in previewRows" :key="i" class="border-t">
              <td v-for="h in previewHeaders" :key="h" class="px-2 py-2">
                {{ row[h] }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="result" class="mb-4 rounded-xl bg-slate-50 p-3 text-sm">
        <p>موفق: {{ result.created ?? result.success ?? result.imported ?? '—' }}</p>
        <p>رد شده: {{ result.skipped ?? result.failed ?? 0 }}</p>
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
          :disabled="!file || !examId || loading"
          @click="importFile"
        >
          {{ loading ? 'در حال ورود...' : 'شروع ورود' }}
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
  exams: { type: Array, default: () => [] },
})
const emit = defineEmits(['close', 'imported'])

const examId = ref('')
const file = ref(null)
const loading = ref(false)
const downloading = ref(false)
const error = ref('')
const result = ref(null)
const previewHeaders = ref([])
const previewRows = ref([])

watch(
  () => props.open,
  (v) => {
    if (!v) return
    examId.value = ''
    file.value = null
    error.value = ''
    result.value = null
    previewHeaders.value = []
    previewRows.value = []
  }
)

function onFile(e) {
  file.value = e.target.files?.[0] || null
  result.value = null
  previewHeaders.value = []
  previewRows.value = []
  const name = (file.value?.name || '').toLowerCase()
  if (name.endsWith('.csv')) {
    const reader = new FileReader()
    reader.onload = () => {
      const lines = String(reader.result || '')
        .replace(/^\uFEFF/, '')
        .split(/\r?\n/)
        .filter(Boolean)
        .slice(0, 6)
      if (!lines.length) return
      const headers = lines[0].split(',').map((h) => h.trim().replace(/^"|"$/g, ''))
      previewHeaders.value = headers
      previewRows.value = lines.slice(1, 6).map((line) => {
        const cols = line.split(',').map((c) => c.trim().replace(/^"|"$/g, ''))
        const obj = {}
        headers.forEach((h, i) => {
          obj[h] = (cols[i] || '').trim()
        })
        return obj
      })
    }
    reader.readAsText(file.value)
  }
}

async function downloadSample(format) {
  downloading.value = format
  error.value = ''
  try {
    const response = await adminApi.get('/admin/questions/import-sample', {
      params: { format },
      responseType: 'blob',
    })
    const url = URL.createObjectURL(new Blob([response.data]))
    const a = document.createElement('a')
    a.href = url
    a.download =
      format === 'csv'
        ? 'questions-import-sample.csv'
        : 'questions-import-sample.xlsx'
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    error.value = e.response?.data?.message || 'دانلود نمونه ناموفق بود.'
  } finally {
    downloading.value = false
  }
}

async function importFile() {
  if (!file.value || !examId.value) {
    error.value = 'آزمون و فایل الزامی است.'
    return
  }
  const name = (file.value.name || '').toLowerCase()
  if (!/\.(xlsx|xls|csv)$/.test(name)) {
    error.value = 'فقط فایل‌های xlsx یا csv مجاز هستند.'
    return
  }
  loading.value = true
  error.value = ''
  try {
    emit('imported', { file: file.value, exam_id: Number(examId.value) })
  } catch (e) {
    error.value = e.response?.data?.message || 'ورود ناموفق بود.'
  } finally {
    loading.value = false
  }
}

defineExpose({
  setResult(payload) {
    result.value = payload
  },
  setError(msg) {
    error.value = msg
  },
})
</script>

<style scoped>
.field {
  @apply h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-orange-400;
}
</style>
