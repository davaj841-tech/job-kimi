<template>
  <div class="flex min-h-dvh items-center justify-center bg-surface-page px-4 py-6" dir="rtl">
    <div class="w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-xl">
      <!-- Soft gradient header -->
      <div class="relative bg-gradient-to-l from-brand to-brand-dark px-6 py-5 text-center text-white">
        <div class="pointer-events-none absolute -left-6 -top-6 h-24 w-24 rounded-full bg-white/10" />
        <div class="pointer-events-none absolute -bottom-8 -right-4 h-20 w-20 rounded-full bg-white/10" />
        <h1 class="relative text-2xl font-black">جاب‌آزمون</h1>
        <p class="relative mt-1 text-xs text-white/80">ورود یا عضویت در حساب کاربری</p>
      </div>

      <div class="px-5 pt-4">
        <!-- Tabs -->
        <div class="mb-4 flex rounded-2xl bg-surface-page p-1">
          <button
            type="button"
            class="flex-1 rounded-xl py-2 text-xs font-bold transition"
            :class="tab === 'login' ? 'bg-white text-brand shadow-sm' : 'text-ink-muted'"
            @click="tab = 'login'"
          >
            🔑 ورود
          </button>
          <button
            type="button"
            class="flex-1 rounded-xl py-2 text-xs font-bold transition"
            :class="tab === 'register' ? 'bg-white text-brand shadow-sm' : 'text-ink-muted'"
            @click="tab = 'register'"
          >
            ✨ عضویت
          </button>
          <button
            type="button"
            class="flex-1 rounded-xl py-2 text-xs font-bold transition"
            :class="tab === 'otp' ? 'bg-white text-brand shadow-sm' : 'text-ink-muted'"
            @click="tab = 'otp'"
          >
            📱 کد
          </button>
        </div>

        <!-- Password login -->
        <form v-if="tab === 'login'" class="space-y-3" @submit.prevent="onLogin">
          <div>
            <label class="mb-1 block text-xs font-bold text-ink-soft">👤 نام کاربری یا ایمیل</label>
            <input
              v-model="loginForm.login"
              class="input-field text-left"
              dir="ltr"
              autocomplete="username"
              required
              placeholder="username یا email"
            />
          </div>
          <div>
            <label class="mb-1 block text-xs font-bold text-ink-soft">🔒 رمز عبور</label>
            <input
              v-model="loginForm.password"
              type="password"
              class="input-field"
              autocomplete="current-password"
              required
              placeholder="رمز عبور"
            />
          </div>
          <div v-if="turnstileEnabled && turnstileSiteKey" class="flex justify-center">
            <div ref="turnstileEl" class="cf-turnstile"></div>
          </div>
          <button
            type="submit"
            class="btn-primary"
            :disabled="auth.loading || !loginForm.login || !loginForm.password || (turnstileEnabled && !turnstileToken)"
          >
            {{ auth.loading ? '...' : 'ورود' }}
          </button>
          <RouterLink to="/forgot-password" class="block text-center text-xs text-ink-muted underline">
            رمز عبور خود را فراموش کرده‌اید؟
          </RouterLink>
        </form>

        <!-- Register -->
        <form v-else-if="tab === 'register'" class="space-y-3" @submit.prevent="onRegister">
          <div>
            <label class="mb-1 block text-xs font-bold text-ink-soft">🙍 نام و نام خانوادگی</label>
            <input v-model="registerForm.name" class="input-field" required placeholder="نام کامل" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-bold text-ink-soft">👤 نام کاربری</label>
            <input
              v-model="registerForm.username"
              class="input-field text-left"
              dir="ltr"
              required
              autocomplete="username"
              placeholder="username"
            />
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="mb-1 block text-xs font-bold text-ink-soft">🔒 رمز عبور</label>
              <input
                v-model="registerForm.password"
                type="password"
                class="input-field"
                required
                autocomplete="new-password"
                placeholder="حداقل ۸ کاراکتر"
              />
            </div>
            <div>
              <label class="mb-1 block text-xs font-bold text-ink-soft">🔁 تکرار رمز</label>
              <input
                v-model="registerForm.password_confirmation"
                type="password"
                class="input-field"
                required
                autocomplete="new-password"
                placeholder="تکرار رمز عبور"
              />
            </div>
          </div>
          <div>
            <label class="mb-1 block text-xs font-bold text-ink-soft">📍 استان *</label>
            <select v-model="registerForm.province" class="input-field" required>
              <option value="" disabled>انتخاب استان</option>
              <option v-for="p in IRAN_PROVINCES" :key="p" :value="p">{{ p }}</option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="mb-1 block text-xs font-bold text-ink-soft">📱 موبایل</label>
              <input
                v-model="registerForm.mobile"
                class="input-field text-left tracking-widest"
                dir="ltr"
                inputmode="numeric"
                maxlength="11"
                placeholder="09123456789"
              />
            </div>
            <div>
              <label class="mb-1 block text-xs font-bold text-ink-soft">📧 ایمیل</label>
              <input
                v-model="registerForm.email"
                type="email"
                class="input-field text-left"
                dir="ltr"
                placeholder="you@example.com"
              />
            </div>
          </div>
          <p class="text-[11px] text-ink-muted">حداقل یکی از موبایل یا ایمیل الزامی است.</p>
          <div v-if="turnstileEnabled && turnstileSiteKey" class="flex justify-center">
            <div ref="turnstileElReg" class="cf-turnstile"></div>
          </div>
          <button
            type="submit"
            class="btn-primary"
            :disabled="auth.loading || !registerForm.province || (turnstileEnabled && !turnstileToken)"
          >
            {{ auth.loading ? '...' : 'ثبت‌نام' }}
          </button>
        </form>

        <!-- OTP -->
        <div v-else class="space-y-3">
          <div v-if="otpStep === 1" class="space-y-3">
            <div>
              <label class="mb-1 block text-xs font-bold text-ink-soft">📱 شماره موبایل</label>
              <input
                v-model="mobile"
                class="input-field text-left tracking-widest"
                dir="ltr"
                inputmode="numeric"
                maxlength="11"
                placeholder="09123456789"
              />
            </div>
            <div v-if="turnstileEnabled && turnstileSiteKey" class="flex justify-center">
              <div ref="turnstileElOtp1" class="cf-turnstile"></div>
            </div>
            <button
              class="btn-primary"
              :disabled="auth.loading || mobile.length !== 11 || (turnstileEnabled && !turnstileToken)"
              @click="onSendOtp"
            >
              دریافت کد تایید
            </button>
          </div>
          <div v-else class="space-y-3">
            <p class="text-xs text-ink-muted">کد ارسال‌شده به {{ mobile }} را وارد کنید.</p>
            <input
              v-model="code"
              class="input-field text-center text-lg tracking-[0.4em]"
              dir="ltr"
              inputmode="numeric"
              maxlength="6"
              placeholder="------"
            />
            <div v-if="turnstileEnabled && turnstileSiteKey" class="flex justify-center">
              <div ref="turnstileElOtp2" class="cf-turnstile"></div>
            </div>
            <button
              class="btn-primary"
              :disabled="auth.loading || code.length < 4 || (turnstileEnabled && !turnstileToken)"
              @click="onVerifyOtp"
            >
              ورود
            </button>
            <button type="button" class="btn-ghost w-full" @click="otpStep = 1">تغییر شماره</button>
          </div>
          <RouterLink to="/forgot-password" class="block text-center text-xs text-ink-muted underline">
            رمز عبور خود را فراموش کرده‌اید؟
          </RouterLink>
        </div>

        <p v-if="error" class="mt-3 text-center text-xs font-medium text-brand">{{ error }}</p>

        <div class="mt-5 flex justify-center pb-4">
          <TrustBadges />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../../api/client';
