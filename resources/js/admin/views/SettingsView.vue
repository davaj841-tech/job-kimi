<template>
  <AdminLayout>
    <div class="space-y-5">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-gray-800">تنظیمات سیستم</h1>
        <div class="flex items-center gap-2">
          <span v-if="store.dirty" class="text-xs text-orange-600"
            >تغییرات ذخیره‌نشده</span
          >
          <button class="btn-orange" :disabled="store.saving" @click="save">
            {{ store.saving ? '...' : 'ذخیره تنظیمات' }}
          </button>
        </div>
      </div>

      <div class="flex flex-wrap gap-2">
        <button
          v-for="s in sections"
          :key="s.key"
          class="btn-muted"
          :class="active === s.key ? 'ring-2 ring-orange-400' : ''"
          @click="switchSection(s.key)"
        >
          {{ s.label }}
        </button>
      </div>

      <div class="rounded-xl bg-white p-5 shadow-sm">
        <div
          v-if="store.loading"
          class="py-10 text-center text-sm text-slate-400"
        >
          در حال بارگذاری...
        </div>
        <SettingsForm
          v-else
          v-model="form"
          :fields="currentFields"
          @dirty="store.markDirty()"
          @upload="onUpload"
        />
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, onMounted, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
import AdminLayout from '../components/layout/AdminLayout.vue'
import SettingsForm from '../components/settings/SettingsForm.vue'
import { apiErrorMessage } from '../../utils/format'
import { useToast } from '../../composables/useToast'
import { applySiteTheme } from '../../composables/useSiteTheme'
import { themePreset } from '../../theme/presets'
import { useSettingsStore } from '../stores/settings'

const store = useSettingsStore()
const toast = useToast()
const active = ref('general')
const form = reactive({})

const sections = [
  { key: 'general', label: 'عمومی' },
  { key: 'mail', label: 'ایمیل SMTP' },
  { key: 'theme', label: 'رنگ و تم' },
  { key: 'homepage', label: 'طرح و تم سایت' },
  { key: 'seo', label: 'سئو' },
  { key: 'payment', label: 'پرداخت' },
  { key: 'sms', label: 'پیامک' },
  { key: 'ai', label: 'هوش مصنوعی' },
  { key: 'security', label: 'امنیت' },
  { key: 'social', label: 'شبکه‌های اجتماعی' },
]

