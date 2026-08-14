<template>
  <div class="space-y-6">
    <Card class="overflow-hidden">
      <div
        class="bg-gradient-to-l from-desk-dark to-desk-blue px-5 py-6 text-white"
      >
        <div class="flex items-center gap-4">
          <div
            class="flex h-16 w-16 items-center justify-center rounded-full bg-white/15 text-2xl font-black"
          >
            {{ initials }}
          </div>
          <div class="min-w-0">
            <p class="truncate text-lg font-bold">
              {{ auth.user?.name || 'کاربر جاب‌آزمون' }}
            </p>
            <p class="mt-0.5 text-xs text-white/70" dir="ltr">
              {{
                auth.user?.username
                  ? '@' + auth.user.username
                  : auth.user?.mobile
              }}
            </p>
          </div>
        </div>
      </div>
      <div
        class="grid grid-cols-3 divide-x divide-surface-line dark:divide-slate-700"
      >
        <div class="p-3 text-center">
          <p class="text-[11px] text-ink-muted dark:text-slate-400">موجودی</p>
          <p class="mt-1 text-xs font-black text-brand">
            {{ formatPrice(auth.user?.wallet_balance) }}
          </p>
        </div>
        <div class="p-3 text-center">
          <p class="text-[11px] text-ink-muted dark:text-slate-400">اشتراک</p>
          <p class="mt-1 text-xs font-black dark:text-white">
            {{ auth.user?.subscription_plan && (auth.user.subscription_plan as any).name
              ? (auth.user.subscription_plan as any).name
              : 'رایگان' }}
          </p>
        </div>
        <div class="p-3 text-center">
          <p class="text-[11px] text-ink-muted dark:text-slate-400">وضعیت</p>
          <Badge
            class="mt-1"
            :variant="auth.user?.is_verified ? 'success' : 'warning'"
          >
            {{ auth.user?.is_verified ? 'تایید شده' : 'در انتظار' }}
          </Badge>
        </div>
      </div>
    </Card>

    <div
      class="flex flex-wrap gap-2 border-b border-surface-line pb-2 dark:border-slate-700"
    >
      <button
        v-for="tab in tabs"
        :key="tab.id"
        type="button"
        class="rounded-xl px-3 py-2 text-sm font-medium transition"
        :class="
          activeTab === tab.id
            ? 'bg-brand text-white'
            : 'text-ink-muted hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
        "
        @click="activeTab = tab.id"
      >
        {{ tab.label }}
      </button>
    </div>

    <Transition
      mode="out-in"
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 translate-y-1"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <Card v-if="activeTab === 'profile'" key="profile" class="space-y-3 p-5">
        <form class="space-y-3 text-sm" @submit.prevent="saveProfile">
          <div>
            <label class="mb-1 block text-xs text-ink-muted">نام</label>
            <input
              v-model="form.name"
              class="input-field"
              placeholder="نام و نام خانوادگی"
            />
          </div>
          <div class="flex items-center justify-between">
            <span class="text-ink-muted">موبایل</span>
            <span class="font-medium" dir="ltr">{{
              auth.user?.mobile || '—'
            }}</span>
          </div>
          <div>
            <label class="mb-1 block text-xs text-ink-muted">ایمیل</label>
            <input
              v-model="form.email"
              type="email"
              class="input-field text-left"
              dir="ltr"
              lang="en"
              inputmode="email"
              autocomplete="email"
              autocapitalize="off"
              spellcheck="false"
              placeholder="you@example.com"
            />
          </div>
          <div>
            <label class="mb-1 block text-xs text-ink-muted">استان *</label>
            <select v-model="form.province" class="input-field" required>
              <option value="">انتخاب استان</option>
              <option v-for="p in IRAN_PROVINCES" :key="p" :value="p">
                {{ p }}
              </option>
            </select>
          </div>
          <button
            type="submit"
            class="btn-primary"
            :disabled="saving || !form.province"
          >
            {{ saving ? '...' : 'ذخیره تغییرات' }}
          </button>
        </form>
      </Card>

      <Card v-else-if="activeTab === 'password'" key="password" class="space-y-3 p-5">
        <form class="space-y-3 text-sm" @submit.prevent="changePassword">
          <div>
            <label class="mb-1 block text-xs text-ink-muted">رمز فعلی</label>
            <input
              v-model="passwordForm.current"
              type="password"
              class="input-field"
              autocomplete="current-password"
            />
          </div>
          <div>
            <label class="mb-1 block text-xs text-ink-muted">رمز جدید</label>
            <input
              v-model="passwordForm.password"
              type="password"
              class="input-field"
              lang="en"
              autocomplete="new-password"
            />
            <PasswordRulesHint :password="passwordForm.password" />
          </div>
          <div>
            <label class="mb-1 block text-xs text-ink-muted">تکرار رمز جدید</label>
            <input
              v-model="passwordForm.password_confirmation"
              type="password"
              class="input-field"
              autocomplete="new-password"
            />
          </div>
          <button type="submit" class="btn-primary" :disabled="pwdSaving">
            {{ pwdSaving ? '...' : 'تغییر رمز عبور' }}
          </button>
        </form>
      </Card>

      <Card v-else-if="activeTab === 'notifications'" key="notif" class="p-5">
        <p class="mb-3 text-sm text-ink-muted dark:text-slate-400">
          تنظیمات کامل اعلان‌ها را در صفحه اختصاصی ویرایش کنید.
        </p>
        <RouterLink
          to="/profile/notifications"
          class="inline-flex rounded-xl bg-brand px-4 py-2 text-sm font-bold text-white"
        >
          رفتن به تنظیمات اعلان‌ها
        </RouterLink>
      </Card>

      <Card v-else key="activity" class="p-5">
        <h3 class="mb-3 text-sm font-bold dark:text-white">فعالیت اخیر</h3>
        <div v-if="activityLoading" class="space-y-2">
          <Skeleton v-for="i in 4" :key="i" class="h-10 rounded-xl" />
        </div>
        <EmptyState
          v-else-if="!activity.length"
          title="فعالیتی ثبت نشده"
          description="پس از استفاده از سایت، فعالیت اینجا دیده می‌شود."
        />
        <ul v-else class="space-y-2 text-sm">
          <li
            v-for="(row, idx) in activity"
            :key="idx"
            class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 dark:bg-slate-700/40"
          >
            <span class="truncate text-ink dark:text-slate-200">{{
              row.page_url || row.route_name || 'بازدید'
            }}</span>
            <span class="shrink-0 text-xs text-ink-muted">{{
              formatDate(row.created_at)
            }}</span>
          </li>
        </ul>
      </Card>
    </Transition>

    <button
      type="button"
      class="w-full rounded-2xl border border-brand/30 bg-brand-soft px-4 py-3 text-sm font-bold text-brand transition hover:bg-brand hover:text-white dark:bg-brand/10"
      @click="onLogout"
    >
      خروج از حساب
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '../../api/client'
import PasswordRulesHint from '../../components/auth/PasswordRulesHint.vue'
import EmptyState from '../../components/EmptyState.vue'
import Badge from '../../components/ui/Badge.vue'
import Card from '../../components/ui/Card.vue'
import Skeleton from '../../components/ui/Skeleton.vue'
import { useToast } from '../../composables/useToast'
import { useAuthStore } from '../../stores/auth'
import { apiErrorMessage, formatDate, formatPrice, unwrapItem } from '../../utils/format'
import { IRAN_PROVINCES } from '../../utils/provinces'