import TrustBadges from '../../components/TrustBadges.vue';
import { useAuthStore } from '../../stores/auth';
import { IRAN_PROVINCES } from '../../utils/provinces';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const tab = ref('login');
const otpStep = ref(1);
const mobile = ref('');
const code = ref('');
const error = ref('');

const loginForm = reactive({ login: '', password: '' });
const registerForm = reactive({
  name: '',
  username: '',
  password: '',
  password_confirmation: '',
  mobile: '',
  email: '',
  province: '',
});

const turnstileEnabled = ref(false);
const turnstileSiteKey = ref('');
const turnstileToken = ref('');
const turnstileEl = ref(null);
const turnstileElReg = ref(null);
const turnstileElOtp1 = ref(null);
const turnstileElOtp2 = ref(null);
let scriptLoaded = false;

function onTurnstileSuccess(token) {
  turnstileToken.value = token;
}
function onTurnstileExpired() {
  turnstileToken.value = '';
}

window.onTurnstileSuccess = onTurnstileSuccess;
window.onTurnstileExpired = onTurnstileExpired;

function redirectAfterAuth() {
  router.replace(route.query.redirect || '/dashboard');
}

async function loadSettings() {
  try {
    const { data } = await api.get('/settings/public');
    const s = data.data || {};
    turnstileEnabled.value = !!(s.turnstile_enabled || s.captcha_enabled);
    turnstileSiteKey.value = s.turnstile_site_key || '';
  } catch {
    // ignore
  }
}

