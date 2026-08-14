import { ref, watch } from 'vue'
import { applySiteTheme, useSiteTheme } from './useSiteTheme'

const STORAGE_KEY = 'ja_theme'

function initialDark(): boolean {
  if (typeof window === 'undefined') return false
  return localStorage.getItem(STORAGE_KEY) === 'dark'
}

const isDark = ref(initialDark())

let wired = false

function ensureWired(): void {
  if (wired || typeof document === 'undefined') return
  wired = true
  watch(
    isDark,
    (val) => {
      localStorage.setItem(STORAGE_KEY, val ? 'dark' : 'light')
      document.documentElement.classList.toggle('dark', val)
      // Re-apply brand theme without stomping dark surface tokens
      const { layout, primary, secondary, font, fontSize } = useSiteTheme()
      applySiteTheme({
        homepage_layout: layout.value,
        primary_color: primary.value,
        secondary_color: secondary.value,
        site_font: font.value,
        site_font_size: fontSize.value,
      })
    },
    { immediate: true }
  )
}

export function useDarkMode() {
  ensureWired()

  const toggle = () => {
    isDark.value = !isDark.value
  }

  return { isDark, toggle }
}
