import { computed, ref } from 'vue'
import api from '../api/client'
import {
  type SiteThemeId,
  themePreset,
  isSiteTheme,
} from '../theme/presets'

const layout = ref<SiteThemeId>('atlas')
const primary = ref('#f97316')
const secondary = ref('#0f2744')
let loaded = false
let inflight: Promise<void> | null = null

function hexToRgb(hex: string, fallback = '15 39 68'): string {
  const raw = hex.replace('#', '').trim()
  const full =
    raw.length === 3
      ? raw
          .split('')
          .map((c) => c + c)
          .join('')
      : raw
  if (!/^[0-9a-fA-F]{6}$/.test(full)) return fallback
  const n = Number.parseInt(full, 16)
  return `${(n >> 16) & 255} ${(n >> 8) & 255} ${n & 255}`
}

function mixHex(hex: string, toward: string, amount: number): string {
  const parse = (h: string) => {
    const rgb = hexToRgb(h, '0 0 0').split(' ').map(Number)
    return rgb
  }
  const a = parse(hex)
  const b = parse(toward)
  const mix = a.map((v, i) => Math.round(v + (b[i] - v) * amount))
  return `#${mix.map((v) => v.toString(16).padStart(2, '0')).join('')}`
}

export function applySiteTheme(input: {
  homepage_layout?: unknown
  primary_color?: unknown
  secondary_color?: unknown
}): void {
  if (isSiteTheme(input.homepage_layout)) {
    layout.value = input.homepage_layout
  }

  const preset = themePreset(layout.value)
  const nextPrimary =
    typeof input.primary_color === 'string' && input.primary_color
      ? input.primary_color
      : preset.primary
  const nextSecondary =
    typeof input.secondary_color === 'string' && input.secondary_color
      ? input.secondary_color
      : preset.secondary

  primary.value = nextPrimary
  secondary.value = nextSecondary

  const ink2 = mixHex(nextSecondary, '#ffffff', 0.12)
  const brandDark = mixHex(nextPrimary, '#000000', 0.18)
  const brandSoft = mixHex(nextPrimary, '#ffffff', 0.88)
  const page = preset.page
  const line = mixHex(nextSecondary, '#ffffff', preset.darkHero ? 0.82 : 0.88)

  const root = document.documentElement
  root.dataset.theme = layout.value
  root.style.setProperty('--c-brand', hexToRgb(nextPrimary, '249 115 22'))
  root.style.setProperty('--c-brand-dark', hexToRgb(brandDark, '211 47 65'))
  root.style.setProperty('--c-brand-soft', hexToRgb(brandSoft, '255 241 242'))
  root.style.setProperty('--c-ink', hexToRgb(nextSecondary, '15 39 68'))
  root.style.setProperty('--c-ink-2', hexToRgb(ink2, '30 58 95'))
  root.style.setProperty('--c-accent', hexToRgb(nextPrimary, '249 115 22'))
  root.style.setProperty('--c-page', hexToRgb(page, '248 250 252'))
  root.style.setProperty('--c-line', hexToRgb(line, '226 232 240'))
  root.style.setProperty('--theme-ink', nextSecondary)
  root.style.setProperty('--theme-ink-2', ink2)
  root.style.setProperty('--theme-accent', nextPrimary)
  root.style.setProperty('--theme-page', page)

  const meta = document.querySelector('meta[name="theme-color"]')
  if (meta) meta.setAttribute('content', nextPrimary)
}

export function useSiteTheme() {
  async function ensureLoaded(force = false) {
    if (loaded && !force) return
    if (inflight) return inflight
    inflight = api
      .get('/settings/public')
      .then(({ data }) => {
        applySiteTheme(data?.data || {})
        loaded = true
      })
      .catch(() => undefined)
      .finally(() => {
        inflight = null
      })
    return inflight
  }

  return {
    layout,
    primary,
    secondary,
    ensureLoaded,
    applySiteTheme,
    preset: computed(() => themePreset(layout.value)),
    isDarkHero: computed(() => themePreset(layout.value).darkHero),
    heroStyle: computed(() => themePreset(layout.value).hero),
    plansVariant: computed(() => themePreset(layout.value).plans),
  }
}
