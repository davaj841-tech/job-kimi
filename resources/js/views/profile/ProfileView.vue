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
            {{
              auth.user?.subscription_plan &&
              (auth.user.subscription_plan as any).name
                ? (auth.user.subscription_plan as any).name
                : 'رایگان'
            }}
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
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h3 class="text-sm font-bold dark:text-white">اطلاعات شخصی</h3>
          <button
            type="button"
            class="rounded-xl border border-brand/30 bg-brand-soft px-3 py-1.5 text-xs font-bold text-brand transition hover:bg-brand hover:text-white disabled:opacity-60"
            :disabled="fillingResume"
            @click="fillFromResume"
          >
            {{ fillingResume ? '...' : 'پر کردن از رزومه' }}
          </button>
        </div>
        <form class="space-y-3 text-sm" @submit.prevent="saveProfile">
          <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-xs text-ink-muted"
                >نام و نام خانوادگی</label
              >
              <input
                v-model="form.name"
                class="input-field"
                placeholder="نام و نام خانوادگی"
              />
            </div>
            <div>
              <label class="mb-1 block text-xs text-ink-muted">ایمیل</label>
              <input
                v-model="form.email"
                type="email"
                class="input-field text-left"
                dir="ltr"
                placeholder="you@example.com"
              />
            </div>
            <div
              class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 dark:bg-slate-800"
            >
              <span class="text-ink-muted">موبایل</span>
              <span class="font-medium" dir="ltr">{{
                auth.user?.mobile || '—'
              }}</span>
            </div>
            <div>
              <label class="mb-1 block text-xs text-ink-muted">تلفن منزل</label>
              <input
                v-model="form.home_phone"
                class="input-field text-left"
                dir="ltr"
                maxlength="11"
                placeholder="02112345678"
              />
            </div>
            <div>
              <label class="mb-1 block text-xs text-ink-muted"
                >وضعیت سربازی</label
              >
              <select v-model="form.military_status" class="input-field">
                <option value="">انتخاب کنید</option>
                <option v-for="m in militaryOptions" :key="m" :value="m">
                  {{ m }}
                </option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-xs text-ink-muted"
                >سابقه بیمه</label
              >
              <input
                v-model="form.insurance_history"
                class="input-field"
                placeholder="مثلاً ۵ سال تامین اجتماعی"
              />
            </div>
            <div>
              <label class="mb-1 block text-xs text-ink-muted">کد ملی</label>
              <input
                :value="form.national_code"
                class="input-field text-left"
                dir="ltr"
                maxlength="10"
                inputmode="numeric"
                placeholder="۱۰ رقم معتبر ایران"
                @input="onNationalCodeInput"
              />
              <p
                v-if="
                  form.national_code.length === 10 &&
                  !isValidNationalCode(form.national_code)
                "
                class="mt-1 text-xs text-brand"
              >
                کد ملی معتبر نیست
              </p>
            </div>
            <JalaliBirthInput v-model="form.birth_date" />
            <div>
              <label class="mb-1 block text-xs text-ink-muted"
                >استان محل تولد</label
              >
              <select
                v-model="form.birth_province"
                class="input-field"
                @change="form.birth_city = ''"
              >
                <option value="">انتخاب استان</option>
                <option v-for="p in IRAN_PROVINCES" :key="p" :value="p">
                  {{ p }}
                </option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-xs text-ink-muted"
                >شهرستان محل تولد</label
              >
              <select
                v-model="form.birth_city"
                class="input-field"
                :disabled="!form.birth_province"
              >
                <option value="">انتخاب شهرستان</option>
                <option v-for="c in cityOptions" :key="c" :value="c">
                  {{ c }}
                </option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-xs text-ink-muted"
                >وضعیت تاهل</label
              >
              <select v-model="form.marital_status" class="input-field">
                <option value="">انتخاب کنید</option>
                <option value="single">مجرد</option>
                <option value="married">متاهل</option>
                <option value="divorced">مطلقه / متعلقه</option>
              </select>
            </div>
            <SearchSelect
              v-model="form.field_of_study"
              label="رشته تحصیلی"
              placeholder="جستجوی رشته…"
              :options="ACADEMIC_FIELDS"
            />
            <div class="md:col-span-2">
              <label class="mb-1 block text-xs text-ink-muted"
                >آدرس محل سکونت</label
              >
              <input v-model="form.address" class="input-field" />
            </div>
            <div>
              <label class="mb-1 block text-xs text-ink-muted">کد پستی</label>
              <input
                :value="form.postal_code"
                class="input-field text-left"
                dir="ltr"
                maxlength="10"
                inputmode="numeric"
                placeholder="۱۰ رقم"
                @input="onPostalCodeInput"
              />
            </div>
            <div class="md:col-span-2">
              <PhotoUpload v-model="form.photo" />
            </div>
          </div>
          <button type="submit" class="btn-primary" :disabled="saving">
            {{ saving ? '...' : 'ذخیره تغییرات' }}
          </button>
        </form>
      </Card>

      <Card
        v-else-if="activeTab === 'password'"
        key="password"
        class="space-y-3 p-5"
      >
        <form class="space-y-3 text-sm" @submit.prevent="changePassword">
          <div>
            <label class="mb-1 block text-xs text-ink-muted">رمز فعلی</label>
            <PasswordInput
              v-model="passwordForm.current"
              input-class="input-field"
              autocomplete="current-password"
            />
          </div>
          <div>
            <label class="mb-1 block text-xs text-ink-muted">رمز جدید</label>
            <PasswordInput
              v-model="passwordForm.password"
              input-class="input-field"
              autocomplete="new-password"
            />
            <PasswordRulesHint :password="passwordForm.password" />
          </div>
          <div>
            <label class="mb-1 block text-xs text-ink-muted"
              >تکرار رمز جدید</label
            >
            <PasswordInput
              v-model="passwordForm.password_confirmation"
              input-class="input-field"
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

      <Card v-else key="activity" class="space-y-5 p-5">
        <div v-if="activityLoading" class="space-y-2">
          <Skeleton v-for="i in 5" :key="i" class="h-12 rounded-xl" />
        </div>
        <template v-else>
          <section>
            <h3 class="mb-3 text-sm font-bold dark:text-white">
              تاریخچه ورود و خروج
            </h3>
            <EmptyState
              v-if="!loginSessions.length"
              title="ورود ثبت نشده"
              description="پس از ورود به حساب، تاریخ و ساعت ورود و خروج اینجا نمایش داده می‌شود."
            />
            <ul v-else class="space-y-2 text-sm">
              <li
                v-for="row in loginSessions"
                :key="row.id"
                class="rounded-xl bg-slate-50 px-3 py-2.5 dark:bg-slate-700/40"
              >
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <span class="font-medium text-ink dark:text-slate-100">
                    ورود:
                    {{ row.logged_in_label || formatDate(row.logged_in_at) }}
                  </span>
                  <Badge :variant="row.is_active ? 'success' : 'info'">
                    {{ row.is_active ? 'نشست فعال' : 'خروج' }}
                  </Badge>
                </div>
                <div
                  class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink-muted dark:text-slate-400"
                >
                  <span>
                    خروج:
                    {{
                      row.is_active
                        ? 'هنوز خارج نشده'
                        : row.logged_out_label || formatDate(row.logged_out_at)
                    }}
                  </span>
                  <span>مدت حضور: {{ row.duration_label || '—' }}</span>
                </div>
              </li>
            </ul>
          </section>

          <section v-if="monthlySummary.length">
            <h3 class="mb-2 text-sm font-bold dark:text-white">
              خلاصه ماه‌های گذشته
            </h3>
            <p class="mb-3 text-xs text-ink-muted">
              پس از اتمام هر ماه، مجموع ورودها و مدت حضور در آن ماه اینجا
              می‌آید.
            </p>
            <ul class="space-y-2 text-sm">
              <li
                v-for="m in monthlySummary"
                :key="`${m.year}-${m.month}`"
                class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-surface-line px-3 py-2 dark:border-slate-600"
              >
                <span class="font-medium dark:text-white">{{ m.label }}</span>
                <span class="text-xs text-ink-muted">
                  {{ m.sessions_count }} ورود ·
                  {{ formatDurationFa(m.total_duration_seconds) }}
                </span>
              </li>
            </ul>
          </section>
        </template>
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
import type { AxiosError } from 'axios'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '../../api/client'
import PasswordInput from '../../components/PasswordInput.vue'
import PasswordRulesHint from '../../components/auth/PasswordRulesHint.vue'
import EmptyState from '../../components/EmptyState.vue'
import JalaliBirthInput from '../../components/resume/JalaliBirthInput.vue'
import PhotoUpload from '../../components/resume/PhotoUpload.vue'
import SearchSelect from '../../components/resume/SearchSelect.vue'
import Badge from '../../components/ui/Badge.vue'
import Card from '../../components/ui/Card.vue'
import Skeleton from '../../components/ui/Skeleton.vue'
import { ACADEMIC_FIELDS } from '../../data/academicFields'
import { useToast } from '../../composables/useToast'
import { useAuthStore, type User } from '../../stores/auth'
import {
  apiErrorMessage,
  formatDate,
  formatPrice,
  unwrapItem,
  unwrapList,
} from '../../utils/format'
import { IRAN_PROVINCES, citiesForProvince } from '../../utils/iranCities'
import { isValidNationalCode } from '../../utils/validators'

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
const fillingResume = ref(false)
const activityLoading = ref(false)

