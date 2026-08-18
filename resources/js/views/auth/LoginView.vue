<template>
  <div
    class="flex min-h-dvh items-center justify-center bg-surface-page px-4 py-6"
    dir="rtl"
  >
    <div class="w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-xl dark:bg-slate-900">
      <div class="relative bg-desk-dark px-6 py-5 text-center text-white">
        <div
          class="pointer-events-none absolute -left-6 -top-6 h-24 w-24 rounded-full bg-white/10"
        />
        <div
          class="pointer-events-none absolute -bottom-8 -right-4 h-20 w-20 rounded-full bg-white/10"
        />
        <div class="relative flex justify-center">
          <SiteBrandLogo
            variant="desktop"
            text-class="text-2xl text-white"
          />
        </div>
        <p class="relative mt-2 text-xs text-white/80">
          ورود یا عضویت در حساب کاربری
        </p>
      </div>

      <div class="px-5 pt-4">
        <div class="mb-4 flex rounded-2xl bg-surface-page p-1 dark:bg-slate-800">
          <button
            type="button"
            class="flex-1 rounded-xl py-2 text-xs font-bold transition"
            :class="
              tab === 'login'
                ? 'bg-white text-brand shadow-sm dark:bg-slate-700'
                : 'text-ink-muted'
            "
            @click="tab = 'login'"
          >
            ورود
          </button>
          <button
            type="button"
            class="flex-1 rounded-xl py-2 text-xs font-bold transition"
            :class="
              tab === 'register'
                ? 'bg-white text-brand shadow-sm dark:bg-slate-700'
                : 'text-ink-muted'
            "
            @click="tab = 'register'"
          >
            عضویت
          </button>
          <button
            type="button"
            class="flex-1 rounded-xl py-2 text-xs font-bold transition"
            :class="
              tab === 'otp'
                ? 'bg-white text-brand shadow-sm dark:bg-slate-700'
                : 'text-ink-muted'
            "
            @click="tab = 'otp'"
          >
            کد یکبارمصرف
          </button>
        </div>

        <form
          v-if="tab === 'login'"
          class="space-y-3"
          @submit.prevent="onLogin"
        >
          <div>
            <label class="mb-1 block text-xs font-bold text-ink-soft"
              >👤 نام کاربری یا ایمیل</label
            >
            <input
              v-model="loginForm.login"
              type="text"
              class="input-field text-left"
              dir="ltr"
              lang="en"
              inputmode="email"
              autocomplete="username"
              autocapitalize="off"
              autocorrect="off"
              spellcheck="false"
              required
              placeholder="username یا you@email.com"
              @input="onLoginIdInput"
            />
          </div>
          <div>
            <label class="mb-1 block text-xs font-bold text-ink-soft"
              >🔒 رمز عبور</label
            >
            <PasswordInput
              v-model="loginForm.password"
              input-class="input-field"
              autocomplete="current-password"
              required
              placeholder="رمز عبور"
            />
          </div>
          <AuthCaptchaField
            :mode="captchaMode"
            :site-key="turnstileSiteKey"
            @update="onCaptcha"
          />
          <button
            type="submit"
            class="btn-primary"
            :disabled="auth.loading || !loginForm.login || !loginForm.password || !captchaReady"
          >
            {{ auth.loading ? '...' : 'ورود' }}
          </button>
          <RouterLink
            to="/forgot-password"
            class="block text-center text-xs text-ink-muted underline"
          >
            رمز عبور خود را فراموش کرده‌اید؟
          </RouterLink>
        </form>

        <form
          v-else-if="tab === 'register'"
          class="space-y-3"
          @submit.prevent="onRegister"
        >
          <div>
            <label class="mb-1 block text-xs font-bold text-ink-soft"
              >🙍 نام و نام خانوادگی</label
            >
            <input
              v-model="registerForm.name"
              class="input-field"
              required
              placeholder="نام کامل"
            />
          </div>
          <div>
            <label class="mb-1 block text-xs font-bold text-ink-soft"
              >👤 نام کاربری (انگلیسی)</label
            >
            <input
              v-model="registerForm.username"
              type="text"
              class="input-field text-left"
              dir="ltr"
              lang="en"
              inputmode="text"
              pattern="[a-z0-9_]{3,20}"
              required
              autocomplete="username"
              autocapitalize="off"
              autocorrect="off"
              spellcheck="false"
              placeholder="username"
              @input="onUsernameInput"
            />
            <p class="mt-1 text-[10px] text-ink-muted">
              فقط حروف کوچک انگلیسی، عدد و ـ (۳ تا ۲۰ کاراکتر)
            </p>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="mb-1 block text-xs font-bold text-ink-soft"
                >🔒 رمز عبور</label
              >
              <PasswordInput
                v-model="registerForm.password"
                input-class="input-field"
                autocomplete="new-password"
                required
                placeholder="حداقل ۸ کاراکتر"
              />
              <PasswordRulesHint :password="registerForm.password" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-bold text-ink-soft"
                >🔁 تکرار رمز</label
              >
              <PasswordInput
                v-model="registerForm.password_confirmation"
                input-class="input-field"
                autocomplete="new-password"
                required
                placeholder="تکرار رمز عبور"
              />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="mb-1 block text-xs font-bold text-ink-soft"
                >📱 موبایل</label
              >
              <input
                v-model="registerForm.mobile"
                class="input-field text-left tracking-widest"
                dir="ltr"
                lang="en"
                inputmode="numeric"
                maxlength="11"
                placeholder="09123456789"
              />
            </div>
            <div>
              <label class="mb-1 block text-xs font-bold text-ink-soft"
                >📧 ایمیل</label
              >
              <input
                v-model="registerForm.email"
                type="email"
                class="input-field text-left"
                dir="ltr"
                lang="en"
                inputmode="email"
                autocomplete="email"
                autocapitalize="off"
                autocorrect="off"
                spellcheck="false"
                placeholder="you@example.com"
              />
            </div>
          </div>
          <p class="text-[11px] text-ink-muted">
            حداقل یکی از موبایل یا ایمیل الزامی است.
          </p>
          <AuthCaptchaField
            :mode="captchaMode"
            :site-key="turnstileSiteKey"
            @update="onCaptcha"
          />
          <button
            type="submit"
            class="btn-primary"
            :disabled="auth.loading || !captchaReady"
          >
            {{ auth.loading ? '...' : 'ثبت‌نام' }}
          </button>
        </form>

        <div v-else class="space-y-3">
          <div v-if="otpStep === 1" class="space-y-3">
            <div>
              <label class="mb-1 block text-xs font-bold text-ink-soft"
                >📱 شماره موبایل</label
              >
              <input
                v-model="mobile"
                class="input-field text-left tracking-widest"
                dir="ltr"
                lang="en"
                inputmode="numeric"
                maxlength="11"
                placeholder="09123456789"
              />
            </div>
            <AuthCaptchaField
              :mode="captchaMode"
              :site-key="turnstileSiteKey"
              @update="onCaptcha"
            />
            <button
              class="btn-primary"
              :disabled="
                auth.loading || mobile.length !== 11 || !captchaReady
              "
              @click="onSendOtp"
            >
              دریافت کد تایید
            </button>
          </div>
          <div v-else class="space-y-3">
            <p class="text-xs text-ink-muted">
              کد ارسال‌شده به {{ mobile }} را وارد کنید.
            </p>
            <input
              v-model="code"
              class="input-field text-center text-lg tracking-[0.4em]"
              dir="ltr"
              lang="en"
              inputmode="numeric"
              maxlength="6"
              placeholder="------"
            />
            <AuthCaptchaField
              :mode="captchaMode"
              :site-key="turnstileSiteKey"
              @update="onCaptcha"
            />
            <button
              class="btn-primary"
              :disabled="auth.loading || code.length < 4 || !captchaReady"
              @click="onVerifyOtp"
            >
              ورود
            </button>
            <button type="button" class="btn-ghost w-full" @click="otpStep = 1">
              تغییر شماره
            </button>
          </div>
          <RouterLink
            to="/forgot-password"
            class="block text-center text-xs text-ink-muted underline"
          >
            رمز عبور خود را فراموش کرده‌اید؟
          </RouterLink>
        </div>

        <p v-if="error" class="mt-3 text-center text-xs font-medium text-brand">
          {{ error }}
        </p>

        <div class="mt-5 flex justify-center pb-4">
          <TrustBadges />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../../api/client'
