<template>
  <div v-if="open" class="fixed inset-0 z-50 flex justify-end bg-black/40">
    <aside class="flex h-full w-full max-w-md flex-col bg-white shadow-2xl">
      <div
        class="flex items-center justify-between border-b border-slate-100 px-5 py-4"
      >
        <h3 class="text-lg font-bold text-slate-800">
          {{ editing ? 'ویرایش کاربر' : 'جزئیات کاربر' }}
        </h3>
        <button
          class="rounded-lg px-2 py-1 text-slate-500 hover:bg-slate-100"
          @click="$emit('close')"
        >
          ✕
        </button>
      </div>

      <div v-if="loading" class="flex-1 p-8 text-center text-sm text-slate-500">
        در حال بارگذاری...
      </div>

      <div v-else-if="user" class="flex-1 space-y-4 overflow-y-auto p-5">
        <div class="flex items-center gap-3">
          <div
            class="flex h-14 w-14 items-center justify-center rounded-full bg-[#0f2744] text-lg font-bold text-white"
          >
            {{ initials }}
          </div>
          <div>
            <p class="font-bold text-slate-800">{{ form.name || '—' }}</p>
            <p class="text-xs text-slate-500" dir="ltr">{{ form.mobile }}</p>
          </div>
        </div>

        <div>
          <label class="label">نام</label>
          <input v-model="form.name" class="field" :readonly="!editing" />
        </div>
        <div>
          <label class="label">موبایل *</label>
          <input
            v-model="form.mobile"
            class="field"
            dir="ltr"
            :readonly="!editing"
          />
        </div>
        <div>
          <label class="label">ایمیل</label>
          <input
            v-model="form.email"
            class="field"
            dir="ltr"
            :readonly="!editing"
          />
        </div>
        <div>
          <label class="label">کد ملی</label>
          <input
            v-model="form.national_code"
            class="field"
            dir="ltr"
            :readonly="!editing"
          />
        </div>
        <div>
          <label class="label">نام کاربری (ورود ادمین)</label>
          <input
            v-model="form.username"
            class="field"
            dir="ltr"
            :readonly="!editing"
            placeholder="اختیاری"
          />
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="label">نقش</label>
            <select v-model="form.role" class="field" :disabled="!editing">
              <option value="jobseeker">کارجو</option>
              <option value="employer">کارفرما</option>
              <option value="operator">اپراتور</option>
              <option v-if="canManage || form.role === 'admin'" value="admin">مدیر</option>
            </select>
          </div>
          <div>
            <label class="label">وضعیت</label>
            <select v-model="form.status" class="field" :disabled="!editing">
              <option value="active">فعال</option>
              <option value="blocked">مسدود</option>
            </select>
          </div>
        </div>

        <OperatorPermissionsPicker
          v-if="form.role === 'operator' && canManage"
          v-model="form.operator_permissions"
          :disabled="!editing"
        />

        <div v-if="editing">
          <label class="label">رمز عبور جدید (اختیاری)</label>
          <PasswordInput
            v-model="form.password"
            input-class="field"
            autocomplete="new-password"
            placeholder="حداقل ۸ کاراکتر، حرف + عدد"
          />
        </div>

        <div class="grid grid-cols-2 gap-3 text-sm">
          <div class="rounded-xl bg-slate-50 p-3">
            <p class="text-xs text-slate-500">موجودی</p>
            <p class="mt-1 font-bold">{{ formatMoney(user.wallet_balance) }}</p>
          </div>
          <div class="rounded-xl bg-slate-50 p-3">
            <p class="text-xs text-slate-500">اشتراک</p>
            <p class="mt-1">{{ user.subscription_plan || 'رایگان' }}</p>
          </div>
        </div>

        <div class="grid grid-cols-3 gap-2 text-center text-sm">
          <div class="rounded-xl border border-slate-100 p-3">
            <p class="text-lg font-black text-slate-800">
              {{ user.attempts_count || 0 }}
            </p>
            <p class="text-[11px] text-slate-500">آزمون</p>
          </div>
          <div class="rounded-xl border border-slate-100 p-3">
            <p class="text-lg font-black text-slate-800">
              {{ user.purchases_count || 0 }}
            </p>
            <p class="text-[11px] text-slate-500">خرید</p>
          </div>
          <div class="rounded-xl border border-slate-100 p-3">
            <p class="text-xs font-bold text-slate-800">
              {{ formatDate(user.created_at) }}
            </p>
            <p class="text-[11px] text-slate-500">عضویت</p>
          </div>
        </div>

        <div>
          <h4 class="mb-2 text-sm font-bold text-slate-800">
            آخرین فعالیت آزمون
          </h4>
          <ul class="space-y-2">
            <li
              v-for="item in user.recent_attempts || []"
              :key="item.id"
              class="rounded-xl border border-slate-100 px-3 py-2 text-xs"
            >
              <p class="font-medium text-slate-700">
                {{ item.exam_title || '—' }}
              </p>
              <p class="mt-1 text-slate-500">
                نمره {{ item.score }} · {{ formatDate(item.created_at) }}
              </p>
            </li>
            <li
              v-if="!(user.recent_attempts || []).length"
              class="text-xs text-slate-400"
            >
              فعالیتی نیست
            </li>
          </ul>
        </div>

        <p v-if="error" class="text-sm text-red-500">{{ error }}</p>
      </div>

      <div
        v-if="user && !loading"
        class="flex gap-2 border-t border-slate-100 p-4"
      >
        <button
          v-if="!editing"
          type="button"
          class="flex-1 rounded-xl bg-orange-500 py-2.5 text-sm font-bold text-white"
          @click="editing = true"
        >
          ویرایش
        </button>
        <template v-else>
          <button
            type="button"
            class="flex-1 rounded-xl bg-slate-100 py-2.5 text-sm font-bold"
            @click="cancelEdit"
          >
            انصراف
          </button>
          <button
            type="button"
            class="flex-1 rounded-xl bg-orange-500 py-2.5 text-sm font-bold text-white disabled:opacity-50"
            :disabled="saving"
            @click="save"
          >
            {{ saving ? '...' : 'ذخیره' }}
          </button>
        </template>
      </div>
    </aside>
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import PasswordInput from '../../../components/PasswordInput.vue'
import { DEFAULT_OPERATOR_PERMISSIONS } from '../../permissions'
import OperatorPermissionsPicker from './OperatorPermissionsPicker.vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  user: { type: Object, default: null },
  loading: { type: Boolean, default: false },
  startEditing: { type: Boolean, default: false },
  canManage: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'save'])

