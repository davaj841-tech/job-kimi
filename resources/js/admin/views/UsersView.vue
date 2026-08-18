<template>
      <div class="space-y-5">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <h1 class="text-2xl font-bold text-gray-800">مدیریت کاربران</h1>
          <span
            class="rounded-full bg-orange-100 px-3 py-1 text-xs font-bold text-orange-700"
          >
            {{ faNum(store.meta.total || 0) }} کاربر
          </span>
        </div>
        <button
          type="button"
          class="rounded-xl bg-[#0f2744] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#1e3a5f]"
          @click="createOpen = true"
        >
          ➕ افزودن کاربر
        </button>
      </div>

      <div class="rounded-xl bg-white p-4 shadow-sm">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-5">
          <input
            v-model="store.filters.search"
            type="search"
            placeholder="جستجو نام، موبایل، ایمیل..."
            class="h-10 rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-orange-400 lg:col-span-2"
            @keyup.enter="applyFilters"
          />
          <select
            v-model="store.filters.role"
            class="h-10 rounded-xl border border-slate-200 px-3 text-sm"
          >
            <option value="">همه نقش‌ها</option>
            <option value="jobseeker">کارجو</option>
            <option value="employer">کارفرما</option>
            <option value="operator">اپراتور</option>
            <option value="admin">ادمین</option>
          </select>
          <select
            v-model="store.filters.status"
            class="h-10 rounded-xl border border-slate-200 px-3 text-sm"
          >
            <option value="">همه وضعیت‌ها</option>
            <option value="active">فعال</option>
            <option value="blocked">مسدود</option>
          </select>
          <select
            v-model="store.filters.sort"
            class="h-10 rounded-xl border border-slate-200 px-3 text-sm"
          >
            <option value="desc">جدیدترین</option>
            <option value="asc">قدیمی‌ترین</option>
            <option value="wallet_desc">بیشترین موجودی</option>
          </select>
        </div>
        <div class="mt-3 flex gap-2">
          <button
            type="button"
            class="rounded-xl bg-orange-500 px-4 py-2 text-sm font-bold text-white hover:bg-orange-600"
            @click="applyFilters"
          >
            اعمال فیلتر
          </button>
          <button
            type="button"
            class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-200"
            @click="clearFilters"
          >
            پاک کردن
          </button>
        </div>
      </div>

      <DataTable
        :columns="columns"
        :rows="store.users"
        :loading="store.loading"
        actions
      >
        <template #cell-index="{ index }">
          {{ faNum(rowNumber(index)) }}
        </template>
        <template #cell-role="{ row }">
          <UserRoleBadge :role="row.role" />
        </template>
        <template #cell-subscription_plan="{ row }">
          {{ row.subscription_plan || 'رایگان' }}
        </template>
        <template #cell-wallet_balance="{ row }">
          {{ formatMoney(row.wallet_balance) }}
        </template>
        <template #cell-status="{ row }">
          <StatusBadge :status="row.status" />
        </template>
        <template #cell-created_at="{ row }">
          {{ formatDate(row.created_at) }}
        </template>
        <template #actions="{ row }">
          <div class="flex flex-wrap justify-end gap-1">
            <button
              class="rounded-lg bg-slate-100 px-2 py-1 text-[11px] font-bold text-slate-700"
              @click="openDetail(row)"
            >
              مشاهده
            </button>
            <button
              class="rounded-lg bg-orange-50 px-2 py-1 text-[11px] font-bold text-orange-700"
              @click="openEdit(row)"
            >
              ویرایش
            </button>
            <button
              class="rounded-lg bg-blue-50 px-2 py-1 text-[11px] font-bold text-blue-700"
              @click="askRole(row)"
            >
              ویرایش نقش
            </button>
            <button
              class="rounded-lg px-2 py-1 text-[11px] font-bold"
              :class="
                row.status === 'blocked'
                  ? 'bg-emerald-50 text-emerald-700'
                  : 'bg-amber-50 text-amber-700'
              "
              @click="askStatus(row)"
            >
              {{ row.status === 'blocked' ? 'فعال' : 'مسدود' }}
            </button>
            <button
              class="rounded-lg bg-red-50 px-2 py-1 text-[11px] font-bold text-red-600"
              @click="askDelete(row)"
            >
              حذف
            </button>
          </div>
        </template>
        <template #empty>
          <div class="py-4">
            <div
              class="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 text-2xl"
            >
              👤
            </div>
            <p class="mb-3 font-medium text-slate-600">کاربری یافت نشد</p>
            <button
              class="text-sm font-bold text-orange-500"
              @click="clearFilters"
            >
              پاک کردن فیلترها
            </button>
          </div>
        </template>
      </DataTable>

      <div
        v-if="store.meta.last_page > 0"
        class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white px-4 py-3 text-sm shadow-sm"
      >
        <p class="text-slate-500">
          نمایش {{ faNum(store.meta.from || 0) }} تا
          {{ faNum(store.meta.to || 0) }} از
          {{ faNum(store.meta.total || 0) }} کاربر
        </p>
        <div class="flex items-center gap-1">
          <button
            class="rounded-lg px-3 py-1.5 disabled:opacity-40"
            :disabled="(store.meta.current_page || 1) <= 1"
            @click="goPage((store.meta.current_page || 1) - 1)"
          >
            قبلی
          </button>
          <button
            v-for="page in visiblePages"
            :key="page"
            type="button"
            class="min-w-8 rounded-lg px-2.5 py-1.5 text-xs font-bold"
            :class="
              page === store.meta.current_page
                ? 'bg-orange-500 text-white'
                : 'bg-slate-100 text-slate-700'
            "
            @click="goPage(page)"
          >
            {{ faNum(page) }}
          </button>
          <button
            class="rounded-lg px-3 py-1.5 disabled:opacity-40"
            :disabled="
              (store.meta.current_page || 1) >= (store.meta.last_page || 1)
            "
            @click="goPage((store.meta.current_page || 1) + 1)"
          >
            بعدی
          </button>
        </div>
      </div>
    </div>

    <UserDetailModal
      ref="detailModal"
      :open="detailOpen"
      :user="store.selectedUser"
      :loading="store.detailLoading"
      :start-editing="detailStartEdit"
      :can-manage="auth.isAdmin"
      @close="closeDetail"
      @save="onSaveUser"
    />

    <UserCreateModal
      ref="createModal"
      :open="createOpen"
      :can-manage="auth.isAdmin"
      @close="createOpen = false"
      @created="onCreateUser"
    />

    <ConfirmDialog
      :open="confirm.open"
      :title="confirm.title"
      :message="confirm.message"
      :danger="confirm.danger"
      @cancel="confirm.open = false"
      @confirm="runConfirm"
    />

    <!-- Role picker mini dialog -->
    <div
      v-if="rolePicker.open"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
      @click.stop
    >
      <div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-xl">
        <h3 class="mb-3 text-base font-bold">تغییر نقش</h3>
        <select
          v-model="rolePicker.role"
          class="mb-4 h-10 w-full rounded-xl border border-slate-200 px-3 text-sm"
        >
          <option value="jobseeker">کارجو</option>
          <option value="employer">کارفرما</option>
          <option value="operator">اپراتور</option>
          <option value="admin">مدیر</option>
        </select>
        <div class="flex justify-end gap-2">
          <button
            class="rounded-xl bg-slate-100 px-3 py-2 text-sm"
            @click="rolePicker.open = false"
          >
            انصراف
          </button>
          <button
            class="rounded-xl bg-orange-500 px-3 py-2 text-sm font-bold text-white"
            @click="confirmRoleChange"
          >
            ذخیره
          </button>
        </div>
      </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import ConfirmDialog from '../components/ui/ConfirmDialog.vue'
