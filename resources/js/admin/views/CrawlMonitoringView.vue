<template>
  <AdminLayout>
    <div class="space-y-5">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-2xl font-bold text-gray-800">پایش خزش</h1>
          <p class="mt-1 text-sm text-slate-500">
            اجراها و خطاهای خزنده منابع رسمی
          </p>
        </div>
        <button
          type="button"
          class="rounded-xl bg-red-50 px-4 py-2 text-sm font-bold text-red-600 hover:bg-red-100 disabled:opacity-50"
          :disabled="pruning"
          @click="pruneFailed"
        >
          {{ pruning ? '...' : 'حذف وضعیت‌های ناموفق' }}
        </button>
      </div>

      <div class="flex gap-2">
        <button
          class="rounded-xl px-4 py-2 text-sm font-bold"
          :class="tab === 'runs' ? 'bg-orange-500 text-white' : 'bg-white'"
          @click="showRuns"
        >
          اجراها
        </button>
        <button
          class="rounded-xl px-4 py-2 text-sm font-bold"
          :class="tab === 'errors' ? 'bg-orange-500 text-white' : 'bg-white'"
          @click="showErrors"
        >
          خطاها
        </button>
      </div>

      <div class="rounded-xl bg-white p-4 shadow-sm">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
          <input
            v-model="store.filters.job_source_id"
            class="field"
            placeholder="شناسه منبع"
          />
          <select
            v-if="tab === 'runs'"
            v-model="store.filters.status"
            class="field"
          >
            <option value="">همه وضعیت‌ها</option>
            <option value="completed">completed</option>
            <option value="partial">partial</option>
            <option value="failed">failed</option>
            <option value="running">running</option>
            <option value="pending">pending</option>
          </select>
          <input
            v-else
            v-model="store.filters.error_type"
            class="field"
            placeholder="نوع خطا"
          />
        </div>
        <div class="mt-3">
          <button
            class="btn-orange"
            @click="tab === 'runs' ? loadRuns() : loadErrors()"
          >
            اعمال فیلتر
          </button>
        </div>
      </div>

      <DataTable
        v-if="tab === 'runs'"
        :columns="runColumns"
        :rows="store.runs"
        :loading="store.loading"
        actions
      >
        <template #cell-status="{ row }">
          <span
            class="rounded-full px-2 py-0.5 text-xs font-bold"
            :class="statusClass(row.status)"
            >{{ row.status }}</span
          >
        </template>
        <template #cell-started_at="{ row }">{{
          formatDate(row.started_at)
        }}</template>
        <template #cell-finished_at="{ row }">{{
          formatDate(row.finished_at)
        }}</template>
        <template #actions="{ row }">
          <button class="act" @click="showRun(row)">جزئیات</button>
          <button
            v-if="row.status === 'failed' || row.status === 'partial'"
            class="act text-red-600"
            @click="removeRun(row)"
          >
            حذف
          </button>
        </template>
      </DataTable>
      <PaginationBar
        v-if="tab === 'runs'"
        :meta="store.runsMeta"
        @page="(p) => store.fetchRuns(p)"
      />

      <DataTable
        v-else
        :columns="errorColumns"
        :rows="store.errors"
        :loading="store.loading"
      >
        <template #cell-occurred_at="{ row }">{{
          formatDate(row.occurred_at)
        }}</template>
        <template #cell-message="{ row }">
          <div class="max-w-md truncate text-xs" :title="row.message">
            {{ row.message }}
          </div>
        </template>
      </DataTable>
      <PaginationBar
        v-if="tab === 'errors'"
        :meta="store.errorsMeta"
        @page="(p) => store.fetchErrors(p)"
      />
    </div>

    <div
      v-if="detail"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    >
      <div
        class="max-h-[85vh] w-full max-w-2xl overflow-auto rounded-2xl bg-white p-5 shadow-xl"
      >
        <div class="mb-3 flex items-center justify-between">
          <h3 class="font-bold">
            اجرای #{{ detail.id }} — {{ detail.source_name }}
          </h3>
          <button class="btn-muted" @click="detail = null">بستن</button>
        </div>
        <div class="grid grid-cols-2 gap-2 text-sm">
          <div>وضعیت: {{ detail.status }}</div>
          <div>مدت: {{ detail.execution_ms }} ms</div>
          <div>یافت‌شده: {{ detail.jobs_found }}</div>
          <div>ایجاد: {{ detail.jobs_created }}</div>
          <div>به‌روزرسانی: {{ detail.jobs_updated }}</div>
          <div>تکراری: {{ detail.duplicates }}</div>
          <div>خطا: {{ detail.errors_count }}</div>
          <div>آخرین موفقیت منبع: {{ formatDate(detail.last_success_at) }}</div>
        </div>
        <h4 class="mt-4 font-bold">خطاها</h4>
        <div
          v-for="err in detail.errors || []"
          :key="err.id"
          class="mt-2 rounded-xl bg-red-50 p-3 text-xs"
        >
          <div class="font-bold">{{ err.error_type }}</div>
          <div>{{ err.message }}</div>
          <div dir="ltr" class="text-slate-500">{{ err.url }}</div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import AdminLayout from '../components/layout/AdminLayout.vue'