function loadTurnstileScript() {
  if (scriptLoaded || document.getElementById('cf-turnstile-script')) {
    scriptLoaded = true;
    renderWidgets();
    return;
  }
  const s = document.createElement('script');
  s.id = 'cf-turnstile-script';
  s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
  s.async = true;
  s.onload = () => {
    scriptLoaded = true;
    renderWidgets();
  };
  document.head.appendChild(s);
}

function renderWidgets() {
  nextTick(() => {
    if (!window.turnstile || !turnstileSiteKey.value) return;
    const targets = [
      turnstileEl.value,
      turnstileElReg.value,
      turnstileElOtp1.value,
      turnstileElOtp2.value,
    ].filter(Boolean);
    targets.forEach((el) => {
      if (el.dataset.rendered) return;
      window.turnstile.render(el, {
        sitekey: turnstileSiteKey.value,
        callback: onTurnstileSuccess,
        'expired-callback': onTurnstileExpired,
      });
      el.dataset.rendered = '1';
    });
  });
}

onMounted(async () => {
  await loadSettings();
  if (turnstileEnabled.value && turnstileSiteKey.value) {
    loadTurnstileScript();
  }
});

watch([tab, otpStep], () => {
  turnstileToken.value = '';
  if (turnstileEnabled.value) {
    nextTick(() => renderWidgets());
  }
});

onUnmounted(() => {
  delete window.onTurnstileSuccess;
  delete window.onTurnstileExpired;
});

async function onLogin() {
  error.value = '';
  try {
    await auth.loginPassword(
      loginForm.login.trim(),
      loginForm.password,
      turnstileToken.value || undefined
    );
    redirectAfterAuth();
  } catch (e) {
    error.value = e.response?.data?.message || 'ورود ناموفق بود.';
  }
}

async function onRegister() {
  error.value = '';
  if (!registerForm.mobile.trim() && !registerForm.email.trim()) {
    error.value = 'حداقل یکی از موبایل یا ایمیل الزامی است.';
    return;
  }
  if (!registerForm.province) {
    error.value = 'انتخاب استان الزامی است.';
    return;
  }
  try {
    const payload = {
      name: registerForm.name.trim(),
      username: registerForm.username.trim(),
      password: registerForm.password,
      password_confirmation: registerForm.password_confirmation,
      province: registerForm.province,
    };
    if (registerForm.mobile.trim()) payload.mobile = registerForm.mobile.trim();
    if (registerForm.email.trim()) payload.email = registerForm.email.trim();
    await auth.register(payload, turnstileToken.value || undefined);
    redirectAfterAuth();
  } catch (e) {
    const errors = e.response?.data?.errors;
    if (errors) {
      error.value = Object.values(errors).flat()[0] || e.response?.data?.message;
    } else {
      error.value = e.response?.data?.message || 'ثبت‌نام ناموفق بود.';
    }
  }
}

async function onSendOtp() {
  error.value = '';
  try {
    await auth.sendOtp(mobile.value, turnstileToken.value || undefined);
    otpStep.value = 2;
  } catch (e) {
    error.value = e.response?.data?.message || 'ارسال کد ناموفق بود.';
  }
}

async function onVerifyOtp() {
  error.value = '';
  try {
    await auth.verifyOtp(mobile.value, code.value, turnstileToken.value || undefined);
    redirectAfterAuth();
  } catch (e) {
    error.value = e.response?.data?.message || 'کد نامعتبر است.';
  }
}
</script>