type LoginSessionRow = {
  id: number
  logged_in_at?: string | null
  logged_out_at?: string | null
  logged_in_label?: string | null
  logged_out_label?: string | null
  duration_seconds?: number
  duration_label?: string | null
  is_active?: boolean
}

type MonthlyRow = {
  year: number
  month: number
  label: string
  sessions_count: number
  total_duration_seconds: number
}

type ActivityPayload = {
  sessions?: LoginSessionRow[]
  monthly?: MonthlyRow[]
  items?: unknown[]
}

type ProfileApiError = {
  message?: string
}

const loginSessions = ref<LoginSessionRow[]>([])
const monthlySummary = ref<MonthlyRow[]>([])
const form = reactive({
  name: '',
  email: '',
  national_code: '',
  home_phone: '',
  military_status: '',
  insurance_history: '',
  birth_date: '',
  birth_province: '',
  birth_city: '',
  marital_status: '',
  field_of_study: '',
  address: '',
  postal_code: '',
  photo: '',
})
const militaryOptions = [
  'پایان خدمت',
  'معافیت دائم',
  'معافیت تحصیلی',
  'در حال خدمت',
  'مشمول',
  'غیرمشمول',
]
const cityOptions = computed(() => citiesForProvince(form.birth_province || ''))
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
  const u: User = auth.user || ({ id: 0, name: null, mobile: '' } as User)
  form.name = u.name || ''
  form.email = u.email || ''
  form.national_code = String(u.national_code || '')
    .replace(/\D/g, '')
    .slice(0, 10)
  form.home_phone = u.home_phone || ''
  form.military_status = u.military_status || ''
  form.insurance_history = u.insurance_history || ''
  form.birth_date = u.birth_date || ''
  form.birth_province = u.birth_province || u.province || ''
  form.birth_city = u.birth_city || ''
  form.marital_status = u.marital_status || ''
  form.field_of_study = u.field_of_study || ''
  form.address = u.address || ''
  form.postal_code = String(u.postal_code || '')
    .replace(/\D/g, '')
    .slice(0, 10)
  form.photo = u.avatar || ''
}