const fieldMap = {
  general: [
    { key: 'site_name', label: 'نام سایت', type: 'text' },
    { key: 'site_description', label: 'توضیحات سایت', type: 'textarea' },
    { key: 'site_logo', label: 'لوگو', type: 'image', uploadType: 'logo' },
    {
      key: 'site_favicon',
      label: 'فاوآیکون',
      type: 'image',
      uploadType: 'favicon',
    },
    { key: 'support_email', label: 'ایمیل پشتیبانی', type: 'email', ltr: true },
    { key: 'support_phone', label: 'شماره تماس', type: 'text', ltr: true },
    { key: 'onboarding_enabled', label: 'نمایش تور آشنایی', type: 'toggle' },
  ],
  mail: [
    { key: 'smtp_host', label: 'SMTP Host', type: 'text', ltr: true },
    { key: 'smtp_port', label: 'SMTP Port', type: 'number' },
    { key: 'smtp_username', label: 'Username', type: 'text', ltr: true },
    { key: 'smtp_password', label: 'Password', type: 'text', ltr: true },
    {
      key: 'smtp_from_address',
      label: 'From Address',
      type: 'email',
      ltr: true,
    },
    { key: 'smtp_from_name', label: 'From Name', type: 'text' },
  ],
  theme: [
    {
      key: 'primary_color',
      label: 'رنگ اصلی (دکمه‌ها، تأکید، نوار فعال)',
      type: 'color',
    },
    {
      key: 'secondary_color',
      label: 'رنگ ثانویه (هدر، سایدبار، تیترها)',
      type: 'color',
    },
    {
      key: 'logo_light',
      label: 'لوگوی روشن',
      type: 'image',
      uploadType: 'logo_light',
    },
    {
      key: 'logo_dark',
      label: 'لوگوی تاریک',
      type: 'image',
      uploadType: 'logo_dark',
    },
  ],
  homepage: [
    {
      key: 'homepage_layout',
      label: 'تم کل سایت (صفحه اول، صفحات جانبی، موبایل، داشبورد و پنل)',
      type: 'homepage-layout',
    },
  ],
  seo: [
    { key: 'meta_title', label: 'Meta Title پیش‌فرض', type: 'text' },
    { key: 'meta_description', label: 'Meta Description', type: 'textarea' },
    { key: 'meta_keywords', label: 'کلمات کلیدی', type: 'text' },
    {
      key: 'google_analytics_id',
      label: 'Google Analytics ID',
      type: 'text',
      ltr: true,
    },
    {
      key: 'google_tag_manager',
      label: 'Google Tag Manager',
      type: 'text',
      ltr: true,
    },
  ],
  payment: [
    {
      key: 'payment_gateway',
      label: 'درگاه پیش‌فرض',
      type: 'select',
      options: [
        { value: 'zarinpal', label: 'زرین‌پال' },
        { value: 'nextpay', label: 'نکست‌پی' },
        { value: 'idpay', label: 'آیدی‌پی' },
      ],
    },
    {
      key: 'zarinpal_merchant_id',
      label: 'شناسه پذیرنده زرین‌پال',
      type: 'text',
      ltr: true,
    },
    { key: 'zarinpal_sandbox', label: 'Sandbox زرین‌پال', type: 'toggle' },
    {
      key: 'nextpay_api_key',
      label: 'API Key نکست‌پی',
      type: 'text',
      ltr: true,
    },
    { key: 'nextpay_active', label: 'فعال بودن نکست‌پی', type: 'toggle' },
    { key: 'idpay_api_key', label: 'API Key آیدی‌پی', type: 'text', ltr: true },
    { key: 'idpay_active', label: 'فعال بودن آیدی‌پی', type: 'toggle' },
    { key: 'idpay_sandbox', label: 'Sandbox آیدی‌پی', type: 'toggle' },
    { key: 'min_wallet_charge', label: 'حداقل شارژ کیف پول', type: 'number' },
  ],
  sms: [
    {
      key: 'sms_gateway',
      label: 'سرویس پیامک',
      type: 'select',
      options: [
        { value: 'kavenegar', label: 'Kavenegar' },
        { value: 'melipayamak', label: 'MeliPayamak' },
      ],
    },
    { key: 'sms_api_key', label: 'API Key', type: 'text', ltr: true },
    { key: 'sms_otp_template', label: 'قالب پیام OTP', type: 'textarea' },
    {
      key: 'sms_subscription_reminder_template',
      label: 'قالب یادآوری اشتراک',
      type: 'textarea',
    },
  ],
  ai: [
    {
      key: 'ai_provider',
      label: 'سرویس',
      type: 'select',
      options: [
        { value: 'openai', label: 'OpenAI' },
        { value: 'claude', label: 'Claude' },
      ],
    },
    { key: 'ai_api_key', label: 'API Key', type: 'text', ltr: true },
    {
      key: 'ai_model',
      label: 'مدل پیش‌فرض',
      type: 'select',
      options: [
        { value: 'gpt-4', label: 'gpt-4' },
        { value: 'gpt-3.5-turbo', label: 'gpt-3.5-turbo' },
        { value: 'claude-3-opus', label: 'claude-3-opus' },
      ],
    },
    { key: 'ai_daily_limit', label: 'سقف روزانه', type: 'number' },
    { key: 'ai_enabled', label: 'فعال بودن AI', type: 'toggle' },
    { key: 'ai_blog_enabled', label: 'تولید مقاله', type: 'toggle' },
    { key: 'ai_questions_enabled', label: 'تولید سوال', type: 'toggle' },
    { key: 'ai_crawl_enabled', label: 'خزش آگهی', type: 'toggle' },
  ],
  security: [
    {
      key: 'turnstile_site_key',
      label: 'Cloudflare Turnstile Site Key',
      type: 'text',
      ltr: true,
    },
    {
      key: 'turnstile_secret_key',
      label: 'Secret Key',
      type: 'text',
      ltr: true,
    },
    { key: 'turnstile_enabled', label: 'فعال بودن Turnstile', type: 'toggle' },
    {
      key: 'captcha_enabled',
      label: 'فعال بودن Captcha (سازگاری)',
      type: 'toggle',
    },
  ],
  social: [
    { key: 'instagram_url', label: 'اینستاگرام', type: 'url', ltr: true },
    { key: 'telegram_url', label: 'تلگرام', type: 'url', ltr: true },
    { key: 'linkedin_url', label: 'لینکدین', type: 'url', ltr: true },
    { key: 'enamad_url', label: 'لینک نماد اعتماد', type: 'url', ltr: true },
    { key: 'samandehi_url', label: 'لینک ساماندهی', type: 'url', ltr: true },
  ],
}