import DataTable from '../components/ui/DataTable.vue'
import PaginationBar from '../components/ui/PaginationBar.vue'
import { useCrawlMonitoringStore } from '../stores/crawlMonitoring'
import { useToast } from '../../composables/useToast'
import { formatDate } from '../../utils/format'

const store = useCrawlMonitoringStore()
const toast = useToast()
const tab = ref('runs')
const detail = ref(null)
const pruning = ref(false)

const runColumns = [
  { key: 'id', label: '#' },
  { key: 'source_name', label: 'منبع' },
  { key: 'status', label: 'وضعیت' },
  { key: 'started_at', label: 'شروع' },
  { key: 'finished_at', label: 'پایان' },
  { key: 'jobs_found', label: 'یافت' },
  { key: 'jobs_created', label: 'ایجاد' },
  { key: 'jobs_updated', label: 'بروز' },
  { key: 'duplicates', label: 'تکرار' },
  { key: 'errors_count', label: 'خطا' },
]

const errorColumns = [
  { key: 'id', label: '#' },
  { key: 'source_name', label: 'منبع' },
  { key: 'error_type', label: 'نوع' },
  { key: 'message', label: 'پیام' },
  { key: 'url', label: 'URL' },
  { key: 'occurred_at', label: 'زمان' },
]

function statusClass(status) {
  if (status === 'completed') return 'bg-emerald-100 text-emerald-700'
  if (status === 'partial') return 'bg-amber-100 text-amber-700'
  if (status === 'failed') return 'bg-red-100 text-red-700'
  return 'bg-slate-100 text-slate-600'
}

function loadRuns() {
  store.fetchRuns(1)
}
function loadErrors() {
  store.fetchErrors(1)
}
function showRuns() {
  tab.value = 'runs'
  loadRuns()
}
function showErrors() {
  tab.value = 'errors'
  loadErrors()
}
async function showRun(row) {
  detail.value = await store.fetchRun(row.id)
}

async function pruneFailed() {
  if (!window.confirm('همه اجراهای ناموفق/ناقص خزش حذف شوند؟')) return
  pruning.value = true
  try {
    const stats = await store.pruneFailed()
    toast.success(
      `پاکسازی شد — اجرا: ${stats?.crawler_runs_deleted ?? 0}`
    )
    await loadRuns()
    if (tab.value === 'errors') await loadErrors()
  } catch (e) {
    toast.error(e.response?.data?.message || 'پاکسازی ناموفق بود.')
  } finally {
    pruning.value = false
  }
}

async function removeRun(row) {
  if (!window.confirm(`اجرای #${row.id} حذف شود؟`)) return
  try {
    await store.destroyRun(row.id)
    toast.success('حذف شد.')
    await loadRuns()
  } catch (e) {
    toast.error(e.response?.data?.message || 'حذف ناموفق بود.')
  }
}

onMounted(loadRuns)
</script>
