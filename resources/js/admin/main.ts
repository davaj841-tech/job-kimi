import '../../css/app.css'
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { useSiteTheme } from '../composables/useSiteTheme'

const app = createApp(App)
app.use(createPinia())
app.use(router)

useSiteTheme()
  .ensureLoaded()
  .catch(() => {})

app.mount('#admin-app')
