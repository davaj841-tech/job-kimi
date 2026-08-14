<template>
  <AdminLayout>
    <div class="space-y-5">
      <h1 class="text-2xl font-bold text-gray-800">پیام‌های تماس با ما</h1>
      <div class="rounded-xl bg-white p-4 shadow-sm">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
          <select v-model="filters.status" class="field">
            <option value="">همه وضعیت‌ها</option>
            <option value="open">باز</option>
            <option value="replied">پاسخ‌داده‌شده</option>
          </select>
          <input
            v-model="filters.search"
            class="field"
            placeholder="جستجو نام، ایمیل یا شماره پیگیری"
            @keyup.enter="load"
          />
          <button class="btn-orange" @click="load">اعمال</button>
        </div>
      </div>

      <DataTable :columns="columns" :rows="rows" :loading="loading" actions>
        <template #cell-index="{ index }">{{ index + 1 }}</template>
        <template #cell-subject="{ row }">{{ subjectLabel(row.subject) }}</template>
        <template #cell-status="{ row }">{{
          row.status === 'replied' ? 'پاسخ‌داده‌شده' : 'باز'
        }}</template>
        <template #actions="{ row }">
          <button class="act" @click="openRow(row)">مشاهده / پاسخ</button>
        </template>
      </DataTable>
    </div>

    <div
      v-if="active"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    >
      <div
        class="flex max-h-[90vh] w-full max-w-xl flex-col rounded-2xl bg-white p-5 shadow-xl"
      >
        <div class="mb-3 flex items-start justify-between gap-2">
          <div>
            <h3 class="font-bold">{{ subjectLabel(active.subject) }}</h3>
            <p class="mt-1 text-xs text-slate-500" dir="ltr">
              {{ active.tracking_code }}
            </p>
          </div>
          <button class="act" @click="active = null">بستن</button>
        </div>
        <div class="mb-3 space-y-1 text-sm">
          <p><strong>نام:</strong> {{ active.name }}</p>
          <p dir="ltr"><strong>Email:</strong> {{ active.email }}</p>
        </div>
        <div
          class="mb-3 flex-1 space-y-2 overflow-y-auto rounded-xl bg-slate-50 p-3 text-sm"
        >
          <p class="rounded-xl bg-white p-2 shadow-sm whitespace-pre-wrap">
            {{ active.message }}
          </p>
          <p
            v-if="active.reply"
            class="rounded-xl bg-orange-50 p-2 whitespace-pre-wrap"
          >
            {{ active.reply }}
          </p>
        </div>
        <form class="flex gap-2" @submit.prevent="reply">
          <textarea
            v-model="replyMsg"
            class="field min-h-[72px] flex-1 py-2"
            placeholder="پاسخ ادمین"
            required
          />
          <button class="btn-orange self-end">ارسال</button>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import adminApi from '../api/client'
import AdminLayout from '../components/layout/AdminLayout.vue'
import DataTable from '../components/ui/DataTable.vue'
import { unwrapList, apiErrorMessage } from '../../utils/format'
import { useToast } from '../../composables/useToast'

const toast = useToast()
const loading = ref(false)
const rows = ref([])
const active = ref(null)
const replyMsg = ref('')
const filters = reactive({ status: '', search: '' })
const columns = [
  { key: 'index', label: '#' },
  { key: 'tracking_code', label: 'پیگیری' },
  { key: 'name', label: 'نام' },
  { key: 'email', label: 'Email' },
  { key: 'subject', label: 'موضوع' },
  { key: 'status', label: 'وضعیت' },
]

const labels = {
  support: 'پشتیبانی',
  complaint: 'شکایت',
  suggestion: 'پیشنهاد',
  partnership: 'همکاری',
}

function subjectLabel(key) {
  return labels[key] || key
}

onMounted(load)
async function load() {
  loading.value = true
  try {
    const { data } = await adminApi.get('/admin/contact-messages', {
      params: filters,
    })
    rows.value = unwrapList(data)
  } finally {
    loading.value = false
  }
}
async function openRow(row) {
  const { data } = await adminApi.get(`/admin/contact-messages/${row.id}`)
  active.value = data.data
  replyMsg.value = ''
}
async function reply() {
  if (!active.value) return
  try {
    await adminApi.post(`/admin/contact-messages/${active.value.id}/reply`, {
      reply: replyMsg.value,
    })
    toast.success('پاسخ ارسال شد')
    replyMsg.value = ''
    await openRow(active.value)
    load()
  } catch (e) {
    toast.error(apiErrorMessage(e))
  }
}
</script>

<style scoped>
.field {
  @apply h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-orange-400;
}
.btn-orange {
  @apply rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-bold text-white;
}
.act {
  @apply rounded-lg px-2 py-1 text-xs font-bold text-slate-600 hover:bg-slate-100;
}
</style>