import AuthCaptchaField from '../../components/auth/AuthCaptchaField.vue'
import PasswordInput from '../../components/PasswordInput.vue'
import PasswordRulesHint from '../../components/auth/PasswordRulesHint.vue'
import SiteBrandLogo from '../../components/SiteBrandLogo.vue'
import TrustBadges from '../../components/TrustBadges.vue'
import { useAuthStore } from '../../stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const tab = ref('login')
const otpStep = ref(1)
const mobile = ref('')
const code = ref('')
const error = ref('')

const loginForm = reactive({ login: '', password: '' })
const registerForm = reactive({
  name: '',
  username: '',
  password: '',
  password_confirmation: '',
  mobile: '',
  email: '',
})

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

function onLoginIdInput(e) {
  const v = String(e.target.value || '')
  // keep latin/email chars
  loginForm.login = v.replace(/[^\w.@+-]/g, '')
}

function onUsernameInput(e) {
  registerForm.username = String(e.target.value || '')
    .toLowerCase()
    .replace(/[^a-z0-9_]/g, '')
    .slice(0, 20)
}

function redirectAfterAuth() {
  router.replace(route.query.redirect || '/dashboard')
}

async function loadSettings() {
  try {
    const { data } = await api.get('/settings/public')
    const s = data.data || {}
    captchaMode.value = s.captcha_mode === 'turnstile' ? 'turnstile' : 'math'
    turnstileSiteKey.value = s.turnstile_site_key || ''
  } catch {
    captchaMode.value = 'math'
  }
}

