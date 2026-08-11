<template>
  <AdminLayout>
    <div class="space-y-5">
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 class="text-2xl font-bold">گزارش حسابرسی</h1>
          <p class="mt-1 text-sm text-slate-500">
            کارهای اپراتور و مدیر — سوال، فایل، مطلب و سایر عملیات
          </p>
        </div>
        <button class="btn-orange" @click="loadReport">بروزرسانی گزارش</button>
      </div>

      <div
        class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6"
      >
        <div
          v-for="card in highlightCards"
          :key="card.key"
          class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm"
        >
          <p class="text-xs text-slate-500">{{ card.label }}</p>
          <p class="mt-1 text-xl font-black text-slate-800">
            {{ fa(report.highlights?.[card.key] || 0) }}
          </p>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-5">
        <select v-model="filters.user_id" class="field">
          <option value="">همه کاربران</option>
          <option
            v-for="u in report.operators || []"
            :key="u.id"
            :value="u.id"
          >
            {{ u.name || u.mobile }} ({{ u.role }})
          </option>
        </select>
        <input v-model="filters.action" class="field" placeholder="عملیات" />
        <input
          v-model="filters.entity_type"
          class="field"
          placeholder="موجودیت"
        />
        <input v-model="filters.date_from" type="date" class="field" />
        <input v-model="filters.date_to" type="date" class="field" />
      </div>
      <div class="flex flex-wrap gap-2">
        <button class="btn-orange" @click="applyFilters">اعمال فیلتر</button>
        <button
          class="rounded-xl bg-red-50 px-4 py-2.5 text-sm font-bold text-red-600"
          @click="deleteRange"
        >
          حذف گزارش بازه (فقط مدیر)
        </button>
      </div>

      <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl bg-white p-4 shadow-sm">
          <h3 class="mb-3 font-bold">بر اساس عملیات</h3>
          <ul class="max-h-64 space-y-2 overflow-y-auto text-sm">
            <li
              v-for="row in report.by_action || []"
              :key="row.action"
              class="flex justify-between gap-2 border-b border-slate-50 pb-1"
            >
              <span>{{ row.label || row.action }}</span>
              <span class="font-bold">{{ fa(row.total) }}</span>
            </li>
            <li
              v-if="!(report.by_action || []).length"
              class="text-slate-400"
            >
              داده‌ای نیست
            </li>
          </ul>
        </div>
        <div class="rounded-2xl bg-white p-4 shadow-sm">
          <h3 class="mb-3 font-bold">بر اساس اپراتور</h3>
          <ul class="max-h-64 space-y-2 overflow-y-auto text-sm">
            <li
              v-for="row in report.by_user || []"
              :key="row.user_id"
              class="flex justify-between gap-2 border-b border-slate-50 pb-1"
            >
              <span>{{ row.name }} <span class="text-xs text-slate-400">{{ row.role }}</span></span>
              <span class="font-bold">{{ fa(row.total) }}</span>
            </li>
            <li v-if="!(report.by_user || []).length" class="text-slate-400">
              داده‌ای نیست
            </li>
          </ul>
        </div>
      </div>

      <DataTable :columns="columns" :rows="rows" :loading="loading" actions>
        <template #cell-created_at="{ row }">{{
          formatDateTime(row.created_at)
        }}</template>
        <template #cell-user="{ row }">{{
          row.user?.name || row.user?.mobile || row.user_id || '—'
        }}</template>
        <template #cell-entity="{ row }"
          >{{ shortEntity(row.entity_type) }} #{{
            row.entity_id || '—'
          }}</template
        >
        <template #actions="{ row }">
          <button class="act" @click="open(row)">جزئیات</button>
        </template>
      </DataTable>
      <PaginationBar :meta="meta" @page="load" />
    </div>

    <div
      v-if="selected"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    >
      <div
        class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-5"
      >
        <h3 class="mb-3 font-bold">جزئیات تغییر</h3>
        <p class="mb-2 text-sm">
          <span class="text-slate-500">عملیات:</span> {{ selected.action }}
        </p>
        <div class="grid gap-3 md:grid-cols-2">
          <div>
            <p class="mb-1 text-xs font-bold text-slate-500">مقادیر قبلی</p>
            <pre
              class="overflow-auto rounded-xl bg-slate-50 p-3 text-xs"
              dir="ltr"
              >{{ pretty(selected.old_values) }}</pre
            >
          </div>
          <div>
            <p class="mb-1 text-xs font-bold text-slate-500">مقادیر جدید</p>
            <pre
              class="overflow-auto rounded-xl bg-slate-50 p-3 text-xs"
              dir="ltr"
              >{{ pretty(selected.new_values) }}</pre
            >
          </div>
        </div>
        <div class="mt-4 text-left">
          <button class="btn-muted" @click="selected = null">بستن</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import adminApi from '../api/client'