import DataTable from '../components/ui/DataTable.vue'
import StatusBadge from '../components/ui/StatusBadge.vue'
import UserRoleBadge from '../components/ui/UserRoleBadge.vue'
import UserDetailModal from '../components/users/UserDetailModal.vue'
import UserCreateModal from '../components/users/UserCreateModal.vue'
import { useToast } from '../../composables/useToast'
import { useAdminAuthStore } from '../stores/auth'
import { useUsersStore } from '../stores/users'

const store = useUsersStore()
const toast = useToast()
const auth = useAdminAuthStore()

const detailOpen = ref(false)
const detailStartEdit = ref(false)
const detailModal = ref(null)
const createOpen = ref(false)
const createModal = ref(null)

function closeDetail() {
  detailOpen.value = false
  detailStartEdit.value = false
}
const rolePicker = reactive({ open: false, userId: null, role: 'jobseeker' })
const confirm = reactive({
  open: false,
  title: 'آیا مطمئن هستید؟',
  message: '',
  danger: true,
  action: null,
})

const columns = [
  { key: 'index', label: '#' },
  { key: 'name', label: 'نام' },
  { key: 'mobile', label: 'موبایل' },
  { key: 'role', label: 'نقش' },
  { key: 'subscription_plan', label: 'اشتراک' },
  { key: 'wallet_balance', label: 'موجودی کیف پول' },
  { key: 'status', label: 'وضعیت' },
  { key: 'created_at', label: 'تاریخ ثبت‌نام' },
]

