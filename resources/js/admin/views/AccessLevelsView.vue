<template>
      <div class="space-y-5">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">سطح دسترسی اپراتور</h1>
        <p class="mt-1 text-sm text-slate-500">
          برای هر اپراتور مشخص کنید به کدام بخش‌های پنل دسترسی داشته باشد.
        </p>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-white p-4">
        <div class="mb-3 flex flex-wrap items-center gap-2">
          <input
            v-model="search"
            type="search"
            class="field max-w-xs"
            placeholder="جستجوی نام یا موبایل…"
            @keyup.enter="load"
          />
          <button type="button" class="btn-dark" @click="load">جستجو</button>
        </div>

        <DataTable
          :columns="columns"
          :rows="rows"
          :loading="loading"
          actions
        >
          <template #cell-index="{ index }">{{ index + 1 }}</template>
          <template #cell-role="{ row }">
            <span
              class="rounded-full px-2 py-0.5 text-[11px] font-bold"
              :class="
                row.role === 'admin'
                  ? 'bg-emerald-50 text-emerald-700'
                  : 'bg-orange-50 text-orange-700'
              "
            >
              {{ row.role === 'admin' ? 'مدیر' : 'اپراتور' }}
            </span>
          </template>
          <template #cell-perms="{ row }">
            <span v-if="row.role === 'admin'" class="text-xs text-slate-500"
              >همه دسترسی‌ها</span
            >
            <span v-else class="text-xs text-slate-600">
              {{ (row.operator_permissions || []).length }} مورد
            </span>
          </template>
          <template #actions="{ row }">
            <button
              v-if="row.role === 'operator'"
              type="button"
              class="act"
              @click="openEdit(row)"
            >
              تنظیم دسترسی
            </button>
            <span v-else class="text-xs text-slate-400">—</span>
          </template>
        </DataTable>
      </div>
    </div>

    <div
      v-if="modal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    >
      <form
        class="w-full max-w-lg space-y-4 rounded-2xl bg-white p-5 shadow-xl"
        @submit.prevent="save"
      >
        <h3 class="text-lg font-bold">دسترسی: {{ form.name || form.mobile }}</h3>
        <OperatorPermissionsPicker v-model="form.operator_permissions" />
        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
        <div class="flex justify-end gap-2">
          <button type="button" class="btn-muted" @click="modal = false">
            انصراف
          </button>
          <button class="btn-orange" :disabled="saving">
            {{ saving ? '...' : 'ذخیره' }}
          </button>
        </div>
      </form>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import adminApi from '../api/client'
import OperatorPermissionsPicker from '../components/users/OperatorPermissionsPicker.vue'
import DataTable from '../components/ui/DataTable.vue'
import { DEFAULT_OPERATOR_PERMISSIONS } from '../permissions'
import { unwrapList, apiErrorMessage } from '../../utils/format'
import { useToast } from '../../composables/useToast'

const toast = useToast()
const rows = ref([])
const loading = ref(false)
const saving = ref(false)
const modal = ref(false)
const search = ref('')
const error = ref('')
const form = reactive({
  id: null,
  name: '',
  mobile: '',
  operator_permissions: [...DEFAULT_OPERATOR_PERMISSIONS],
})

const columns = [
  { key: 'index', label: '#' },
  { key: 'name', label: 'نام' },
  { key: 'mobile', label: 'موبایل' },
  { key: 'role', label: 'نقش' },
  { key: 'perms', label: 'دسترسی' },
]

onMounted(load)

async function load() {
  loading.value = true
  try {
    const { data } = await adminApi.get('/admin/users', {
      params: {
        search: search.value || undefined,
        role: 'operator',
        per_page: 50,
      },
    })
    const list = unwrapList(data)
    // also include operators that may come without role filter support
    rows.value = (list || []).filter(
      (u) => u.role === 'operator' || u.role === 'admin'
    )
    if (!rows.value.length && list?.length) rows.value = list
  } catch (e) {
    toast.error(apiErrorMessage(e) || 'بارگذاری ناموفق')
  } finally {
    loading.value = false
  }
}

function openEdit(row) {
  form.id = row.id
  form.name = row.name || ''
  form.mobile = row.mobile || ''
  form.operator_permissions = Array.isArray(row.operator_permissions)
    ? [...row.operator_permissions]
    : [...DEFAULT_OPERATOR_PERMISSIONS]
  error.value = ''
  modal.value = true
}

async function save() {
  if (!form.id) return
  saving.value = true
  error.value = ''
  try {
    await adminApi.put(`/admin/users/${form.id}`, {
      mobile: form.mobile,
      operator_permissions: form.operator_permissions,
    })
    toast.success('دسترسی ذخیره شد')
    modal.value = false
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e) || 'ذخیره ناموفق'
  } finally {
    saving.value = false
  }
}
</script>
