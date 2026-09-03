<template>
  <div
    class="flex min-h-dvh items-center justify-center bg-surface-page px-4 py-6"
    dir="rtl"
  >
    <div class="w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-xl">
      <div
        class="relative bg-gradient-to-l from-brand to-brand-dark px-6 py-5 text-center text-white"
      >
        <div
          class="pointer-events-none absolute -left-6 -top-6 h-24 w-24 rounded-full bg-white/10"
        />
        <div
          class="pointer-events-none absolute -bottom-8 -right-4 h-20 w-20 rounded-full bg-white/10"
        />
        <h1 class="relative text-2xl font-black">جاب‌آزمون</h1>
        <p class="relative mt-1 text-xs text-white/80">🔑 بازنشانی رمز عبور</p>
      </div>

      <div class="px-5 py-5">
        <!-- Step 1: identifier -->
        <form
          v-if="step === 'identify'"
          class="space-y-3"
          @submit.prevent="submitIdentify"
        >
          <label class="mb-1 block text-xs font-bold text-ink-soft"
            >📧 ایمیل یا 📱 شماره موبایل</label
          >
          <input
            v-model="identifier"
            class="input-field text-left"
            dir="ltr"
            lang="en"
            inputmode="email"
            autocomplete="username"
            autocapitalize="off"
            spellcheck="false"
            required
            placeholder="09123456789 یا you@example.com"
          />
          <AuthCaptchaField
            :mode="captchaMode"
            :site-key="turnstileSiteKey"
            action="forgot-password"
            @update="onCaptcha"
          />
          <button class="btn-primary" :disabled="loading || !captchaReady">
            {{ loading ? '...' : 'ادامه ←' }}
          </button>
        </form>

        <!-- Mobile OTP + new password -->
        <form
          v-else-if="step === 'otp'"
          class="space-y-3"
          @submit.prevent="submitOtp"
        >
          <p class="text-xs text-ink-muted">
            📩 کد ارسال‌شده به {{ identifier }} را وارد کنید و رمز جدید تعیین
            کنید.
          </p>
          <div>
            <label class="mb-1 block text-xs font-bold text-ink-soft"
              >🔢 کد تایید</label
            >
            <input
              v-model="code"
              class="input-field text-center text-lg tracking-[0.4em]"
              dir="ltr"
              inputmode="numeric"
              maxlength="6"
              required
              placeholder="------"
            />
          </div>
          <div>
            <label class="mb-1 block text-xs font-bold text-ink-soft"
              >🔒 رمز عبور جدید</label
            >
            <PasswordInput
              v-model="password"
              input-class="input-field"
              required
              autocomplete="new-password"
            />
          </div>
          <div>
            <label class="mb-1 block text-xs font-bold text-ink-soft"
              >🔁 تکرار رمز عبور</label
            >
            <PasswordInput
              v-model="password_confirmation"
              input-class="input-field"
              required
              autocomplete="new-password"
            />
          </div>
          <AuthCaptchaField
            :mode="captchaMode"
            :site-key="turnstileSiteKey"
            action="forgot-password"
            @update="onCaptcha"
          />
          <button class="btn-primary" :disabled="loading || !captchaReady">
            {{ loading ? '...' : 'تغییر رمز عبور' }}
          </button>
          <button
            type="button"
            class="btn-ghost w-full"
            @click="step = 'identify'"
          >
            بازگشت
          </button>
        </form>

        <!-- Email success -->
        <div v-else-if="step === 'email'" class="space-y-3">
          <p
            class="rounded-xl bg-green-50 p-4 text-center text-sm text-green-800"
          >
            ✅ در صورت وجود حساب، لینک بازنشانی به ایمیل شما ارسال شد. صندوق
            ورودی و اسپم را بررسی کنید.
          </p>
          <RouterLink to="/login" class="btn-primary block text-center"
            >بازگشت به ورود</RouterLink
          >
        </div>

        <!-- OTP success -->
        <div v-else class="space-y-3">
          <p
            class="rounded-xl bg-green-50 p-4 text-center text-sm text-green-800"
          >
            ✅ رمز عبور با موفقیت تغییر کرد. اکنون وارد شوید.
          </p>
          <RouterLink to="/login" class="btn-primary block text-center"
            >ورود</RouterLink
          >
        </div>

        <p v-if="error" class="mt-3 text-center text-xs font-medium text-brand">
          {{ error }}
        </p>

        <RouterLink
          v-if="step === 'identify'"
          to="/login"
          class="mt-5 block text-center text-xs text-ink-muted underline"
        >
          بازگشت به ورود
        </RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import AuthCaptchaField from '../../components/auth/AuthCaptchaField.vue'
import PasswordInput from '../../components/PasswordInput.vue'
import api from '../../api/client'
import { normalizeIranMobile } from '../../utils/iranMobile'

const identifier = ref('')
const code = ref('')
const password = ref('')
const password_confirmation = ref('')
const loading = ref(false)
const error = ref('')
const step = ref('identify') // identify | otp | email | done

const captchaMode = ref('math')
const turnstileSiteKey = ref('')
const captcha = reactive({
  turnstile_token: '',
  captcha_id: '',
  captcha_answer: '',
})

const captchaReady = computed(() => {
  if (captchaMode.value === 'turnstile') return !!captcha.turnstile_token
  return !!(captcha.captcha_id && captcha.captcha_answer)
})

function onCaptcha(payload) {
  captcha.turnstile_token = payload.turnstile_token || ''
  captcha.captcha_id = payload.captcha_id || ''
  captcha.captcha_answer = payload.captcha_answer || ''
}

function captchaPayload() {
  if (captchaMode.value === 'turnstile') {
    return { turnstile_token: captcha.turnstile_token }
  }
  return {
    captcha_id: captcha.captcha_id,
    captcha_answer: captcha.captcha_answer,
  }
}

function resetCaptcha() {
  captcha.turnstile_token = ''
  captcha.captcha_id = ''
  captcha.captcha_answer = ''
}

onMounted(async () => {
  try {
    const { data } = await api.get('/settings/public')
    const s = data?.data || {}
    captchaMode.value = s.captcha_mode === 'turnstile' ? 'turnstile' : 'math'
    turnstileSiteKey.value = s.turnstile_site_key || ''
  } catch {
    captchaMode.value = 'math'
  }
})

async function submitIdentify() {
  error.value = ''
  loading.value = true
  try {
    const id = identifier.value.trim()
    const payload = { identifier: id, ...captchaPayload() }
    if (id.includes('@')) payload.email = id

    const { data } = await api.post('/auth/forgot-password', payload)
    const channel =
      data.data?.channel || (normalizeIranMobile(id) ? 'mobile' : 'email')
    resetCaptcha()
    if (channel === 'mobile') {
      step.value = 'otp'
    } else {
      step.value = 'email'
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'ارسال ناموفق بود.'
    resetCaptcha()
  } finally {
    loading.value = false
  }
}

async function submitOtp() {
  error.value = ''
  loading.value = true
  try {
    await api.post('/auth/forgot-password/verify-otp', {
      mobile: normalizeIranMobile(identifier.value) || identifier.value.trim(),
      code: code.value.trim(),
      password: password.value,
      password_confirmation: password_confirmation.value,
      ...captchaPayload(),
    })
    step.value = 'done'
  } catch (e) {
    const errors = e.response?.data?.errors
    if (errors) {
      error.value = Object.values(errors).flat()[0] || e.response?.data?.message
    } else {
      error.value = e.response?.data?.message || 'تغییر رمز ناموفق بود.'
    }
    resetCaptcha()
  } finally {
    loading.value = false
  }
}
</script>
