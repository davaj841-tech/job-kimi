<template>
  <div class="space-y-2">
    <div v-if="mode === 'turnstile'" class="flex justify-center">
      <div ref="el" class="cf-turnstile"></div>
    </div>
    <div v-else class="rounded-xl border border-surface-line bg-surface-page p-3">
      <div class="mb-2 flex items-center justify-between gap-2">
        <p class="text-xs font-bold text-ink-soft">کپچا امنیتی</p>
        <button
          type="button"
          class="text-[11px] font-bold text-brand"
          @click="refresh"
        >
          بازنشانی
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
import api from '../../api/client'

const props = defineProps({
  mode: { type: String, default: 'math' }, // turnstile | math
  siteKey: { type: String, default: '' },
})

const emit = defineEmits(['update'])

const el = ref(null)
const question = ref('')
const captchaId = ref('')
const answer = ref('')
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
  answer.value = token
  emitPayload()
}

function onExpired() {
  answer.value = ''
  emitPayload()
}

async function refresh() {
  answer.value = ''
  captchaId.value = ''
  question.value = ''
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
  return new Promise((resolve) => {
    if (window.turnstile) {
      resolve()
      return
    }
    const existing = document.getElementById('cf-turnstile-script')
    if (existing) {
      existing.addEventListener('load', () => resolve())
      if (window.turnstile) resolve()
      return
    }
    const s = document.createElement('script')
    s.id = 'cf-turnstile-script'
    s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit'
    s.async = true
    s.onload = () => resolve()
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
  widgetId = window.turnstile.render(el.value, {
    sitekey: props.siteKey,
    callback: onToken,
    'expired-callback': onExpired,
  })
}

onMounted(async () => {
  if (props.mode === 'turnstile' && props.siteKey) {
    await loadScript()
    await nextTick()
    renderTurnstile()
  } else {
    await loadMath()
  }
})

watch(
  () => [props.mode, props.siteKey],
  async () => {
    widgetId = null
    if (props.mode === 'turnstile' && props.siteKey) {
      await loadScript()
      await nextTick()
      renderTurnstile(true)
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
