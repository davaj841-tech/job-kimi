<template>
  <AdminLayout>
    <div class="space-y-5">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-2xl font-bold text-gray-800">تولید محتوای خودکار</h1>
          <p class="mt-1 text-sm text-slate-500">
            مقالات فارسی از آگهی‌های تأییدشده — بدون هوش مصنوعی
          </p>
        </div>
        <button
          type="button"
          class="btn-orange"
          :disabled="generating"
          @click="generateNow"
        >
          {{ generating ? '...' : 'تولید اکنون' }}
        </button>
      </div>

      <div
        v-if="dash.enabled === false"
        class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
      >
        تولید زمان‌بندی‌شده خاموش است. دکمه «تولید اکنون» همچنان کار می‌کند.
        برای نمایش در سایت، مقاله را «انتشار» کنید یا
        <code>CONTENT_PUBLISH_MODE=publish</code> بگذارید.
      </div>

      <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <div
          v-for="c in cards"
          :key="c.key"
          class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm"
        >
          <p class="text-xs text-slate-500">{{ c.label }}</p>
          <p class="mt-1 text-xl font-black">{{ fa(dash[c.key] || 0) }}</p>
        </div>
      </div>

      <div class="rounded-xl bg-white p-4 shadow-sm">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
          <input
            v-model="filters.search"
            class="field md:col-span-2"
            placeholder="جستجو عنوان/اسلاگ"
          />
          <select v-model="filters.status" class="field">
            <option value="">همه وضعیت‌ها</option>
            <option value="draft">پیش‌نویس</option>
            <option value="published">منتشرشده</option>
            <option value="failed">ناموفق</option>
            <option value="skipped">ردشده</option>
            <option value="scheduled">زمان‌بندی</option>
          </select>
          <select v-model="filters.content_type" class="field">
            <option value="">همه انواع</option>
            <option
              v-for="t in types"
              :key="t.value"
              :value="t.value"
            >
              {{ t.label }}
            </option>
          </select>
        </div>
        <div class="mt-3">
          <button type="button" class="btn-orange" @click="load(1)">
            اعمال فیلتر
          </button>
        </div>
      </div>

      <DataTable
        :columns="columns"
        :rows="rows"
        :loading="loading"
        actions
      >
        <template #cell-created_at="{ row }">{{
          formatDate(row.created_at)
        }}</template>
        <template #cell-status="{ row }">
          <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold">{{
            row.status
          }}</span>
        </template>
        <template #actions="{ row }">
          <button class="act" @click="open(row)">مشاهده</button>
          <button class="act" @click="regenerate(row)">بازتولید</button>
          <button
            v-if="row.status !== 'published'"
            class="act text-emerald-700"
            @click="publish(row)"
          >
            انتشار
          </button>
          <button
            v-if="row.status === 'published'"
            class="act text-amber-700"
            @click="unpublish(row)"
          >
            پیش‌نویس
          </button>
          <button class="act text-red-600" @click="remove(row)">حذف</button>
        </template>
      </DataTable>
      <PaginationBar :meta="meta" @page="load" />
    </div>

    <div
      v-if="detail"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    >
      <div
        class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white p-5"
      >
        <div class="mb-3 flex items-center justify-between">
          <h3 class="font-bold">{{ detail.title }}</h3>
          <button class="btn-muted" @click="detail = null">بستن</button>
        </div>
        <p class="mb-2 text-xs text-slate-500">
          {{ detail.content_type_label }} · {{ detail.status }} ·
          {{ detail.company_name || '—' }}
        </p>
        <div
          class="prose prose-sm max-w-none whitespace-pre-wrap text-right text-sm leading-7 text-slate-800"
        >
          {{ detailPreview }}
        </div>
        <p v-if="detail.last_error" class="mt-3 text-sm text-red-600">
          {{ detail.last_error }}
        </p>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import adminApi from '../api/client'
import AdminLayout from '../components/layout/AdminLayout.vue'
import DataTable from '../components/ui/DataTable.vue'
import PaginationBar from '../components/ui/PaginationBar.vue'
import { useToast } from '../../composables/useToast'
import { formatDate, unwrapList, unwrapMeta } from '../../utils/format'

