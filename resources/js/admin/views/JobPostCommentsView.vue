<template>
  <div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-bold text-gray-800">نظرات آگهی‌ها</h1>
      <p class="text-sm text-slate-500">
        نظرات کاربران پس از تایید نمایش داده می‌شوند.
      </p>
    </div>

    <div class="flex flex-wrap gap-2 rounded-xl bg-white p-4 shadow-sm">
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
      <input
        v-model="store.filters.search"
        type="search"
        class="field max-w-sm"
        placeholder="جستجو در نظر / کاربر / آگهی..."
        @keyup.enter="apply"
      />
      <button class="btn-muted" @click="apply">اعمال فیلتر</button>
    </div>

    <DataTable
      :columns="columns"
      :rows="store.items"
      :loading="store.loading"
      actions
    >
      <template #cell-index="{ index }">{{ fa(rowNum(index)) }}</template>
      <template #cell-job="{ row }">
        <RouterLink
          v-if="row.job_post?.id"
          :to="`/admin/job-posts`"
          class="font-bold text-brand hover:underline"
          :title="row.job_post?.title"
        >
          {{ truncate(row.job_post?.title || '—', 40) }}
        </RouterLink>
        <span v-else>—</span>
      </template>
      <template #cell-user="{ row }">{{
        row.user?.name || row.user?.mobile || '—'
      }}</template>
      <template #cell-content="{ row }">
        <span class="line-clamp-2 text-sm">{{ row.content }}</span>
      </template>
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
          <button class="act text-red-600" @click="askDelete(row)">حذف</button>
        </div>
      </template>
    </DataTable>
    <PaginationBar :meta="store.meta" @page="(p) => store.fetchComments(p)" />
  </div>

  <ConfirmDialog
    :open="confirm.open"
    :title="confirm.title"
    :message="confirm.message"
    @cancel="confirm.open = false"
    @confirm="runConfirm"
  />
</template>

<script setup>
import { onMounted, reactive } from 'vue'
import ConfirmDialog from '../components/ui/ConfirmDialog.vue'
import DataTable from '../components/ui/DataTable.vue'
import PaginationBar from '../components/ui/PaginationBar.vue'
import { formatDateTime, apiErrorMessage } from '../../utils/format'
import { useToast } from '../../composables/useToast'
import { useJobPostCommentsStore } from '../stores/jobPostComments'

const store = useJobPostCommentsStore()
const toast = useToast()
const confirm = reactive({ open: false, title: '', message: '', action: null })

const columns = [
  { key: 'index', label: '#' },
  { key: 'job', label: 'آگهی' },
  { key: 'user', label: 'کاربر' },
  { key: 'content', label: 'نظر' },
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
function truncate(s, n) {
  const t = String(s || '')
  return t.length > n ? t.slice(0, n) + '…' : t
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

onMounted(() => store.fetchComments())

function apply() {
  store.fetchComments(1)
}
function askApprove(row) {
  confirm.open = true
  confirm.title = 'تایید نظر'
  confirm.message = 'این نظر تایید و در صفحه آگهی نمایش داده شود؟'
  confirm.action = async () => {
    await store.approve(row.id)
    toast.success('نظر تایید شد')
  }
}
function askReject(row) {
  confirm.open = true
  confirm.title = 'رد نظر'
  confirm.message = 'این نظر رد شود؟'
  confirm.action = async () => {
    await store.reject(row.id)
    toast.success('نظر رد شد')
  }
}
function askDelete(row) {
  confirm.open = true
  confirm.title = 'حذف نظر'
  confirm.message = 'نظر برای همیشه حذف شود؟'
  confirm.action = async () => {
    await store.remove(row.id)
    toast.success('نظر حذف شد')
  }
}
async function runConfirm() {
  const action = confirm.action
  confirm.open = false
  if (!action) return
  try {
    await action()
  } catch (e) {
    toast.error(apiErrorMessage(e))
  }
}
</script>
