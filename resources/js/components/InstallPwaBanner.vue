<template>
  <div
    v-if="visible"
    class="fixed inset-x-0 z-50 px-3 lg:bottom-4 lg:left-auto lg:right-4 lg:w-80 lg:px-0"
    :style="{ bottom: 'calc(4.25rem + env(safe-area-inset-bottom))' }"
  >
    <div
      class="flex items-center gap-3 rounded-2xl px-3 py-2.5 text-white shadow-lg"
      style="background: var(--theme-ink)"
    >
      <p class="min-w-0 flex-1 text-xs leading-5">
        {{
          ios
            ? 'برای میانبر، دکمه Share و سپس Add to Home Screen را بزنید.'
            : 'میانبر جاب‌آزمون را به صفحه اصلی دستگاه اضافه کنید.'
        }}
      </p>
      <button
        v-if="!ios"
        type="button"
        class="shrink-0 rounded-xl bg-brand px-3 py-1.5 text-xs font-bold"
        @click="install"
      >
        نصب
      </button>
      <button
        type="button"
        class="shrink-0 text-white/60 hover:text-white"
        aria-label="بستن"
        @click="dismiss"
      >
        ✕
      </button>
    </div>
  </div>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from 'vue'

const KEY = 'ja_pwa_install_dismissed'
const visible = ref(false)
const ios = ref(false)
let deferred = null

function isStandalone() {
  return (
    window.matchMedia('(display-mode: standalone)').matches ||
    window.navigator.standalone === true
  )
}

function isIos() {
  const ua = window.navigator.userAgent || ''
  return /iphone|ipad|ipod/i.test(ua)
}

function onPrompt(e) {
  e.preventDefault()
  deferred = e
  if (!localStorage.getItem(KEY) && !isStandalone()) visible.value = true
}

async function install() {
  if (!deferred) {
    visible.value = false
    return
  }
  deferred.prompt()
  await deferred.userChoice.catch(() => null)
  deferred = null
  visible.value = false
  localStorage.setItem(KEY, '1')
}

function dismiss() {
  visible.value = false
  localStorage.setItem(KEY, '1')
}

onMounted(() => {
  if (isStandalone() || localStorage.getItem(KEY)) return
  ios.value = isIos() && !window.MSStream
  if (ios.value) visible.value = true
  window.addEventListener('beforeinstallprompt', onPrompt)
  window.addEventListener('appinstalled', dismiss)
})

onUnmounted(() => {
  window.removeEventListener('beforeinstallprompt', onPrompt)
  window.removeEventListener('appinstalled', dismiss)
})
</script>
