import { defineStore } from 'pinia'
import { ref, watch } from 'vue'
import { applySiteTheme, useSiteTheme } from '../composables/useSiteTheme'

const STORAGE_KEY = 'ja_theme'

function readInitialDark(): boolean {
  if (typeof window === 'undefined') return false

  const stored = localStorage.getItem(STORAGE_KEY)
  if (stored === 'dark') return true
  if (stored === 'light') return false

  return window.matchMedia('(prefers-color-scheme: dark)').matches
}

function hasExplicitPreference(): boolean {
  if (typeof window === 'undefined') return false
  const stored = localStorage.getItem(STORAGE_KEY)
  return stored === 'dark' || stored === 'light'
}

export const useThemeStore = defineStore('theme', () => {
  const isDark = ref(readInitialDark())
  let wired = false
  let mediaQuery: MediaQueryList | null = null

  function applyTheme(val: boolean): void {
    if (typeof document === 'undefined') return

    document.documentElement.classList.toggle('dark', val)

    const { layout, primary, secondary, font, fontSize } = useSiteTheme()
    applySiteTheme({
      homepage_layout: layout.value,
      primary_color: primary.value,
      secondary_color: secondary.value,
      site_font: font.value,
      site_font_size: fontSize.value,
    })
  }

  function persistTheme(val: boolean): void {
    if (typeof localStorage === 'undefined') return
    localStorage.setItem(STORAGE_KEY, val ? 'dark' : 'light')
  }

  function init(): void {
    if (wired || typeof window === 'undefined') return
    wired = true

    watch(
      isDark,
      (val) => {
        applyTheme(val)
      },
      { immediate: true }
    )

    mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
    const onSystemChange = (event: MediaQueryListEvent) => {
      if (!hasExplicitPreference()) {
        isDark.value = event.matches
      }
    }

    if (typeof mediaQuery.addEventListener === 'function') {
      mediaQuery.addEventListener('change', onSystemChange)
    } else {
      mediaQuery.addListener(onSystemChange)
    }
  }

  function toggle(): void {
    isDark.value = !isDark.value
    persistTheme(isDark.value)
  }

  function setDark(val: boolean): void {
    isDark.value = val
    persistTheme(val)
  }

  return { isDark, toggle, setDark, init }
})
