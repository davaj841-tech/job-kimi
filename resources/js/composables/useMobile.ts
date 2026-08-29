import { onBeforeUnmount, onMounted, ref } from 'vue'

const MOBILE_MAX = 1023

function readIsMobile(): boolean {
  if (typeof window === 'undefined') return false
  return window.matchMedia(`(max-width: ${MOBILE_MAX}px)`).matches
}

const isMobile = ref(readIsMobile())
let listeners = 0
let mediaQuery: MediaQueryList | null = null

function onResize(event: MediaQueryListEvent): void {
  isMobile.value = event.matches
}

function ensureListener(): void {
  if (typeof window === 'undefined' || mediaQuery) return

  mediaQuery = window.matchMedia(`(max-width: ${MOBILE_MAX}px)`)
  if (typeof mediaQuery.addEventListener === 'function') {
    mediaQuery.addEventListener('change', onResize)
  } else {
    mediaQuery.addListener(onResize)
  }
}

function releaseListener(): void {
  if (!mediaQuery) return

  if (typeof mediaQuery.removeEventListener === 'function') {
    mediaQuery.removeEventListener('change', onResize)
  } else {
    mediaQuery.removeListener(onResize)
  }

  mediaQuery = null
}

export function useMobile() {
  onMounted(() => {
    listeners += 1
    isMobile.value = readIsMobile()
    ensureListener()
  })

  onBeforeUnmount(() => {
    listeners = Math.max(0, listeners - 1)
    if (listeners === 0) {
      releaseListener()
    }
  })

  return { isMobile }
}

export function isMobileViewport(): boolean {
  return readIsMobile()
}
