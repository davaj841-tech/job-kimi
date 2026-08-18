<template>
  <div class="flex min-h-screen items-center justify-center bg-[#0f2744] px-4">
    <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-xl">
      <div class="mb-6 text-center">
        <h1 class="text-2xl font-black text-slate-800">ورود به پنل مدیریت</h1>
        <p class="mt-2 text-sm text-slate-500">فقط نقش‌های ادمین و اپراتور</p>
      </div>

      <div class="mb-6 grid grid-cols-2 gap-2 rounded-xl bg-slate-100 p-1">
        <button
          type="button"
          class="rounded-lg px-3 py-2 text-sm font-bold transition"
          :class="
            tab === 'password'
              ? 'bg-white text-slate-800 shadow'
              : 'text-slate-500'
          "
          @click="selectPasswordTab"
        >
          ورود با رمز عبور
        </button>
        <button
          type="button"
          class="rounded-lg px-3 py-2 text-sm font-bold transition"
          :class="
            tab === 'otp' ? 'bg-white text-slate-800 shadow' : 'text-slate-500'
          "
          @click="selectOtpTab"
        >
          ورود با پیامک
        </button>
      </div>

      <!-- Password login -->
      <div v-if="tab === 'password' && !showForgot" class="space-y-4">
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700"
            >نام کاربری</label
          >
          <input
            v-model="username"
            class="h-11 w-full rounded-xl border border-slate-200 px-3 text-left outline-none focus:border-orange-500"
            dir="ltr"
            lang="en"
            inputmode="text"
            autocomplete="username"
            autocapitalize="off"
            spellcheck="false"
            maxlength="20"
            placeholder="admin"
            @input="
              username = String(username || '')
                .toLowerCase()
                .replace(/[^a-z0-9_]/g, '')
            "
          />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700"
            >رمز عبور</label
          >
          <PasswordInput
            v-model="password"
            input-class="h-11 w-full rounded-xl border border-slate-200 px-3 text-left outline-none focus:border-orange-500"
            autocomplete="current-password"
            placeholder="••••••••"
            @enter="onPasswordLogin"
          />
        </div>
        <button
          class="h-11 w-full rounded-xl bg-orange-500 text-sm font-bold text-white hover:bg-orange-600 disabled:opacity-50"
          :disabled="auth.loading || !canSubmitPassword"
          @click="onPasswordLogin"
        >
          ورود
        </button>
        <button
          type="button"
          class="w-full text-sm text-slate-500 underline"
          @click="openForgot"
        >
          فراموشی رمز عبور
        </button>
      </div>

      <!-- Forgot password -->
      <div v-else-if="tab === 'password' && showForgot" class="space-y-4">
        <p class="text-sm text-slate-500">
          لینک بازنشانی به ایمیل ادمین ارسال می‌شود.
        </p>
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700"
            >ایمیل</label
          >
          <input
            v-model="email"
            type="email"
            class="h-11 w-full rounded-xl border border-slate-200 px-3 text-left outline-none focus:border-orange-500"
            dir="ltr"
            lang="en"
            inputmode="email"
            autocomplete="email"
            autocapitalize="off"
            spellcheck="false"
            placeholder="admin@example.com"
            @keyup.enter="onForgot"
          />
        </div>
        <button
          class="h-11 w-full rounded-xl bg-orange-500 text-sm font-bold text-white hover:bg-orange-600 disabled:opacity-50"
          :disabled="auth.loading || !email.includes('@')"
          @click="onForgot"
        >
          ارسال لینک بازنشانی
        </button>
        <button
          type="button"
          class="w-full text-sm text-slate-500"
          @click="closeForgot"
        >
          بازگشت به ورود
        </button>
      </div>

      <!-- OTP login -->
      <div v-else-if="tab === 'otp' && otpStep === 1" class="space-y-4">
        <label class="block text-sm font-medium text-slate-700"
          >شماره موبایل</label
        >
        <input
          v-model="mobile"
          class="h-11 w-full rounded-xl border border-slate-200 px-3 text-left tracking-widest outline-none focus:border-orange-500"
          dir="ltr"
          inputmode="numeric"
          maxlength="11"
          placeholder="09123456789"
        />
        <button
          class="h-11 w-full rounded-xl bg-orange-500 text-sm font-bold text-white hover:bg-orange-600 disabled:opacity-50"
          :disabled="auth.loading || mobile.length !== 11"
          @click="onSend"
        >
          دریافت کد تایید
        </button>
      </div>

      <div v-else-if="tab === 'otp'" class="space-y-4">
        <p class="text-sm text-slate-500">کد ارسال‌شده به {{ mobile }}</p>
        <input
          v-model="code"
          class="h-11 w-full rounded-xl border border-slate-200 px-3 text-center text-lg tracking-[0.35em] outline-none focus:border-orange-500"
          dir="ltr"
          inputmode="numeric"
          maxlength="6"
          placeholder="------"
        />
        <button
          class="h-11 w-full rounded-xl bg-orange-500 text-sm font-bold text-white hover:bg-orange-600 disabled:opacity-50"
          :disabled="auth.loading || code.length < 4"
          @click="onVerify"
        >
          ورود
        </button>
        <button class="w-full text-sm text-slate-500" @click="otpStep = 1">
          تغییر شماره
        </button>
      </div>

      <p v-if="info" class="mt-4 text-center text-sm text-emerald-600">
        {{ info }}
      </p>
      <p v-if="error" class="mt-4 text-center text-sm text-red-500">
        {{ error }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import PasswordInput from '../../components/PasswordInput.vue'
import { useRouter } from 'vue-router'
import { useAdminAuthStore } from '../stores/auth'

const auth = useAdminAuthStore()
const router = useRouter()

const tab = ref('password')
const showForgot = ref(false)
const otpStep = ref(1)

const username = ref('')
const password = ref('')
const email = ref('')
const mobile = ref('')
const code = ref('')
const error = ref('')
const info = ref('')

const canSubmitPassword = computed(() => {
  const u = username.value.trim()
  return /^[a-z0-9_]{3,20}$/.test(u) && password.value.length >= 8
})

function selectPasswordTab() {
  tab.value = 'password'
  showForgot.value = false
}

function selectOtpTab() {
  tab.value = 'otp'
  showForgot.value = false
  otpStep.value = 1
}

function openForgot() {
  showForgot.value = true
  error.value = ''
  info.value = ''
}

function closeForgot() {
  showForgot.value = false
  error.value = ''
  info.value = ''
}

async function onPasswordLogin() {
  error.value = ''
  info.value = ''
  try {
    await auth.loginWithPassword(username.value.trim(), password.value)
    router.replace('/admin/dashboard')
  } catch (e) {
    error.value = e.response?.data?.message || 'ورود ناموفق بود.'
  }
}

async function onForgot() {
  error.value = ''
  info.value = ''
  try {
    const data = await auth.forgotPassword(email.value.trim())
    info.value = data.message || 'لینک بازنشانی ارسال شد.'
  } catch (e) {
    error.value = e.response?.data?.message || 'ارسال لینک ناموفق بود.'
  }
}

async function onSend() {
  error.value = ''
  info.value = ''
  try {
    await auth.sendOtp(mobile.value)
    otpStep.value = 2
  } catch (e) {
    error.value = e.response?.data?.message || 'ارسال کد ناموفق بود.'
  }
}

async function onVerify() {
  error.value = ''
  info.value = ''
  try {
    await auth.verifyOtp(mobile.value, code.value)
    router.replace('/admin/dashboard')
  } catch (e) {
    error.value = e.response?.data?.message || 'ورود ناموفق بود.'
  }
}
</script>