const toast = useToast()
const dash = ref({})
const rows = ref([])
const meta = ref(null)
const loading = ref(false)
const generating = ref(false)
const detail = ref(null)
const types = ref([])
const filters = reactive({ search: '', status: '', content_type: '' })

const detailPreview = computed(() => {
  const html = detail.value?.content || ''
  return String(html)
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/<\/p>/gi, '\n')
    .replace(/<\/h[2-4]>/gi, '\n')
    .replace(/<[^>]+>/g, '')
    .replace(/&nbsp;/g, ' ')
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&#039;/g, "'")
    .trim()
})

const columns = [
  { key: 'id', label: '#' },
  { key: 'title', label: 'عنوان' },
  { key: 'content_type_label', label: 'نوع' },
  { key: 'status', label: 'وضعیت' },
  { key: 'company_name', label: 'سازمان' },
  { key: 'created_at', label: 'ایجاد' },
]

const cards = [
  { key: 'generated_today', label: 'تولید امروز' },
  { key: 'published_today', label: 'انتشار امروز' },
  { key: 'drafts', label: 'پیش‌نویس' },
  { key: 'failed', label: 'ناموفق' },
  { key: 'skipped', label: 'تکراری/رد' },
  { key: 'pending_publish', label: 'در انتظار' },
]

onMounted(async () => {
  await loadDash()
  await load(1)
})

function fa(n) {
  return Number(n || 0).toLocaleString('fa-IR')
}

async function loadDash() {
  const { data } = await adminApi.get('/admin/generated-contents/dashboard')
  dash.value = data.data || {}
}

async function load(page = 1) {
  loading.value = true
  try {
    const { data } = await adminApi.get('/admin/generated-contents', {
      params: { ...filters, page, per_page: 20 },
    })
    rows.value = unwrapList(data)
    meta.value = unwrapMeta(data)
    const payload = data?.data
    if (Array.isArray(payload?.types)) {
      types.value = payload.types
    }
  } finally {
    loading.value = false
  }
}

async function generateNow() {
  generating.value = true
  try {
    const { data } = await adminApi.post('/admin/generated-contents/generate-now', {
      seed_templates: true,
    })
    const created = data.data?.created ?? 0
    const updated = data.data?.updated ?? 0
    const err = data.data?.errors?.[0]
    toast.success(data.message || `ایجاد: ${created} | به‌روز: ${updated}`)
    if (!created && !updated && err) {
      toast.error(err)
    }
    await loadDash()
    await load(1)
  } catch (e) {
    toast.error(e.response?.data?.message || 'تولید ناموفق بود.')
  } finally {
    generating.value = false
  }
}

async function open(row) {
  const { data } = await adminApi.get(`/admin/generated-contents/${row.id}`)
  detail.value = data.data
}

async function regenerate(row) {
  try {
    await adminApi.post(`/admin/generated-contents/${row.id}/regenerate`)
    toast.success('بازتولید شد.')
    await load(meta.value?.current_page || 1)
  } catch (e) {
    toast.error(e.response?.data?.message || 'بازتولید ناموفق.')
  }
}

async function publish(row) {
  try {
    await adminApi.post(`/admin/generated-contents/${row.id}/publish`)
    toast.success('منتشر شد.')
    await loadDash()
    await load(meta.value?.current_page || 1)
  } catch (e) {
    toast.error(e.response?.data?.message || 'انتشار ناموفق.')
  }
}

async function unpublish(row) {
  try {
    await adminApi.post(`/admin/generated-contents/${row.id}/unpublish`)
    toast.success('به پیش‌نویس برگشت.')
    await loadDash()
    await load(meta.value?.current_page || 1)
  } catch (e) {
    toast.error(e.response?.data?.message || 'عملیات ناموفق.')
  }
}

async function remove(row) {
  if (!window.confirm('حذف شود؟')) return
  try {
    await adminApi.delete(`/admin/generated-contents/${row.id}`)
    toast.success('حذف شد.')
    await load(1)
  } catch (e) {
    toast.error(e.response?.data?.message || 'حذف ناموفق.')
  }
}
</script>

<style scoped>
.field {
  @apply h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none;
}
.btn-orange {
  @apply rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50;
}
.btn-muted {
  @apply rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold;
}
.act {
  @apply rounded-lg px-2 py-1 text-[11px] font-bold text-slate-700 hover:bg-slate-100;
}
</style>