import AdminLayout from '../components/layout/AdminLayout.vue'
import DataTable from '../components/ui/DataTable.vue'
import PaginationBar from '../components/ui/PaginationBar.vue'
import { useToast } from '../../composables/useToast'
import { formatDateTime, unwrapList, unwrapMeta } from '../../utils/format'

const toast = useToast()
const rows = ref([])
const meta = ref(null)
const loading = ref(false)
const selected = ref(null)
const report = ref({ highlights: {}, by_action: [], by_user: [], operators: [] })
const filters = reactive({
  user_id: '',
  action: '',
  entity_type: '',
  date_from: '',
  date_to: '',
})
const columns = [
  { key: 'created_at', label: 'زمان' },
  { key: 'user', label: 'کاربر' },
  { key: 'ip_address', label: 'IP' },
  { key: 'action', label: 'عملیات' },
  { key: 'entity', label: 'موجودیت' },
]

const highlightCards = [
  { key: 'questions_created', label: 'سوال ایجادشده' },
  { key: 'questions_imported', label: 'سوال واردشده' },
  { key: 'pdfs_published', label: 'فایل PDF' },
  { key: 'blog_posts', label: 'مطالب' },
  { key: 'exams_managed', label: 'آزمون‌ها' },
  { key: 'jobs_managed', label: 'آگهی‌ها' },
]

onMounted(async () => {
  await loadReport()
  await load(1)
})

function fa(n) {
  return Number(n || 0).toLocaleString('fa-IR')
}

async function loadReport() {
  try {
    const { data } = await adminApi.get('/admin/audit-logs/report', {
      params: {
        date_from: filters.date_from || undefined,
        date_to: filters.date_to || undefined,
        user_id: filters.user_id || undefined,
      },
    })
    report.value = data.data || report.value
  } catch (e) {
    toast.error(e.response?.data?.message || 'بارگذاری گزارش ناموفق بود.')
  }
}

async function applyFilters() {
  await loadReport()
  await load(1)
}

async function load(page = 1) {
  loading.value = true
  try {
    const { data } = await adminApi.get('/admin/audit-logs', {
      params: { ...filters, page, per_page: 20 },
    })
    rows.value = unwrapList(data)
    meta.value = unwrapMeta(data)
  } finally {
    loading.value = false
  }
}

async function deleteRange() {
  if (!filters.date_from || !filters.date_to) {
    toast.error('بازه تاریخ را مشخص کنید.')
    return
  }
  if (
    !window.confirm(
      `گزارش‌های ${filters.date_from} تا ${filters.date_to} حذف شوند؟`
    )
  )
    return
  try {
    const { data } = await adminApi.delete('/admin/audit-logs', {
      data: {
        date_from: filters.date_from,
        date_to: filters.date_to,
        user_id: filters.user_id || undefined,
      },
    })
    toast.success(data.message || 'حذف شد.')
    await applyFilters()
  } catch (e) {
    toast.error(e.response?.data?.message || 'حذف ناموفق بود.')
  }
}

function open(row) {
  selected.value = row
}
function pretty(v) {
  return JSON.stringify(v ?? {}, null, 2)
}
function shortEntity(t) {
  if (!t) return '—'
  return String(t).split('\\').pop()
}
</script>

<style scoped>
.field {
  @apply h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none;
}
.btn-orange {
  @apply rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-bold text-white;
}
.btn-muted {
  @apply rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold;
}
.act {
  @apply rounded-lg px-2 py-1 text-xs font-bold text-slate-600 hover:bg-slate-100;
}
</style>