const visiblePages = computed(() => {
  const current = store.meta.current_page || 1
  const last = store.meta.last_page || 1
  const pages = []
  const start = Math.max(1, current - 2)
  const end = Math.min(last, start + 4)
  for (let i = start; i <= end; i++) pages.push(i)
  return pages
})

function faNum(n) {
  return new Intl.NumberFormat('fa-IR').format(Number(n || 0))
}

function formatMoney(v) {
  return `${new Intl.NumberFormat('fa-IR').format(Number(v || 0))} ریال`
}

function formatDate(v) {
  if (!v) return '—'
  try {
    return new Date(v).toLocaleDateString('fa-IR')
  } catch {
    return String(v)
  }
}

function rowNumber(index) {
  const from = store.meta.from || 1
  return from + index
}

async function applyFilters() {
  await store.fetchUsers(1)
}

async function clearFilters() {
  store.resetFilters()
  await store.fetchUsers(1)
}

async function goPage(page) {
  await store.fetchUsers(page)
}

async function openDetail(row) {
  detailStartEdit.value = false
  detailOpen.value = true
  try {
    await store.fetchUser(row.id)
  } catch (e) {
    toast.error(e.response?.data?.message || 'بارگذاری جزئیات ناموفق بود.')
  }
}

async function openEdit(row) {
  detailStartEdit.value = true
  detailOpen.value = true
  try {
    await store.fetchUser(row.id)
  } catch (e) {
    toast.error(e.response?.data?.message || 'بارگذاری جزئیات ناموفق بود.')
  }
}

async function onSaveUser(payload) {
  if (!store.selectedUser) return
  try {
    await store.updateUser(store.selectedUser.id, payload)
    toast.success('کاربر به‌روزرسانی شد.')
    detailModal.value?.markSaved?.()
    detailStartEdit.value = false
  } catch (e) {
    const { apiErrorMessage } = await import('../../utils/format')
    const msg = apiErrorMessage(e, 'ذخیره ناموفق بود.')
    toast.error(msg)
    detailModal.value?.markFailed?.(msg)
  }
}

async function onCreateUser(payload) {
  try {
    await store.createUser(payload)
    toast.success('کاربر ایجاد شد.')
    createOpen.value = false
  } catch (e) {
    const { apiErrorMessage } = await import('../../utils/format')
    createModal.value?.markFailed?.(
      apiErrorMessage(e, 'ایجاد کاربر ناموفق بود.')
    )
  }
}

function askRole(row) {
  rolePicker.open = true
  rolePicker.userId = row.id
  rolePicker.role = row.role
}

function confirmRoleChange() {
  const userId = rolePicker.userId
  const role = rolePicker.role
  rolePicker.open = false
  confirm.open = true
  confirm.title = 'تغییر نقش کاربر'
  confirm.message = `نقش کاربر به «${role}» تغییر کند؟`
  confirm.danger = false
  confirm.action = async () => {
    try {
      await store.updateRole(userId, role)
      toast.success('نقش به‌روزرسانی شد.')
    } catch (e) {
      toast.error(e.response?.data?.message || 'خطا در تغییر نقش')
    }
  }
}

function askStatus(row) {
  const next = row.status === 'blocked' ? 'active' : 'blocked'
  confirm.open = true
  confirm.title = next === 'blocked' ? 'مسدود کردن کاربر' : 'فعال‌سازی کاربر'
  confirm.message =
    next === 'blocked'
      ? `کاربر «${row.name}» مسدود شود؟`
      : `کاربر «${row.name}» فعال شود؟`
  confirm.danger = next === 'blocked'
  confirm.action = async () => {
    try {
      await store.updateStatus(row.id, next)
      toast.success('وضعیت به‌روزرسانی شد.')
    } catch (e) {
      toast.error(e.response?.data?.message || 'خطا در تغییر وضعیت')
    }
  }
}

function askDelete(row) {
  confirm.open = true
  confirm.title = 'حذف کاربر'
  confirm.message = `کاربر «${row.name}» حذف شود؟ این عمل قابل بازگشت از پنل نیست.`
  confirm.danger = true
  confirm.action = async () => {
    try {
      await store.deleteUser(row.id)
      toast.success('کاربر حذف شد.')
      detailOpen.value = false
    } catch (e) {
      toast.error(e.response?.data?.message || 'حذف ناموفق بود')
    }
  }
}

async function runConfirm() {
  const action = confirm.action
  confirm.open = false
  if (action) await action()
}

onMounted(() => {
  store.fetchUsers(1).catch((e) => {
    toast.error(e.response?.data?.message || 'بارگذاری کاربران ناموفق بود.')
  })
})
</script>
