<template>
  <AdminLayout>
    <div class="space-y-5">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-gray-800">
          مدیریت محتوای هوش مصنوعی
        </h1>
        <p class="text-sm text-slate-500">
          تولید امروز: {{ fa(store.stats.generated_today) }} / سقف روزانه:
          {{ fa(store.stats.daily_limit) }}
        </p>
      </div>

      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard
          title="تولید امروز"
          :value="fa(store.stats.generated_today)"
          icon="✦"
          color="#7c3aed"
        />
        <StatCard
          title="سقف روزانه"
          :value="fa(store.stats.daily_limit)"
          icon="▣"
          color="#0f2744"
        />
        <StatCard
          title="در انتظار تأیید"
          :value="fa(store.stats.pending)"
          icon="◎"
          color="#eab308"
        />
        <StatCard
          title="سوالات ذخیره‌شده"
          :value="fa(store.stats.by_type?.exam_question || 0)"
          icon="?"
          color="#2563eb"
        />
      </div>

      <div class="flex flex-wrap gap-2">
        <button
          v-for="t in tabs"
          :key="t.value"
          class="btn-muted"
          :class="
            store.filters.type === t.value ? 'ring-2 ring-orange-400' : ''
          "
          @click="switchTab(t.value)"
        >
          {{ t.label }}
        </button>
      </div>

      <div class="rounded-xl bg-white p-4 shadow-sm">
        <select
          v-model="store.filters.status"
          class="field max-w-xs"
          @change="apply"
        >
          <option value="">همه وضعیت‌ها</option>
          <option value="pending">در انتظار تأیید</option>
          <option value="approved">تایید شده</option>
          <option value="rejected">رد شده</option>
        </select>
      </div>

      <DataTable
        :columns="columns"
        :rows="store.items"
        :loading="store.loading"
        actions
      >
        <template #cell-index="{ index }">{{ fa(rowNum(index)) }}</template>
        <template #cell-topic="{ row }">{{ topicOf(row) }}</template>
        <template #cell-meta="{ row }">{{ metaSummary(row) }}</template>
        <template #cell-status="{ row }">
          <span
            class="rounded-full px-2 py-0.5 text-xs font-bold"
            :class="statusClass(row.status)"
            >{{ statusLabel(row.status) }}</span
          >
        </template>
        <template #cell-created_at="{ row }">{{
          formatDateTime(row.created_at)
        }}</template>
        <template #actions="{ row }">
          <div class="flex flex-wrap justify-end gap-1">
            <button class="act" @click="preview(row)">مشاهده</button>
            <button
              v-if="row.status === 'pending'"
              class="act text-emerald-700"
              @click="askApprove(row)"
            >
              تایید
            </button>
            <button
              v-if="row.status === 'pending'"
              class="act text-red-600"
              @click="askReject(row)"
            >
              رد
            </button>
            <button class="act text-red-600" @click="askDelete(row)">
              حذف
            </button>
          </div>
        </template>
      </DataTable>
      <PaginationBar :meta="store.meta" @page="(p) => store.fetchContents(p)" />
    </div>

    <AIPreviewModal
      :open="previewOpen"
      :item="selected"
      @close="previewOpen = false"
      @approve="doApprove"
      @reject="doReject"
    />
    <ConfirmDialog
      :open="confirm.open"
      :title="confirm.title"
      :message="confirm.message"
      @cancel="confirm.open = false"
      @confirm="runConfirm"
    />
  </AdminLayout>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import AdminLayout from '../components/layout/AdminLayout.vue'
import AIPreviewModal from '../components/ai/AIPreviewModal.vue'
import ConfirmDialog from '../components/ui/ConfirmDialog.vue'
import DataTable from '../components/ui/DataTable.vue'
import PaginationBar from '../components/ui/PaginationBar.vue'
import StatCard from '../components/ui/StatCard.vue'
import { formatDateTime, apiErrorMessage } from '../../utils/format'
import { useToast } from '../../composables/useToast'
import { useAiContentsStore } from '../stores/aiContents'

