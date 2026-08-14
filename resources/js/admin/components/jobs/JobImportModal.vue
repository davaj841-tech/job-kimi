<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
  >
    <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
      <div class="mb-4 flex items-center justify-between">
        <h3 class="text-lg font-bold">ورود اکسل آگهی‌ها</h3>
        <button type="button" @click="$emit('close')">✕</button>
      </div>

      <div
        class="mb-4 rounded-xl border border-orange-100 bg-orange-50 p-3 text-sm leading-6 text-slate-700"
      >
        <p class="mb-2 font-bold text-orange-700">نمونه فایل XLSX</p>
        <p class="mb-3 text-xs text-slate-600">
          ستون‌ها: عنوان، برچسب سئو، طبقه‌بندی، شرح، استان‌ها، شهر،
          مهلت ثبت‌نام، تاریخ آزمون، لینک ثبت‌نام، ویژه
        </p>
        <button
          type="button"
          class="inline-flex rounded-lg bg-orange-500 px-3 py-2 text-xs font-bold text-white disabled:opacity-50"
          :disabled="sampleLoading"
          @click="downloadSample"
        >
          {{ sampleLoading ? '...' : 'دانلود نمونه Excel (xlsx)' }}
        </button>
        <p v-if="sampleError" class="mt-2 text-xs text-red-500">
          {{ sampleError }}
        </p>
      </div>

      <FileUploader
        v-model="file"
        accept=".xlsx,.xls,.csv"
        label="آپلود Excel"
        hint="ترجیحاً xlsx — حداکثر ۲۰MB"
      />
      <div v-if="result" class="mt-3 rounded-xl bg-slate-50 p-3 text-sm">
        <p>ایجاد: {{ result.created ?? 0 }}</p>
        <p>رد شده: {{ result.skipped ?? 0 }}</p>
        <ul
          v-if="result.errors?.length"
          class="mt-2 max-h-28 overflow-y-auto text-xs text-red-600"
        >
          <li v-for="(e, i) in result.errors" :key="i">{{ e }}</li>
        </ul>
      </div>
      <p v-if="error" class="mt-2 text-sm text-red-500">{{ error }}</p>
      <div class="mt-4 flex justify-end gap-2">
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
          @click="run"
        >
          {{ loading ? '...' : 'شروع ورود' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import FileUploader from '../ui/FileUploader.vue'
import adminApi from '../../api/client'

defineProps({ open: Boolean })
const emit = defineEmits(['close', 'imported'])

const file = ref(null)
const loading = ref(false)
const error = ref('')
const result = ref(null)
const sampleLoading = ref(false)
const sampleError = ref('')

watch(file, () => {
  result.value = null
  error.value = ''
})

async function downloadSample() {
  sampleLoading.value = true
  sampleError.value = ''
  try {
    const { data } = await adminApi.get('/admin/job-posts/import-sample', {
      responseType: 'blob',
    })
    const url = URL.createObjectURL(data)
    const a = document.createElement('a')
    a.href = url
    a.download = 'job-posts-import-sample.xlsx'
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(url)
  } catch (e) {
    sampleError.value = e.response?.data?.message || 'دانلود نمونه ناموفق بود.'
  } finally {
    sampleLoading.value = false
  }
}

function run() {
  if (!file.value) return
  loading.value = true
  emit('imported', file.value)
}

defineExpose({
  setResult(r) {
    result.value = r
    loading.value = false
  },
  setError(msg) {
    error.value = msg
    loading.value = false
  },
})
</script>
