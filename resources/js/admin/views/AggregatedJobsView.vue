<template>
      <div class="space-y-5">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">
          بررسی آگهی‌های تجمیع‌شده
        </h1>
        <p class="mt-1 text-sm text-slate-500">
          فقط آگهی‌های آمده از منابع رسمی. انتشار خودکار وجود ندارد.
        </p>
      </div>

      <div v-if="stats" class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <div class="rounded-xl bg-white p-4 shadow-sm">
          <p class="text-xs text-slate-500">کل تجمیع</p>
          <p class="text-xl font-bold">{{ stats.total_aggregated_jobs }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm">
          <p class="text-xs text-slate-500">در انتظار</p>
          <p class="text-xl font-bold text-amber-600">
            {{ stats.pending_jobs }}
          </p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm">
          <p class="text-xs text-slate-500">منتشر شده</p>
          <p class="text-xl font-bold text-emerald-700">
            {{ stats.published_jobs }}
          </p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm">
          <p class="text-xs text-slate-500">رد شده</p>
          <p class="text-xl font-bold text-red-600">
            {{ stats.rejected_jobs }}
          </p>
        </div>
      </div>

      <div class="rounded-xl bg-white p-4 shadow-sm">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
          <select v-model="store.filters.status" class="field">
            <option value="pending">در انتظار</option>
            <option value="approved">منتشر شده</option>
            <option value="rejected">رد شده</option>
            <option value="">همه</option>
          </select>
          <input
            v-model="store.filters.job_source_id"
            class="field"
            placeholder="شناسه منبع"
          />
          <input
            v-model="store.filters.search"
            class="field md:col-span-2"
            placeholder="جستجو عنوان"
            @keyup.enter="apply"
          />
        </div>
        <button class="btn-orange mt-3" @click="apply">اعمال</button>
      </div>

      <DataTable
        :columns="columns"
        :rows="store.jobs"
        :loading="store.loading"
        actions
      >
        <template #cell-title="{ row }">
          <div class="font-medium">{{ row.title }}</div>
          <div class="text-xs text-indigo-600">
            تجمیع از: {{ row.job_source?.name || '—' }}
          </div>
        </template>
        <template #cell-organization_name="{ row }">{{
          row.organization_name || row.company_name
        }}</template>
        <template #cell-status="{ row }">
          <span
            class="rounded-full px-2 py-0.5 text-xs font-bold"
            :class="statusClass(row.status)"
            >{{ statusLabel(row.status) }}</span
          >
        </template>
        <template #cell-created_at="{ row }">{{
          formatDate(row.created_at)
        }}</template>
        <template #actions="{ row }">
          <div class="flex flex-wrap justify-end gap-1">
            <button class="act" @click="openReview(row)">مشاهده/ویرایش</button>
            <button
              v-if="row.status === 'pending'"
              class="act text-emerald-700"
              @click="approve(row)"
            >
              انتشار
            </button>
            <button
              v-if="row.status === 'pending'"
              class="act text-red-600"
              @click="reject(row)"
            >
              رد
            </button>
          </div>
        </template>
      </DataTable>
      <PaginationBar
        :meta="store.meta"
        @page="(p) => store.fetchPendingJobs(p)"
      />
    </div>

    <div
      v-if="editing"
      class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4"
    >
      <div class="mt-6 w-full max-w-3xl rounded-2xl bg-white p-5 shadow-xl">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-lg font-bold">بررسی آگهی تجمیع‌شده</h2>
          <button class="btn-muted" @click="editing = null">بستن</button>
        </div>
        <div class="mb-4 rounded-xl bg-indigo-50 p-3 text-sm text-indigo-900">
          <div>منبع: {{ editing.job_source?.name }}</div>
          <div dir="ltr">Source URL: {{ editing.source_url || '—' }}</div>
          <div dir="ltr">
            Application URL: {{ editing.registration_link || '—' }}
          </div>
          <div>تاریخ انتشار منبع: {{ formatDate(editing.published_at) }}</div>
          <div>تاریخ خزش/ثبت: {{ formatDate(editing.created_at) }}</div>
        </div>
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
          <input
            v-model="form.title"
            class="field md:col-span-2"
            placeholder="عنوان"
          />
          <input
            v-model="form.organization_name"
            class="field md:col-span-2"
            placeholder="سازمان"
            disabled
          />
          <textarea
            v-model="form.description"
            class="field md:col-span-2"
            rows="4"
            placeholder="شرح"
          />
          <textarea
            v-model="form.requirements"
            class="field md:col-span-2"
            rows="3"
            placeholder="شرایط"
          />
          <input v-model="form.province" class="field" placeholder="استان" />
          <input v-model="form.city" class="field" placeholder="شهر" />
          <input
            v-model="form.registration_link"
            class="field md:col-span-2"
            placeholder="لینک ثبت‌نام"
            dir="ltr"
          />
          <input
            v-model="form.source_url"
            class="field md:col-span-2"
            placeholder="آدرس منبع"
            dir="ltr"
          />
        </div>
        <p v-if="formError" class="mt-2 text-sm text-red-600">
          {{ formError }}
        </p>
        <div class="mt-4 flex flex-wrap justify-end gap-2">
          <button class="btn-muted" @click="editing = null">انصراف</button>
          <button class="btn-dark" :disabled="saving" @click="save">
            ذخیره ویرایش
          </button>
          <button
            v-if="editing.status === 'pending'"
            class="btn-orange"
            @click="approve(editing)"
          >
            انتشار
          </button>
          <button
            v-if="editing.status === 'pending'"
            class="btn-muted text-red-600"
            @click="reject(editing)"
          >
            رد
          </button>
        </div>
      </div>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import DataTable from '../components/ui/DataTable.vue'
