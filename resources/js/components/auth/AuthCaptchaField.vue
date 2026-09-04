<template>
  <div class="space-y-2">
    <div v-if="mode === 'turnstile'" class="flex justify-center">
      <div ref="el" class="cf-turnstile"></div>
      <p v-if="widgetError" class="mt-2 text-center text-xs text-red-600">
        {{ widgetError }}
      </p>
    </div>
    <div
      v-else
      class="rounded-xl border border-surface-line bg-surface-page p-3"
    >
      <div class="mb-2 flex items-center justify-between gap-2">
        <p class="text-xs font-bold text-ink-soft">کپچا امنیتی</p>
        <button
          type="button"
          class="inline-flex items-center justify-center rounded-lg p-1 text-brand transition hover:bg-brand/10"
          aria-label="بازنشانی کپچا"
          title="بازنشانی"
          @click="refresh"
        >
          <ArrowPathIcon class="h-4 w-4" />
        </button>
      </div>
      <p class="mb-2 text-sm font-black tracking-wide text-desk-text" dir="ltr">
        {{ question || '...' }}
      </p>
      <input
        v-model="answer"
        type="text"
        inputmode="numeric"
        lang="en"
        autocomplete="off"
        class="input-field text-left"
        dir="ltr"
        placeholder="پاسخ"
        @input="emitPayload"
      />
    </div>
  </div>
</template>

<script setup>
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { ArrowPathIcon } from '@heroicons/vue/24/outline'
import api from '../../api/client'

const props = defineProps({
  mode: { type: String, default: 'math' }, // turnstile | math
  siteKey: { type: String, default: '' },
  /** Cloudflare widget action (1–32 chars); must match backend auth.captcha:action */
  action: { type: String, default: '' },
})

const emit = defineEmits(['update'])

const el = ref(null)
const question = ref('')
const captchaId = ref('')
const answer = ref('')
const widgetError = ref('')
let widgetId = null

function emitPayload() {
  if (props.mode === 'turnstile') {
    emit('update', { turnstile_token: answer.value || undefined })
    return
  }
  emit('update', {
    captcha_id: captchaId.value,
    captcha_answer: answer.value.trim(),
  })
}

function onToken(token) {
  widgetError.value = ''
  answer.value = token
  emitPayload()
}

function onExpired() {
  answer.value = ''
  widgetError.value = 'تایید امنیتی منقضی شد. دوباره تلاش کنید.'
  emitPayload()
}

function onError() {
  answer.value = ''
  widgetError.value = 'بارگذاری تایید امنیتی ناموفق بود. صفحه را تازه کنید.'
  emitPayload()
}

async function refresh() {
  answer.value = ''
  captchaId.value = ''
  question.value = ''
  widgetError.value = ''
  emitPayload()
  if (props.mode === 'math') {
    await loadMath()
    return
  }
  await nextTick()
  renderTurnstile(true)
}

async function loadMath() {
  try {
    const { data } = await api.get('/auth/captcha')
    const payload = data?.data || {}
    captchaId.value = payload.id || ''
    question.value = payload.question || ''
  } catch {
    question.value = 'خطا در بارگذاری کپچا'
  }
}

function loadScript() {
  return new Promise((resolve, reject) => {
    if (window.turnstile) {
      resolve()
      return
    }
    const existing = document.getElementById('cf-turnstile-script')
    if (existing) {
      existing.addEventListener('load', () => resolve())
      existing.addEventListener('error', () =>
        reject(new Error('turnstile script'))
      )
      if (window.turnstile) resolve()
      return
    }
    const s = document.createElement('script')
    s.id = 'cf-turnstile-script'
    s.src =
      'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit'
    s.async = true
    s.onload = () => resolve()
    s.onerror = () => reject(new Error('turnstile script'))
    document.head.appendChild(s)
  })
}

function renderTurnstile(force = false) {
  if (!el.value || !props.siteKey || !window.turnstile) return
  if (force && widgetId != null) {
    try {
      window.turnstile.remove(widgetId)
    } catch {
      /* ignore */
    }
    widgetId = null
    el.value.innerHTML = ''
  }
  if (widgetId != null) return
  const options = {
    sitekey: props.siteKey,
    callback: onToken,
    'expired-callback': onExpired,
    'error-callback': onError,
  }
  if (props.action) {
    options.action = props.action
  }
  widgetId = window.turnstile.render(el.value, options)
}

onMounted(async () => {
  if (props.mode === 'turnstile' && props.siteKey) {
    try {
      await loadScript()
      await nextTick()
      renderTurnstile()
    } catch {
      widgetError.value = 'بارگذاری تایید امنیتی ناموفق بود. صفحه را تازه کنید.'
    }
  } else {
    await loadMath()
  }
})

watch(
  () => [props.mode, props.siteKey, props.action],
  async () => {
    widgetId = null
    if (props.mode === 'turnstile' && props.siteKey) {
      try {
        await loadScript()
        await nextTick()
        renderTurnstile(true)
      } catch {
        widgetError.value =
          'بارگذاری تایید امنیتی ناموفق بود. صفحه را تازه کنید.'
      }
    } else {
      await loadMath()
    }
  }
)

onUnmounted(() => {
  if (widgetId != null && window.turnstile) {
    try {
      window.turnstile.remove(widgetId)
    } catch {
      /* ignore */
    }
  }
})

defineExpose({ refresh, emitPayload })
</script>
