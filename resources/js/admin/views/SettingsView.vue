<template>
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
        :model-value="form"
        :fields="currentFields"
        @update:model-value="onFormUpdate"
        @dirty="store.markDirty()"
        @upload="onUpload"
      />
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
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
  { key: 'homepage', label: 'طرح، تم و فونت سایت' },
  { key: 'seo', label: 'سئو' },
  { key: 'payment', label: 'پرداخت' },
  { key: 'sms', label: 'پیامک' },
  { key: 'ai', label: 'هوش مصنوعی' },
  { key: 'exam', label: 'آزمون' },
  { key: 'security', label: 'امنیت' },
  { key: 'social', label: 'شبکه‌های اجتماعی و اپ' },
]

const fieldMap = {
  general: [
    { key: 'site_name', label: 'نام سایت', type: 'text' },
    { key: 'site_description', label: 'توضیحات سایت', type: 'textarea' },
    {
      key: 'site_logo',
      label: 'لوگوی سایت',
      type: 'image',
      uploadType: 'logo',
    },
    {
      key: 'site_favicon',
      label: 'فاوآیکون / آیکون سایت',
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
  homepage: [
    {
      key: 'homepage_layout',
      label: 'تم کل سایت (صفحه اول، صفحات جانبی، موبایل، داشبورد، پنل و فوتر)',
      type: 'homepage-layout',
    },
    {
      key: 'site_font',
      label: 'فونت فارسی سایت',
      type: 'site-font',
    },
    {
      key: 'site_font_size',
      label: 'اندازه فونت سایت',
      type: 'site-font-size',
    },
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
        { value: 'mellat', label: 'بانک ملت' },
        { value: 'shaparak', label: 'شاپرک' },
      ],
    },
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
  exam: [
    {
      key: 'default_exam_duration',
      label: 'مدت پیش‌فرض آزمون (دقیقه)',
      type: 'number',
    },
    {
      key: 'exam_questions_per_page',
      label: 'تعداد سوال در هر صفحه آزمون',
      type: 'number',
    },
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
    { key: 'whatsapp_url', label: 'واتساپ', type: 'url', ltr: true },
    { key: 'rubika_url', label: 'روبیکا', type: 'url', ltr: true },
    { key: 'bale_url', label: 'بله', type: 'url', ltr: true },
    { key: 'enamad_url', label: 'لینک نماد اعتماد', type: 'url', ltr: true },
    { key: 'samandehi_url', label: 'لینک ساماندهی', type: 'url', ltr: true },
    {
      key: 'android_play_url',
      label: 'لینک گوگل پلی (اپ اندروید)',
      type: 'url',
      ltr: true,
    },
    {
      key: 'android_bazaar_url',
      label: 'لینک کافه بازار (اپ اندروید)',
      type: 'url',
      ltr: true,
    },
    {
      key: 'android_direct_url',
      label: 'فایل APK اپ سایت',
      type: 'file',
      uploadType: 'apk',
      accept: '.apk',
      maxSizeMb: 50,
      hint: 'فایل APK را اینجا آپلود کنید — حداکثر ۵۰ مگابایت',
    },
  ],
}

const paymentFieldsByGateway = {
  zarinpal: [
    {
      key: 'zarinpal_merchant_id',
      label: 'شناسه پذیرنده',
      type: 'text',
      ltr: true,
    },
    {
      key: 'zarinpal_sandbox',
      label: 'حالت آزمایشی (Sandbox)',
      type: 'toggle',
    },
  ],
  nextpay: [
    { key: 'nextpay_api_key', label: 'کلید API', type: 'text', ltr: true },
    { key: 'nextpay_active', label: 'فعال بودن درگاه', type: 'toggle' },
  ],
  idpay: [
    { key: 'idpay_api_key', label: 'کلید API', type: 'text', ltr: true },
    { key: 'idpay_active', label: 'فعال بودن درگاه', type: 'toggle' },
    { key: 'idpay_sandbox', label: 'حالت آزمایشی (Sandbox)', type: 'toggle' },
  ],
  mellat: [
    {
      key: 'mellat_terminal_id',
      label: 'شماره ترمینال',
      type: 'text',
      ltr: true,
    },
    { key: 'mellat_username', label: 'نام کاربری', type: 'text', ltr: true },
    { key: 'mellat_password', label: 'رمز درگاه', type: 'text', ltr: true },
    { key: 'mellat_active', label: 'فعال بودن درگاه', type: 'toggle' },
  ],
  shaparak: [
    {
      key: 'shaparak_merchant_id',
      label: 'شناسه پذیرنده',
      type: 'text',
      ltr: true,
    },
    {
      key: 'shaparak_terminal_id',
      label: 'شماره ترمینال',
      type: 'text',
      ltr: true,
    },
    { key: 'shaparak_username', label: 'نام کاربری', type: 'text', ltr: true },
    { key: 'shaparak_password', label: 'رمز درگاه', type: 'text', ltr: true },
    { key: 'shaparak_active', label: 'فعال بودن درگاه', type: 'toggle' },
  ],
}

const currentFields = computed(() => {
  if (active.value !== 'payment') return fieldMap[active.value] || []
  const gw = form.payment_gateway || 'zarinpal'
  return [
    ...(fieldMap.payment || []),
    ...(paymentFieldsByGateway[gw] || []),
    { key: 'min_wallet_charge', label: 'حداقل شارژ کیف پول', type: 'number' },
  ]
})

function loadForm() {
  const src = store.groups[active.value] || {}
  Object.keys(form).forEach((k) => delete form[k])
  Object.assign(form, { ...src })
}

/** Keep reactive form mutated in-place (v-model replace breaks watches/selection). */
function onFormUpdate(next) {
  if (!next || typeof next !== 'object') return
  Object.assign(form, next)
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
    form.primary_color = preset.primary
    form.secondary_color = preset.secondary
    applySiteTheme({
      homepage_layout: id,
      primary_color: preset.primary,
      secondary_color: preset.secondary,
      site_font: form.site_font,
      site_font_size: form.site_font_size,
    })
  }
)