onMounted(() => {
  void loadSettings()
})

watch([tab, otpStep], () => {
  captcha.turnstile_token = ''
  captcha.captcha_id = ''
  captcha.captcha_answer = ''
})

async function onLogin() {
  error.value = ''
  try {
    await auth.loginPassword(
      loginForm.login.trim(),
      loginForm.password,
      captchaPayload()
    )
    redirectAfterAuth()
  } catch (e) {
    error.value = e.response?.data?.message || 'ورود ناموفق بود.'
  }
}

async function onRegister() {
  error.value = ''
  if (!registerForm.mobile.trim() && !registerForm.email.trim()) {
    error.value = 'حداقل یکی از موبایل یا ایمیل الزامی است.'
    return
  }
  if (!/^[a-z0-9_]{3,20}$/.test(registerForm.username)) {
    error.value = 'نام کاربری باید ۳ تا ۲۰ کاراکتر انگلیسی باشد.'
    return
  }
  try {
    const payload = {
      name: registerForm.name.trim(),
      username: registerForm.username.trim(),
      password: registerForm.password,
      password_confirmation: registerForm.password_confirmation,
    }
    if (registerForm.mobile.trim()) payload.mobile = registerForm.mobile.trim()
    if (registerForm.email.trim()) payload.email = registerForm.email.trim()
    await auth.register(payload, captchaPayload())
    redirectAfterAuth()
  } catch (e) {
    const errors = e.response?.data?.errors
    if (errors) {
      error.value = Object.values(errors).flat()[0] || e.response?.data?.message
    } else {
      error.value = e.response?.data?.message || 'ثبت‌نام ناموفق بود.'
    }
  }
}

async function onSendOtp() {
  error.value = ''
  try {
    await auth.sendOtp(mobile.value, captchaPayload())
    otpStep.value = 2
  } catch (e) {
    error.value = e.response?.data?.message || 'ارسال کد ناموفق بود.'
  }
}

async function onVerifyOtp() {
  error.value = ''
  try {
    await auth.verifyOtp(mobile.value, code.value, captchaPayload())
    redirectAfterAuth()
  } catch (e) {
    error.value = e.response?.data?.message || 'کد نامعتبر است.'
  }
}
</script>
