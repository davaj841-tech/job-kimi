import './bootstrap'
import '../css/app.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { registerSW } from 'virtual:pwa-register'
import * as Sentry from '@sentry/vue'
import App from './App.vue'
import router from './router'
import { useFeatureStore } from './stores/feature'
import { useSiteTheme } from './composables/useSiteTheme'
import { useThemeStore } from './stores/themeStore'

function activateWaitingWorker(reg: ServiceWorkerRegistration): void {
  if (reg.waiting) {
    reg.waiting.postMessage({ type: 'SKIP_WAITING' })
  }
}

function registerPwa(): void {
  if (!('serviceWorker' in navigator)) return

  // SW lives under /build so precache paths resolve correctly; scope is whole site.
  navigator.serviceWorker
    .register('/build/sw.js', { scope: '/' })
    .then((reg) => {
      activateWaitingWorker(reg)

      reg.addEventListener('updatefound', () => {
        const worker = reg.installing
        if (!worker) return
        worker.addEventListener('statechange', () => {
          if (
            worker.state === 'installed' &&
            navigator.serviceWorker.controller
          ) {
            activateWaitingWorker(reg)
            window.location.reload()
          }
        })
      })

      let refreshing = false
      navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (refreshing) return
        refreshing = true
        window.location.reload()
      })
    })
    .catch(() => {
      registerSW({
        immediate: true,
        onNeedRefresh() {
          window.location.reload()
        },
        onOfflineReady() {
          /* PWA precache ready */
        },
      })
    })
}
registerPwa()

const app = createApp(App)

if (import.meta.env.PROD && import.meta.env.VITE_SENTRY_DSN) {
  Sentry.init({
    app,
    dsn: import.meta.env.VITE_SENTRY_DSN,
    integrations: [Sentry.browserTracingIntegration({ router })],
    tracesSampleRate: 0.1,
  })
}

const pinia = createPinia()
app.use(pinia)
app.use(router)

useThemeStore(pinia).init()

useFeatureStore(pinia)
  .fetch()
  .catch(() => {})

useSiteTheme()
  .ensureLoaded()
  .catch(() => {})

app.mount('#app')