import PaginationBar from '../components/ui/PaginationBar.vue'
import { useAggregationStore } from '../stores/aggregation'
import { formatDate } from '../../utils/format'

const store = useAggregationStore()
const stats = ref(null)
const editing = ref(null)
const saving = ref(false)
const formError = ref('')
const form = reactive({
  title: '',
  organization_name: '',
  description: '',
  requirements: '',
  province: '',
  city: '',
  registration_link: '',
  source_url: '',
})

const columns = [
  { key: 'title', label: 'عنوان' },
  { key: 'organization_name', label: 'سازمان' },
  { key: 'status', label: 'وضعیت' },
  { key: 'created_at', label: 'ثبت/خزش' },
]

function statusLabel(s) {
  return { pending: 'در انتظار', approved: 'منتشر', rejected: 'رد شده' }[s] || s
}
function statusClass(s) {
  if (s === 'pending') return 'bg-amber-100 text-amber-700'
  if (s === 'approved') return 'bg-emerald-100 text-emerald-700'
  return 'bg-red-100 text-red-700'
}

function apply() {
  store.fetchPendingJobs(1)
}

async function openReview(row) {
  const full = await store.fetchJob(row.id)
  editing.value = full
  Object.assign(form, {
    title: full.title || '',
    organization_name: full.organization_name || full.company_name || '',
    description: full.description || '',
    requirements: full.requirements || '',
    province: full.province || '',
    city: full.city || '',
    registration_link: full.registration_link || '',
    source_url: full.source_url || '',
  })
  formError.value = ''
}

async function save() {
  saving.value = true
  formError.value = ''
  try {
    const payload = {
      title: form.title,
      description: form.description,
      requirements: form.requirements || null,
      province: form.province || null,
      city: form.city || null,
      registration_link: form.registration_link || null,
      source_url: form.source_url || null,
    }
    if (form.province) payload.provinces = [form.province]
    editing.value = await store.updateJob(editing.value.id, payload)
    await store.fetchPendingJobs(store.filters.page || 1)
    stats.value = await store.fetchStats()
  } catch (e) {
    formError.value = e.response?.data?.message || 'خطا در ذخیره'
  } finally {
    saving.value = false
  }
}

async function approve(row) {
  await store.approveJob(row.id)
  editing.value = null
  await store.fetchPendingJobs(store.filters.page || 1)
  stats.value = await store.fetchStats()
}

async function reject(row) {
  await store.rejectJob(row.id)
  editing.value = null
  await store.fetchPendingJobs(store.filters.page || 1)
  stats.value = await store.fetchStats()
}

onMounted(async () => {
  stats.value = await store.fetchStats()
  await store.fetchPendingJobs(1)
})
</script>
