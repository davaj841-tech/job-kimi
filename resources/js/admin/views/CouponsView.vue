<template>
  <AdminLayout>
    <div class="space-y-5">
      <div class="flex items-center justify-between gap-3">
        <h1 class="text-2xl font-bold">کدهای تخفیف</h1>
        <button class="btn-dark" @click="openCreate">کد جدید</button>
      </div>

      <div class="flex flex-wrap gap-2">
        <input
          v-model="search"
          class="field max-w-xs"
          placeholder="جستجوی کد..."
          @keyup.enter="load"
        />
        <select
          v-model="activeFilter"
          class="field max-w-[10rem]"
          @change="load"
        >
          <option value="">همه</option>
          <option value="1">فعال</option>
          <option value="0">غیرفعال</option>
        </select>
        <button class="btn-muted" @click="load">اعمال</button>
      </div>

      <DataTable :columns="columns" :rows="rows" :loading="loading" actions>
        <template #cell-index="{ index }">{{ index + 1 }}</template>
        <template #cell-type="{ row }">{{
          row.type === 'percentage' ? 'درصدی' : 'مبلغ ثابت'
        }}</template>
        <template #cell-value="{ row }">
          {{ row.type === 'percentage' ? `${fa(row.value)}٪` : fa(row.value) }}
        </template>
        <template #cell-uses="{ row }"
          >{{ fa(row.used_count) }} /
          {{ row.max_uses != null ? fa(row.max_uses) : '∞' }}</template
        >
        <template #cell-applicable_to="{ row }">{{
          applicableLabel(row.applicable_to)
        }}</template>
        <template #cell-is_active="{ row }">{{
          row.is_active ? 'فعال' : 'غیرفعال'
        }}</template>
        <template #actions="{ row }">
          <button class="act" @click="edit(row)">ویرایش</button>
          <button class="act text-red-600" @click="remove(row)">حذف</button>
        </template>
      </DataTable>
    </div>

    <div
      v-if="modal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    >
      <form
        class="max-h-[90vh] w-full max-w-lg space-y-3 overflow-y-auto rounded-2xl bg-white p-5"
        @submit.prevent="save"
      >
        <h3 class="font-bold">
          {{ form.id ? 'ویرایش کد تخفیف' : 'کد تخفیف جدید' }}
        </h3>
        <input
          v-model="form.code"
          required
          class="field uppercase"
          dir="ltr"
          placeholder="کد (مثلاً SAVE20)"
        />
        <select v-model="form.type" class="field">
          <option value="percentage">درصدی</option>
          <option value="fixed">مبلغ ثابت</option>
        </select>
        <input
          v-model.number="form.value"
          required
          type="number"
          min="0"
          class="field"
          placeholder="مقدار"
        />
        <input
          v-model.number="form.max_uses"
          type="number"
          min="1"
          class="field"
          placeholder="حداکثر استفاده (خالی = نامحدود)"
        />
        <input
          v-model.number="form.min_purchase"
          type="number"
          min="0"
          class="field"
          placeholder="حداقل مبلغ خرید"
        />
        <select v-model="form.applicable_to" class="field">
          <option value="both">هر دو</option>
          <option value="subscription">فقط اشتراک</option>
          <option value="pdf">فقط PDF</option>
        </select>
        <label class="block text-xs text-slate-500">شروع</label>
        <input v-model="form.starts_at" type="datetime-local" class="field" />
        <label class="block text-xs text-slate-500">انقضا</label>
        <input v-model="form.expires_at" type="datetime-local" class="field" />
        <label class="flex items-center gap-2 text-sm"
          ><input v-model="form.is_active" type="checkbox" /> فعال</label
        >
        <div class="flex justify-end gap-2">
          <button type="button" class="btn-muted" @click="modal = false">
            انصراف
          </button>
          <button class="btn-orange">ذخیره</button>
        </div>
      </form>
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
const rows = ref([])
const loading = ref(false)
const modal = ref(false)
const search = ref('')
const activeFilter = ref('')
const form = reactive({
  id: null,
  code: '',
  type: 'percentage',
  value: 10,
  max_uses: null,
  min_purchase: null,
  applicable_to: 'both',
  starts_at: '',
  expires_at: '',
  is_active: true,
})

const columns = [
  { key: 'index', label: '#' },
  { key: 'code', label: 'کد' },
  { key: 'type', label: 'نوع' },
  { key: 'value', label: 'مقدار' },
  { key: 'uses', label: 'استفاده' },
  { key: 'applicable_to', label: 'قابل اعمال' },
  { key: 'is_active', label: 'وضعیت' },
]

function fa(n) {
  return new Intl.NumberFormat('fa-IR').format(Number(n || 0))
}
function applicableLabel(v) {
  return { both: 'هر دو', subscription: 'اشتراک', pdf: 'PDF' }[v] || v
}
function toLocalInput(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return ''
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}
function fromLocalInput(v) {
  return v ? new Date(v).toISOString() : null
}

onMounted(load)

async function load() {
  loading.value = true
  try {
    const { data } = await adminApi.get('/admin/coupons', {
      params: {
        search: search.value || undefined,
        is_active: activeFilter.value === '' ? undefined : activeFilter.value,
      },
    })
    rows.value = unwrapList(data)
  } finally {
    loading.value = false
  }
}

function openCreate() {
  Object.assign(form, {
    id: null,
    code: '',
    type: 'percentage',
    value: 10,
    max_uses: null,
    min_purchase: null,
    applicable_to: 'both',
    starts_at: '',
    expires_at: '',
    is_active: true,
  })
  modal.value = true
}

function edit(row) {
  Object.assign(form, {
    ...row,
    starts_at: toLocalInput(row.starts_at),
    expires_at: toLocalInput(row.expires_at),
  })
  modal.value = true
}

async function save() {
  try {
    const payload = {
      code: String(form.code || '')
        .toUpperCase()
        .trim(),
      type: form.type,
      value: form.value,
      max_uses: form.max_uses || null,
      min_purchase: form.min_purchase || null,
      applicable_to: form.applicable_to,
      starts_at: fromLocalInput(form.starts_at),
      expires_at: fromLocalInput(form.expires_at),
      is_active: !!form.is_active,
    }
    if (form.id) {
      await adminApi.put(`/admin/coupons/${form.id}`, payload)
    } else {
      await adminApi.post('/admin/coupons', payload)
    }
    toast.success('ذخیره شد')
    modal.value = false
    load()
  } catch (e) {
    toast.error(apiErrorMessage(e))
  }
}

async function remove(row) {
  if (!confirm('حذف شود؟')) return
  await adminApi.delete(`/admin/coupons/${row.id}`)
  load()
}
</script>

<style scoped>
.field {
  @apply h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none;
}
.btn-dark {
  @apply rounded-xl bg-[#0f2744] px-4 py-2.5 text-sm font-bold text-white;
}
.btn-muted {
  @apply rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold;
}
.btn-orange {
  @apply rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-bold text-white;
}
.act {
  @apply rounded-lg px-2 py-1 text-xs font-bold text-slate-600 hover:bg-slate-100;
}
</style>
