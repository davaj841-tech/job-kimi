import '../../css/app.css'
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { useSiteTheme } from '../composables/useSiteTheme'
import { useThemeStore } from '../stores/themeStore'

const app = createApp(App)
const pinia = createPinia()
app.use(pinia)
app.use(router)

useThemeStore(pinia).init()

useSiteTheme()
  .ensureLoaded()
  .catch(() => {})

app.mount('#admin-app')
