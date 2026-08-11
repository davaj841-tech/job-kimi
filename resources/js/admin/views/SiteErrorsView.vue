<template>
  <AdminLayout>
    <div class="space-y-5">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-gray-800">خطاهای سایت</h1>
        <div class="flex flex-wrap gap-2">
          <button type="button" class="btn-muted" :disabled="exporting" @click="exportExcel">
            {{ exporting ? '...' : 'خروجی اکسل' }}
          </button>
          <button type="button" class="btn-muted" @click="clearResolved">
            پاک‌سازی حل‌شده‌ها
          </button>
          <button type="button" class="btn-orange" @click="runAutoHeal">
            خودترمیمی خودکار
          </button>
        </div>
      </div>

      <div class="rounded-xl bg-white p-4 shadow-sm">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
          <input
            v-model="filters.search"
            class="field md:col-span-2"
            placeholder="جستجو در پیام / کلاس / URL"
            @keyup.enter="load(1)"
          />
          <select v-model="filters.resolved" class="field">
            <option value="0">حل‌نشده</option>
            <option value="1">حل‌شده</option>
            <option value="">همه</option>
          </select>
        </div>
        <div class="mt-3 flex gap-2">
          <button type="button" class="btn-orange" @click="load(1)">
            اعمال فیلتر
          </button>
          <button type="button" class="btn-muted" @click="clear">
            پاک کردن
          </button>
        </div>
      </div>

      <div
        v-if="loading"
        class="rounded-xl bg-white p-8 text-center text-sm text-slate-500 shadow-sm"
      >
        در حال بارگذاری...
      </div>

      <div v-else class="space-y-3">
        <article
          v-for="row in rows"
          :key="row.id"
          class="rounded-xl bg-white p-4 shadow-sm"
        >
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
              <p class="text-base font-bold text-slate-800">
                {{ row.message_fa || row.message || 'بدون پیام' }}
              </p>
              <p
                v-if="row.message_fa && row.message"
                class="mt-1 text-xs text-slate-500"
                dir="ltr"
              >
                {{ row.message }}
              </p>
              <div class="mt-2 flex flex-wrap gap-2 text-xs text-slate-500">
                <span
                  class="rounded-md bg-slate-100 px-2 py-0.5 font-mono"
                  dir="ltr"
                >
                  {{ row.exception_class || row.class || '—' }}
                </span>
                <span>تکرار: {{ fa(row.occurrences) }}</span>
                <span
                  >آخرین:
                  {{ formatDateTime(row.last_seen_at || row.last_seen) }}</span
                >
                <span v-if="row.resolved_at" class="font-bold text-emerald-600"
                  >حل‌شده</span
                >
              </div>
            </div>
            <div class="flex shrink-0 gap-1">
              <button
                v-if="!row.resolved_at"
                type="button"
                class="act text-emerald-700"
                @click="resolve(row)"
              >
                حل شد
              </button>
              <button
                type="button"
                class="act text-red-600"
                @click="askDelete(row)"
              >
                حذف
              </button>
            </div>
          </div>
        </article>

        <p
          v-if="!rows.length"
          class="rounded-xl bg-white py-8 text-center text-slate-500 shadow-sm"
        >
          خطایی یافت نشد
        </p>
      </div>

      <PaginationBar :meta="meta" @page="load" />
    </div>

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
import adminApi from '../api/client'
import AdminLayout from '../components/layout/AdminLayout.vue'
import ConfirmDialog from '../components/ui/ConfirmDialog.vue'
import PaginationBar from '../components/ui/PaginationBar.vue'
import { formatDateTime, unwrapList, unwrapMeta } from '../../utils/format'
import { useToast } from '../../composables/useToast'

const toast = useToast()
const rows = ref([])
const meta = ref(null)
const loading = ref(false)
const exporting = ref(false)
const filters = reactive({ search: '', resolved: '0' })
const confirm = reactive({ open: false, title: '', message: '', action: null })

onMounted(() => load(1))

async function load(page = 1) {
  loading.value = true
  try {
    const params = { page, per_page: 20 }
    if (filters.search) params.search = filters.search
    if (filters.resolved !== '') params.resolved = filters.resolved
    const { data } = await adminApi.get('/admin/site-errors', { params })
    rows.value = unwrapList(data)
    meta.value = unwrapMeta(data)
  } catch (e) {
    toast.error(e.response?.data?.message || 'بارگذاری خطاها ناموفق بود.')
    rows.value = []
  } finally {
    loading.value = false
  }
}

function clear() {
  filters.search = ''
  filters.resolved = '0'
  load(1)
}

async function exportExcel() {
  exporting.value = true
  try {
    const params = {}
    if (filters.search) params.search = filters.search
    if (filters.resolved !== '') params.resolved = filters.resolved
    const response = await adminApi.get('/admin/site-errors/export', {
      params,
      responseType: 'blob',
    })
    const url = URL.createObjectURL(new Blob([response.data]))
    const a = document.createElement('a')
    a.href = url
    a.download = `site-errors-${Date.now()}.xlsx`
    a.click()
    URL.revokeObjectURL(url)
    toast.success('فایل اکسل دانلود شد.')
  } catch (e) {
    toast.error(e.response?.data?.message || 'دانلود اکسل ناموفق بود.')
  } finally {
    exporting.value = false
  }
}

function fa(n) {
  return new Intl.NumberFormat('fa-IR').format(Number(n || 0))
}

async function resolve(row) {
  try {
    await adminApi.post(`/admin/site-errors/${row.id}/resolve`)
    toast.success('خطا به‌عنوان حل‌شده علامت خورد.')
    await load(meta.value?.current_page || 1)
  } catch (e) {
    toast.error(e.response?.data?.message || 'عملیات ناموفق بود.')
  }
}

function askDelete(row) {
  confirm.open = true
  confirm.title = 'حذف خطا'
  confirm.message = 'این رکورد خطا حذف شود؟'
  confirm.action = async () => {
    await adminApi.delete(`/admin/site-errors/${row.id}`)
    toast.success('حذف شد.')
    await load(meta.value?.current_page || 1)
  }
}

async function clearResolved() {
  confirm.open = true
  confirm.title = 'پاک‌سازی حل‌شده‌ها'
  confirm.message = 'تمام خطاهای حل‌شده حذف شوند؟'
  confirm.action = async () => {
    await adminApi.delete('/admin/site-errors')
    toast.success('خطاهای حل‌شده پاک شدند.')
    await load(1)
  }
}

async function runAutoHeal() {
  try {
    const { data } = await adminApi.post('/admin/site-errors/auto-heal', {
      aggressive: false,
    })
    const s = data.data || {}
    toast.success(
      `خودترمیمی: خزش ${s.crawler_runs_deleted ?? 0} | خطای حل‌شده ${s.site_errors_resolved ?? 0}`
    )
    await load(1)
  } catch (e) {
    toast.error(e.response?.data?.message || 'خودترمیمی ناموفق بود.')
  }
}

async function runConfirm() {
  const fn = confirm.action
  confirm.open = false
  try {
    if (fn) await fn()
  } catch (e) {
    toast.error(e.response?.data?.message || 'عملیات ناموفق بود.')
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
.btn-muted {
  @apply rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold text-slate-700;
}
.act {
  @apply rounded-lg bg-slate-100 px-2 py-1 text-[11px] font-bold text-slate-700;
}
</style>
