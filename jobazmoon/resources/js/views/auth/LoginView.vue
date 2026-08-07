<template>
  <div class="flex min-h-dvh flex-col bg-white px-5 py-10">
    <div class="mb-10 text-center">
      <h1 class="text-3xl font-black text-brand">جاب‌آزمون</h1>
      <p class="mt-2 text-sm text-ink-muted">ورود با شماره موبایل</p>
    </div>

    <div v-if="step === 1" class="space-y-4">
      <label class="block text-sm font-medium">شماره موبایل</label>
      <input
        v-model="mobile"
        class="input-field text-left tracking-widest"
        dir="ltr"
        inputmode="numeric"
        maxlength="11"
        placeholder="09123456789"
      />
      <div v-if="turnstileEnabled && turnstileSiteKey" class="flex justify-center">
        <div ref="turnstileEl" class="cf-turnstile" :data-sitekey="turnstileSiteKey" data-callback="onTurnstileSuccess" data-expired-callback="onTurnstileExpired"></div>
      </div>
      <button class="btn-primary" :disabled="auth.loading || mobile.length !== 11 || (turnstileEnabled && !turnstileToken)" @click="onSend">
        دریافت کد تایید
      </button>
      <RouterLink to="/forgot-password" class="block text-center text-sm text-ink-muted underline">
        رمز عبور خود را فراموش کرده‌اید؟
      </RouterLink>
    </div>

    <div v-else class="space-y-4">
      <p class="text-sm text-ink-muted">کد ارسال‌شده به {{ mobile }} را وارد کنید.</p>
      <input
        v-model="code"
        class="input-field text-center text-lg tracking-[0.4em]"
        dir="ltr"
        inputmode="numeric"
        maxlength="6"
        placeholder="------"
      />
      <div v-if="turnstileEnabled && turnstileSiteKey" class="flex justify-center">
        <div ref="turnstileEl2" class="cf-turnstile" :data-sitekey="turnstileSiteKey" data-callback="onTurnstileSuccess" data-expired-callback="onTurnstileExpired"></div>
      </div>
      <button class="btn-primary" :disabled="auth.loading || code.length < 4 || (turnstileEnabled && !turnstileToken)" @click="onVerify">
        ورود
      </button>
      <button class="btn-ghost w-full" @click="step = 1">تغییر شماره</button>
    </div>

    <p v-if="error" class="mt-4 text-center text-sm text-brand">{{ error }}</p>

    <div class="mt-10 flex justify-center">
      <TrustBadges />
    </div>
  </div>
</template>

<script setup>
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../../api/client';
import TrustBadges from '../../components/TrustBadges.vue';
import { useAuthStore } from '../../stores/auth';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const step = ref(1);
const mobile = ref('');
const code = ref('');
const error = ref('');
const turnstileEnabled = ref(false);
const turnstileSiteKey = ref('');
const turnstileToken = ref('');
const turnstileEl = ref(null);
const turnstileEl2 = ref(null);
let scriptLoaded = false;

function onTurnstileSuccess(token) {
  turnstileToken.value = token;
}
function onTurnstileExpired() {
  turnstileToken.value = '';
}

window.onTurnstileSuccess = onTurnstileSuccess;
window.onTurnstileExpired = onTurnstileExpired;

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
    const targets = [turnstileEl.value, turnstileEl2.value].filter(Boolean);
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

watch(step, () => {
  turnstileToken.value = '';
  if (turnstileEnabled.value) {
    nextTick(() => renderWidgets());
  }
});

onUnmounted(() => {
  delete window.onTurnstileSuccess;
  delete window.onTurnstileExpired;
});

async function onSend() {
  error.value = '';
  try {
    await auth.sendOtp(mobile.value, turnstileToken.value || undefined);
    step.value = 2;
  } catch (e) {
    error.value = e.response?.data?.message || 'ارسال کد ناموفق بود.';
  }
}

async function onVerify() {
  error.value = '';
  try {
    await auth.verifyOtp(mobile.value, code.value, turnstileToken.value || undefined);
    router.replace(route.query.redirect || '/');
  } catch (e) {
    error.value = e.response?.data?.message || 'کد نامعتبر است.';
  }
}
</script>
