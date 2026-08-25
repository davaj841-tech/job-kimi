<template>
  <div class="space-y-5">
    <h1 class="text-2xl font-bold text-gray-800">تیکت‌های پشتیبانی</h1>
    <div class="rounded-xl bg-white p-4 shadow-sm">
      <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
        <select v-model="filters.status" class="field">
          <option value="">همه وضعیت‌ها</option>
          <option value="open">باز</option>
          <option value="closed">بسته</option>
        </select>
        <select v-model="filters.priority" class="field">
          <option value="">همه اولویت‌ها</option>
          <option value="low">کم</option>
          <option value="medium">متوسط</option>
          <option value="high">بالا</option>
        </select>
        <input
          v-model="filters.search"
          class="field"
          placeholder="جستجو موضوع / شماره پیگیری"
          @keyup.enter="load"
        />
      </div>
      <button class="btn-orange mt-3" @click="load">اعمال</button>
    </div>

    <DataTable :columns="columns" :rows="rows" :loading="loading" actions>
      <template #cell-index="{ index }">{{ index + 1 }}</template>
      <template #cell-user="{ row }">{{
        row.user?.name || row.user?.mobile || '—'
      }}</template>
      <template #cell-status="{ row }">{{
        row.status === 'open' ? 'باز' : 'بسته'
      }}</template>
      <template #actions="{ row }">
        <button class="act" @click="openTicket(row)">پاسخ</button>
        <button
          class="act"
          @click="setStatus(row, row.status === 'open' ? 'closed' : 'open')"
        >
          {{ row.status === 'open' ? 'بستن' : 'باز کردن' }}
        </button>
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
      <h3 class="mb-1 font-bold">{{ active.subject }}</h3>
      <p
        v-if="active.tracking_code"
        class="mb-2 text-xs font-bold text-orange-600"
        dir="ltr"
      >
        شماره پیگیری: {{ active.tracking_code }}
      </p>
      <div
        class="mb-3 flex-1 space-y-2 overflow-y-auto rounded-xl bg-slate-50 p-3 text-sm"
      >
        <p class="rounded-xl bg-white p-2 shadow-sm">{{ active.message }}</p>
        <p
          v-for="r in active.replies || []"
          :key="r.id"
          class="rounded-xl p-2"
          :class="r.is_admin ? 'bg-orange-50' : 'bg-white shadow-sm'"
        >
          {{ r.message }}
        </p>
      </div>
      <form class="flex gap-2" @submit.prevent="reply">
        <input
          v-model="replyMsg"
          class="field flex-1"
          placeholder="پاسخ ادمین"
        />
        <button class="btn-orange">ارسال</button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import adminApi from '../api/client'
import DataTable from '../components/ui/DataTable.vue'
import { unwrapList, apiErrorMessage } from '../../utils/format'
import { useToast } from '../../composables/useToast'

const toast = useToast()
const loading = ref(false)
const rows = ref([])
const active = ref(null)
const replyMsg = ref('')
const filters = reactive({ status: '', priority: '', search: '' })
const columns = [
  { key: 'index', label: '#' },
  { key: 'tracking_code', label: 'پیگیری' },
  { key: 'subject', label: 'موضوع' },
  { key: 'user', label: 'کاربر' },
  { key: 'priority', label: 'اولویت' },
  { key: 'status', label: 'وضعیت' },
]

onMounted(load)
async function load() {
  loading.value = true
  try {
    const { data } = await adminApi.get('/admin/tickets', { params: filters })
    rows.value = unwrapList(data)
  } finally {
    loading.value = false
  }
}
async function openTicket(row) {
  const { data } = await adminApi.get(`/tickets/${row.id}`)
  active.value = data.data
}
async function setStatus(row, status) {
  try {
    await adminApi.put(`/admin/tickets/${row.id}/status`, { status })
    toast.success('وضعیت به‌روز شد')
    load()
  } catch (e) {
    toast.error(apiErrorMessage(e))
  }
}
async function reply() {
  if (!active.value) return
  await adminApi.post(`/tickets/${active.value.id}/reply`, {
    message: replyMsg.value,
  })
  replyMsg.value = ''
  await openTicket(active.value)
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
