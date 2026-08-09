import './bootstrap'
import '../css/app.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { registerSW } from 'virtual:pwa-register'
import * as Sentry from '@sentry/vue'
import App from './App.vue'
import router from './router'

registerSW({ immediate: true })

const app = createApp(App)

if (import.meta.env.PROD && import.meta.env.VITE_SENTRY_DSN) {
  Sentry.init({
    app,
    dsn: import.meta.env.VITE_SENTRY_DSN,
    integrations: [Sentry.browserTracingIntegration({ router })],
    tracesSampleRate: 0.1,
  })
}

app.use(createPinia())
app.use(router)
app.mount('#app')