const store = useAiContentsStore()
const toast = useToast()
const previewOpen = ref(false)
const selected = ref(null)
const confirm = reactive({ open: false, title: '', message: '', action: null })

const tabs = [
  { value: 'exam_question', label: 'سوالات تولید شده' },
  { value: 'blog_post', label: 'مقالات تولید شده' },
  { value: 'job_crawl', label: 'آگهی‌های خزش شده' },
  { value: 'resume_tip', label: 'مشاوره‌های رزومه' },
]

const columns = [
  { key: 'index', label: '#' },
  { key: 'topic', label: 'موضوع' },
  { key: 'meta', label: 'جزئیات' },
  { key: 'status', label: 'وضعیت' },
  { key: 'created_at', label: 'تاریخ' },
]

function fa(n) {
  return new Intl.NumberFormat('fa-IR').format(Number(n || 0))
}
function rowNum(i) {
  return (
    (store.meta.from ||
      (store.meta.current_page - 1) * (store.meta.per_page || 20) + 1 ||
      1) + i
  )
}
function statusLabel(s) {
  return (
    { pending: 'در انتظار تأیید', approved: 'تایید شده', rejected: 'رد شده' }[
      s
    ] || s
  )
}
function statusClass(s) {
  return (
    {
      pending: 'bg-yellow-100 text-yellow-800',
      approved: 'bg-emerald-100 text-emerald-800',
      rejected: 'bg-red-100 text-red-700',
    }[s] || 'bg-slate-100'
  )
}
function topicOf(row) {
  return (
    row.prompt || row.metadata?.topic || row.metadata?.subject || `#${row.id}`
  )
}
function metaSummary(row) {
  const m = row.metadata || {}
  if (row.type === 'exam_question') {
    return (
      [m.subject, m.difficulty, m.count ? `${m.count} سوال` : '']
        .filter(Boolean)
        .join(' / ') || '—'
    )
  }
  return m.source || '—'
}

onMounted(async () => {
  await Promise.all([store.fetchStats(), store.fetchContents()])
})

function switchTab(type) {
  store.filters.type = type
  store.fetchContents(1)
}
function apply() {
  store.fetchContents(1)
}
async function preview(row) {
  selected.value = await store.fetchContent(row.id)
  previewOpen.value = true
}
function askApprove(row) {
  confirm.open = true
  confirm.title = 'تایید محتوا'
  confirm.message = 'این محتوا تایید و در سیستم ثبت شود؟'
  confirm.action = async () => {
    await store.approve(row.id)
    toast.success('تایید شد')
    previewOpen.value = false
  }
}
function askReject(row) {
  confirm.open = true
  confirm.title = 'رد محتوا'
  confirm.message = 'این محتوا رد شود؟'
  confirm.action = async () => {
    await store.reject(row.id)
    toast.success('رد شد')
    previewOpen.value = false
  }
}
function askDelete(row) {
  confirm.open = true
  confirm.title = 'حذف'
  confirm.message = 'محتوا حذف شود؟'
  confirm.action = async () => {
    await store.remove(row.id)
    toast.success('حذف شد')
  }
}
async function doApprove() {
  if (!selected.value) return
  await store.approve(selected.value.id)
  toast.success('تایید شد')
  previewOpen.value = false
}
async function doReject() {
  if (!selected.value) return
  await store.reject(selected.value.id)
  toast.success('رد شد')
  previewOpen.value = false
}
async function runConfirm() {
  try {
    await confirm.action?.()
  } catch (e) {
    toast.error(apiErrorMessage(e))
  } finally {
    confirm.open = false
  }
}
</script>

<style scoped>
.field {
  @apply h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-orange-400;
}
.btn-muted {
  @apply rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold text-slate-700;
}
.act {
  @apply rounded-lg px-2 py-1 text-xs font-bold text-slate-600 hover:bg-slate-100;
}
</style>
