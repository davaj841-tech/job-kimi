<template>
  <AdminLayout>
    <div class="space-y-5">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-2xl font-bold">بکاپ‌ها</h1>
          <p class="mt-1 text-sm text-slate-500">
            بکاپ خودکار هر روز ساعت ۳ صبح
          </p>
        </div>
        <button class="btn-dark" :disabled="creating" @click="create">
          بکاپ دستی
        </button>
      </div>

      <DataTable :columns="columns" :rows="rows" :loading="loading" actions>
        <template #cell-index="{ index }">{{ index + 1 }}</template>
        <template #cell-size="{ row }">{{ row.size_human }}</template>
        <template #cell-date="{ row }">{{ formatDateTime(row.date) }}</template>
        <template #actions="{ row }">
          <button class="act" @click="download(row)">دانلود</button>
          <button class="act text-red-600" @click="remove(row)">حذف</button>
        </template>
      </DataTable>
      <p v-if="message" class="text-sm text-slate-600">{{ message }}</p>
    </div>
  </AdminLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import adminApi from '../api/client'
import AdminLayout from '../components/layout/AdminLayout.vue'
import DataTable from '../components/ui/DataTable.vue'
import { formatDateTime, apiErrorMessage, unwrapList } from '../../utils/format'
import { useToast } from '../../composables/useToast'

const toast = useToast()
const rows = ref([])
const loading = ref(false)
const creating = ref(false)
const message = ref('')
const columns = [
  { key: 'index', label: '#' },
  { key: 'path', label: 'نام فایل' },
  { key: 'size', label: 'حجم' },
  { key: 'date', label: 'تاریخ' },
]

onMounted(load)

async function load() {
  loading.value = true
  try {
    const { data } = await adminApi.get('/admin/backups')
    rows.value = unwrapList(data)
  } finally {
    loading.value = false
  }
}

async function create() {
  creating.value = true
  message.value = ''
  try {
    const { data } = await adminApi.post('/admin/backups')
    message.value = data.message || 'بکاپ در صف قرار گرفت.'
    toast.success(message.value)
    setTimeout(load, 2000)
  } catch (e) {
    toast.error(apiErrorMessage(e))
  } finally {
    creating.value = false
  }
}

async function download(row) {
  try {
    const { data } = await adminApi.get('/admin/backups/download', {
      params: { path: row.path },
      responseType: 'blob',
    })
    const url = URL.createObjectURL(data)
    const a = document.createElement('a')
    a.href = url
    a.download = row.path
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    toast.error(apiErrorMessage(e))
  }
}

async function remove(row) {
  if (!confirm('حذف شود؟')) return
  await adminApi.delete('/admin/backups', { params: { path: row.path } })
  load()
}
</script>

<style scoped>
.btn-dark {
  @apply rounded-xl bg-[#0f2744] px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50;
}
.act {
  @apply rounded-lg px-2 py-1 text-xs font-bold text-slate-600 hover:bg-slate-100;
}
</style>