const auth = useAuthStore()
const router = useRouter()
const toast = useToast()

const activeTab = ref('profile')
const tabs = [
  { id: 'profile', label: 'اطلاعات شخصی' },
  { id: 'password', label: 'رمز عبور' },
  { id: 'notifications', label: 'اعلان‌ها' },
  { id: 'activity', label: 'تاریخچه ورود' },
]

const saving = ref(false)
const pwdSaving = ref(false)
const activityLoading = ref(false)
const activity = ref<any[]>([])
const form = reactive({ name: '', email: '', province: '' })
const passwordForm = reactive({
  current: '',
  password: '',
  password_confirmation: '',
})

const initials = computed(() => {
  const n = auth.user?.name || 'ک'
  return n.trim().charAt(0)
})

function syncForm() {
  form.name = auth.user?.name || ''
  form.email = auth.user?.email || ''
  form.province = auth.user?.province || ''
}

async function saveProfile() {
  saving.value = true
  try {
    await auth.updateProfile({
      name: form.name || null,
      email: form.email || null,
      province: form.province,
    })
    toast.success('پروفایل ذخیره شد')
    syncForm()
  } catch (e: any) {
    toast.error(apiErrorMessage(e) || 'ذخیره ناموفق بود')
  } finally {
    saving.value = false
  }
}

async function changePassword() {
  pwdSaving.value = true
  try {
    await api.put('/auth/password', passwordForm)
    toast.success('رمز عبور تغییر کرد')
    passwordForm.current = ''
    passwordForm.password = ''
    passwordForm.password_confirmation = ''
  } catch (e: any) {
    toast.error(apiErrorMessage(e) || 'تغییر رمز ناموفق بود')
  } finally {
    pwdSaving.value = false
  }
}

async function loadActivity() {
  activityLoading.value = true
  try {
    const { data } = await api.get('/user/activity')
    activity.value = (unwrapItem(data) as any)?.items || []
  } catch {
    activity.value = []
  } finally {
    activityLoading.value = false
  }
}

watch(activeTab, (tab) => {
  if (tab === 'activity' && !activity.value.length) void loadActivity()
})

async function onLogout() {
  await auth.logout()
  router.push('/login')
}

onMounted(async () => {
  try {
    await auth.fetchMe()
  } catch {
    // ignore
  }
  syncForm()
})
</script>
