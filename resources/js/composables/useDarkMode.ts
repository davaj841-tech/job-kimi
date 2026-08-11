import { ref, watch } from 'vue'

const STORAGE_KEY = 'ja_theme'

function initialDark(): boolean {
  if (typeof window === 'undefined') return false
  const stored = localStorage.getItem(STORAGE_KEY)
  // Marketing UI is light-first (trust/motivation); only dark when user chose it.
  if (stored === 'dark') return true
  return false
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
