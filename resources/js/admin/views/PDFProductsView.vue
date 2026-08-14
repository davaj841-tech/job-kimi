<template>
  <AdminLayout>
    <div class="space-y-5">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-gray-800">فروشگاه فایل‌های PDF</h1>
        <button class="btn-dark" @click="openCreate">فایل جدید</button>
      </div>

      <div class="rounded-xl bg-white p-4 shadow-sm">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
          <input
            v-model="store.filters.search"
            class="field"
            placeholder="جستجو"
            @keyup.enter="apply"
          />
          <input
            v-model="store.filters.category"
            class="field"
            placeholder="طبقه‌بندی"
          />
          <select v-model="store.filters.is_active" class="field">
            <option value="">همه وضعیت‌ها</option>
            <option value="1">فعال</option>
            <option value="0">غیرفعال</option>
          </select>
        </div>
        <div class="mt-3 flex gap-2">
          <button class="btn-orange" @click="apply">اعمال فیلتر</button>
          <button class="btn-muted" @click="clear">پاک کردن</button>
        </div>
      </div>

      <DataTable
        :columns="columns"
        :rows="store.products"
        :loading="store.loading"
        actions
      >
        <template #cell-index="{ index }">{{ fa(rowNum(index)) }}</template>
        <template #cell-title="{ row }">
          <button
            class="text-right font-medium hover:text-orange-600"
            @click="openEdit(row)"
          >
            {{ row.title }}
          </button>
        </template>
        <template #cell-price="{ row }">{{ formatPrice(row.price) }}</template>
        <template #cell-download_count="{ row }">{{
          fa(row.download_count)
        }}</template>
        <template #cell-is_active="{ row }">
          <StatusToggle
            :model-value="Boolean(row.is_active)"
            @update:model-value="(v) => onToggle(row, v)"
          />
        </template>
        <template #actions="{ row }">
          <div class="flex flex-wrap justify-end gap-1">
            <button class="act" @click="openEdit(row)">ویرایش</button>
            <button
              class="act"
              @click="toast.info(`دانلودها: ${fa(row.download_count)}`)"
            >
              آمار دانلود
            </button>
            <button class="act text-red-600" @click="askDelete(row)">
              حذف
            </button>
          </div>
        </template>
      </DataTable>

      <PaginationBar :meta="store.meta" @page="go" />
    </div>

    <PDFProductModal
      :open="modalOpen"
      :product="editing"
      :classifications="classifications"
      @close="modalOpen = false"
      @saved="onSaved"
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
import ConfirmDialog from '../components/ui/ConfirmDialog.vue'
import DataTable from '../components/ui/DataTable.vue'
import PaginationBar from '../components/ui/PaginationBar.vue'
import StatusToggle from '../components/ui/StatusToggle.vue'
import PDFProductModal from '../components/pdf/PDFProductModal.vue'
import { formatPrice } from '../../utils/format'
import { useToast } from '../../composables/useToast'
import { usePdfProductsStore } from '../stores/pdfProducts'
import adminApi from '../api/client'

const store = usePdfProductsStore()
const toast = useToast()
const modalOpen = ref(false)
const editing = ref(null)
const classifications = ref([])
const confirm = reactive({ open: false, title: '', message: '', action: null })

const columns = [
  { key: 'index', label: '#' },
  { key: 'title', label: 'عنوان' },
  { key: 'category', label: 'طبقه‌بندی' },
  { key: 'price', label: 'قیمت' },
  { key: 'download_count', label: 'تعداد دانلود' },
  { key: 'is_active', label: 'وضعیت' },
]

function fa(n) {
  return new Intl.NumberFormat('fa-IR').format(Number(n || 0))
}
function rowNum(i) {
  return (
    ((store.meta.current_page || 1) - 1) * (store.meta.per_page || 20) + i + 1
  )
}

async function apply() {
  await store.fetchPDFProducts(1)
}
async function clear() {
  store.resetFilters()
  await store.fetchPDFProducts(1)
}
async function go(p) {
  await store.fetchPDFProducts(p)
}
function openCreate() {
  editing.value = null
  modalOpen.value = true
}
async function openEdit(row) {
  try {
    editing.value = await store.fetchPDFProduct(row.id)
    modalOpen.value = true
  } catch (e) {
    toast.error(e.response?.data?.message || 'بارگذاری ناموفق بود.')
  }
}
async function onToggle(row, value) {
  try {
    await store.toggleActive(row.id, value)
    toast.success('وضعیت به‌روزرسانی شد.')
  } catch (e) {
    toast.error(e.response?.data?.message || 'تغییر وضعیت ناموفق بود.')
  }
}
function askDelete(row) {
  confirm.open = true
  confirm.title = 'حذف فایل'
  confirm.message = `فایل «${row.title}» حذف شود؟`
  confirm.action = async () => {
    await store.deletePDFProduct(row.id)
    toast.success('فایل حذف شد.')
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
async function onSaved({ id, payload }) {
  try {
    if (id) await store.updatePDFProduct(id, payload)
    else await store.createPDFProduct(payload)
    modalOpen.value = false
    toast.success('فایل ذخیره شد.')
  } catch (e) {
    toast.error(e.response?.data?.message || 'ذخیره ناموفق بود.')
  }
}

onMounted(async () => {
  await Promise.all([
    store.fetchPDFProducts(1),
    adminApi.get('/admin/job-classifications').then(({ data }) => {
      classifications.value = data.data?.flat || data.data || []
    }),
  ])
})
</script>

<style scoped>
.field {
  @apply h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-orange-400;
}
.btn-dark {
  @apply rounded-xl bg-[#0f2744] px-4 py-2.5 text-sm font-bold text-white;
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