const editing = ref(false)
const saving = ref(false)
const error = ref('')
const form = reactive({
  name: '',
  mobile: '',
  email: '',
  national_code: '',
  username: '',
  role: 'jobseeker',
  operator_permissions: [...DEFAULT_OPERATOR_PERMISSIONS],
  status: 'active',
  password: '',
})

watch(
  () => [props.open, props.user, props.startEditing],
  () => {
    if (!props.open) {
      editing.value = false
      error.value = ''
      return
    }
    syncForm()
    editing.value = Boolean(props.startEditing)
  },
  { immediate: true }
)

function syncForm() {
  const u = props.user || {}
  form.name = u.name || ''
  form.mobile = u.mobile || ''
  form.email = u.email || ''
  form.national_code = u.national_code || ''
  form.username = u.username || ''
  form.role = u.role || 'jobseeker'
  form.operator_permissions = Array.isArray(u.operator_permissions)
    ? [...u.operator_permissions]
    : [...DEFAULT_OPERATOR_PERMISSIONS]
  form.status = u.status || 'active'
  form.password = ''
}

function cancelEdit() {
  editing.value = false
  error.value = ''
  syncForm()
}

async function save() {
  error.value = ''
  if (!/^09\d{9}$/.test(form.mobile)) {
    error.value = 'شماره موبایل معتبر نیست.'
    return
  }
  saving.value = true
  const payload = {
    name: form.name || null,
    mobile: form.mobile,
    email: form.email || null,
    national_code: form.national_code || null,
    username: form.username || null,
    role: form.role,
    status: form.status,
  }
  if (form.role === 'operator' && props.canManage) {
    payload.operator_permissions = form.operator_permissions
  }
  if (form.password) payload.password = form.password
  emit('save', payload)
}

function markSaved() {
  saving.value = false
  editing.value = false
  error.value = ''
}

function markFailed(message) {
  saving.value = false
  error.value = message || 'ذخیره ناموفق بود.'
}

defineExpose({ markSaved, markFailed })

const initials = computed(() => {
  const name = form.name || form.mobile || '?'
  return String(name).trim().charAt(0)
})

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
</script>

<style scoped>
.label {
  @apply mb-1 block text-xs text-slate-500;
}
.field {
  @apply h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none read-only:bg-slate-50 focus:border-orange-400 disabled:bg-slate-50;
}
</style>