watch(
  () => form.site_font,
  (id) => {
    if (active.value !== 'homepage' || !id) return
    applySiteTheme({
      homepage_layout: form.homepage_layout,
      primary_color: form.primary_color,
      secondary_color: form.secondary_color,
      site_font: id,
      site_font_size: form.site_font_size,
    })
  }
)

watch(
  () => form.site_font_size,
  (size) => {
    if (active.value !== 'homepage' || !size) return
    applySiteTheme({
      homepage_layout: form.homepage_layout,
      primary_color: form.primary_color,
      secondary_color: form.secondary_color,
      site_font: form.site_font,
      site_font_size: size,
    })
  }
)

watch(
  () => [form.primary_color, form.secondary_color],
  ([primaryColor, secondaryColor]) => {
    if (active.value !== 'homepage') return
    applySiteTheme({
      homepage_layout: form.homepage_layout,
      primary_color: primaryColor,
      secondary_color: secondaryColor,
      site_font: form.site_font,
      site_font_size: form.site_font_size,
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
    if (active.value === 'homepage' || active.value === 'general') {
      await store.fetchSettings()
      loadForm()
    }
    applySiteTheme({
      homepage_layout: store.groups.homepage?.homepage_layout,
      primary_color: store.groups.homepage?.primary_color,
      secondary_color: store.groups.homepage?.secondary_color,
      site_font: store.groups.homepage?.site_font,
      site_font_size: store.groups.homepage?.site_font_size,
      site_logo: store.groups.general?.site_logo,
      site_favicon: store.groups.general?.site_favicon,
    })
    toast.success('تنظیمات ذخیره شد و روی کل سایت اعمال شد')
  } catch (e) {
    toast.error(apiErrorMessage(e))
  }
}

async function onUpload({ type, file, key }) {
  if (!file) {
    if (key) form[key] = ''
    return
  }
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