function sanitizeDigits(value: string, max: number): string {
  return value.replace(/\D/g, '').slice(0, max)
}

function onNationalCodeInput(event: Event): void {
  const target = event.target as HTMLInputElement | null
  form.national_code = sanitizeDigits(target?.value || '', 10)
}

function onPostalCodeInput(event: Event): void {
  const target = event.target as HTMLInputElement | null
  form.postal_code = sanitizeDigits(target?.value || '', 10)
}

async function saveProfile() {
  saving.value = true
  try {
    const national = form.national_code || ''
    if (national && !isValidNationalCode(national)) {
      toast.error('کد ملی واردشده معتبر نیست.')
      return
    }
    await auth.updateProfile({
      name: form.name || null,
      email: form.email || null,
      province: form.birth_province || null,
      national_code: national || null,
      home_phone: form.home_phone || null,
      military_status: form.military_status || null,
      insurance_history: form.insurance_history || null,
      birth_date: form.birth_date || null,
      birth_province: form.birth_province || null,
      birth_city: form.birth_city || null,
      marital_status: form.marital_status || null,
      field_of_study: form.field_of_study || null,
      address: form.address || null,
      postal_code: form.postal_code || null,
      photo: form.photo?.startsWith('data:image') ? form.photo : undefined,
    })
    toast.success('پروفایل ذخیره شد')
    syncForm()
  } catch (e) {
    toast.error(
      apiErrorMessage(e as AxiosError<ProfileApiError>) || 'ذخیره ناموفق بود'
    )
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
  } catch (e) {
    toast.error(
      apiErrorMessage(e as AxiosError<ProfileApiError>) ||
        'تغییر رمز ناموفق بود'
    )
  } finally {
    pwdSaving.value = false
  }
}