const currentFields = computed(() => fieldMap[active.value] || [])

function loadForm() {
  const src = store.groups[active.value] || {}
  Object.keys(form).forEach((k) => delete form[k])
  Object.assign(form, { ...src })
}

watch(
  () => store.groups,
  () => loadForm(),
  { deep: true }
)

watch(
  () => form.homepage_layout,
  (id) => {
    if (active.value !== 'homepage' || !id) return
    const preset = themePreset(id)
    applySiteTheme({
      homepage_layout: id,
      primary_color: preset.primary,
      secondary_color: preset.secondary,
    })
  }
)

watch(
  () => [form.primary_color, form.secondary_color],
  ([primaryColor, secondaryColor]) => {
    if (active.value !== 'theme') return
    applySiteTheme({
      homepage_layout: store.groups.homepage?.homepage_layout,
      primary_color: primaryColor,
      secondary_color: secondaryColor,
    })
  }
)

onMounted(async () => {
  await store.fetchSettings()
  loadForm()
})

onBeforeRouteLeave((_to, _from, next) => {
  if (
    store.dirty &&
    !window.confirm('تغییرات ذخیره‌نشده دارید. خارج می‌شوید؟')
  ) {
    next(false)
  } else {
    next()
  }
})

function onBeforeUnload(e) {
  if (store.dirty) {
    e.preventDefault()
    e.returnValue = ''
  }
}
onMounted(() => window.addEventListener('beforeunload', onBeforeUnload))
onBeforeUnmount(() =>
  window.removeEventListener('beforeunload', onBeforeUnload)
)

function switchSection(key) {
  if (store.dirty && !window.confirm('تغییرات این بخش ذخیره نشده. ادامه؟'))
    return
  active.value = key
  store.dirty = false
  loadForm()
}

async function save() {
  try {
    await store.updateSettings(active.value, { ...form })
    if (active.value === 'homepage' || active.value === 'theme') {
      await store.fetchSettings()
      loadForm()
    }
    applySiteTheme({
      homepage_layout: store.groups.homepage?.homepage_layout,
      primary_color: store.groups.theme?.primary_color,
      secondary_color: store.groups.theme?.secondary_color,
    })
    toast.success('تنظیمات ذخیره شد و روی کل سایت اعمال شد')
  } catch (e) {
    toast.error(apiErrorMessage(e))
  }
}

async function onUpload({ type, file }) {
  if (!file) return
  try {
    const res = await store.uploadLogo(file, type)
    if (res?.key) form[res.key] = res.url
    toast.success('فایل آپلود شد')
  } catch (e) {
    toast.error(apiErrorMessage(e))
  }
}
</script>

<style scoped>
.btn-muted {
  @apply rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold text-slate-700;
}
.btn-orange {
  @apply rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50;
}
</style>
