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

function registerPwa() {
  if (!('serviceWorker' in navigator)) return
  // SW lives under /build so precache paths resolve correctly; scope is whole site.
  navigator.serviceWorker
    .register('/build/sw.js', { scope: '/' })
    .catch(() => {
      registerSW({
        immediate: true,
        onOfflineReady() {
          console.log('App ready to work offline')
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

useFeatureStore(pinia)
  .fetch()
  .catch(() => {})

useSiteTheme()
  .ensureLoaded()
  .catch(() => {})

app.mount('#app')