async function fillFromResume() {
  fillingResume.value = true
  try {
    const { data } = await api.get('/resumes')
    const resumes = unwrapList(data) as Array<{
      is_active?: boolean
      updated_at?: string
      data?: { personal?: Record<string, string | null | undefined> }
    }>

    const pick =
      resumes.find((r) => r.is_active && r.data?.personal) ||
      [...resumes].sort((a, b) =>
        String(b.updated_at || '').localeCompare(String(a.updated_at || ''))
      )[0]

    const p = pick?.data?.personal
    if (!p || !Object.values(p).some((v) => String(v || '').trim())) {
      toast.error('رزومه‌ای با اطلاعات شخصی یافت نشد.')
      return
    }

    if (p.full_name) form.name = String(p.full_name)
    if (p.email) form.email = String(p.email)
    if (p.home_phone) form.home_phone = String(p.home_phone)
    if (p.military_status) form.military_status = String(p.military_status)
    if (p.insurance_history)
      form.insurance_history = String(p.insurance_history)
    const nc = String(p.national_code || '')
      .replace(/\D/g, '')
      .slice(0, 10)
    if (nc) form.national_code = nc
    if (p.birth_date) form.birth_date = String(p.birth_date)
    if (p.birth_province) form.birth_province = String(p.birth_province)
    if (p.birth_city) form.birth_city = String(p.birth_city)
    if (p.marital_status) form.marital_status = String(p.marital_status)
    if (p.field_of_study) form.field_of_study = String(p.field_of_study)
    if (p.address) form.address = String(p.address)
    const pc = String(p.postal_code || '')
      .replace(/\D/g, '')
      .slice(0, 10)
    if (pc) form.postal_code = pc
    if (p.photo) form.photo = String(p.photo)

    toast.success('اطلاعات از رزومه پر شد. برای ذخیره دکمه ذخیره را بزنید.')
  } catch (e) {
    toast.error(
      apiErrorMessage(e as AxiosError<ProfileApiError>) ||
        'خواندن رزومه ناموفق بود'
    )
  } finally {
    fillingResume.value = false
  }
}

function formatDurationFa(seconds: number): string {
  const s = Math.max(0, Number(seconds) || 0)
  if (s < 60) return `${s} ثانیه`
  const hours = Math.floor(s / 3600)
  const minutes = Math.floor((s % 3600) / 60)
  const parts: string[] = []
  if (hours) parts.push(`${hours} ساعت`)
  if (minutes) parts.push(`${minutes} دقیقه`)
  return parts.join(' و ') || 'کمتر از یک دقیقه'
}

async function loadActivity() {
  activityLoading.value = true
  try {
    const { data } = await api.get('/user/activity')
    const payload = unwrapItem<ActivityPayload>(data)
    loginSessions.value = payload?.sessions || []
    monthlySummary.value = payload?.monthly || []
  } catch {
    loginSessions.value = []
    monthlySummary.value = []
  } finally {
    activityLoading.value = false
  }
}

watch(activeTab, (tab) => {
  if (tab === 'activity') void loadActivity()
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
