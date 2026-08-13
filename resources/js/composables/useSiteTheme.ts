import { computed, ref } from 'vue'
import api from '../api/client'
import {
  type SiteFontId,
  type SiteThemeId,
  fontPreset,
  isSiteFont,
  isSiteTheme,
  themePreset,
} from '../theme/presets'

const layout = ref<SiteThemeId>('atlas')
const font = ref<SiteFontId>('estedad')
const primary = ref('#f97316')
const secondary = ref('#0f2744')
const siteName = ref('جاب‌آزمون')
const siteLogo = ref('')
const logoLight = ref('')
const logoDark = ref('')
const siteFavicon = ref('')
let loaded = false
let inflight: Promise<void> | null = null

function asUrl(v: unknown): string {
  return typeof v === 'string' ? v.trim() : ''
}

function applyFavicon(href: string) {
  if (typeof document === 'undefined' || !href) return
  let link = document.querySelector<HTMLLinkElement>('link[rel="icon"][data-ja]')
  if (!link) {
    link = document.createElement('link')
    link.rel = 'icon'
    link.setAttribute('data-ja', '1')
    document.head.appendChild(link)
  }
  link.href = href
}

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

/** Brand/navy vars only — page/text/surface stay in CSS so html.dark works. */
export function applySiteTheme(input: {
  homepage_layout?: unknown
  primary_color?: unknown
  secondary_color?: unknown
  site_font?: unknown
  site_name?: unknown
  site_logo?: unknown
  logo_light?: unknown
  logo_dark?: unknown
  site_favicon?: unknown
}): void {
  if (typeof input.site_name === 'string' && input.site_name.trim()) {
    siteName.value = input.site_name.trim()
  }
  if ('site_logo' in input) siteLogo.value = asUrl(input.site_logo)
  if ('logo_light' in input) logoLight.value = asUrl(input.logo_light)
  if ('logo_dark' in input) logoDark.value = asUrl(input.logo_dark)
  if ('site_favicon' in input) {
    siteFavicon.value = asUrl(input.site_favicon)
    applyFavicon(siteFavicon.value)
  }

  if (isSiteTheme(input.homepage_layout)) {
    layout.value = input.homepage_layout
  }
  if (isSiteFont(input.site_font)) {
    font.value = input.site_font
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
  const brandSoftLight = mixHex(nextPrimary, '#ffffff', 0.88)
  const brandSoftDark = mixHex(nextPrimary, '#000000', 0.55)
  const page = preset.page
  const line = mixHex(nextSecondary, '#ffffff', preset.darkHero ? 0.82 : 0.88)
  const isDark =
    typeof document !== 'undefined' &&
    document.documentElement.classList.contains('dark')

  const root = document.documentElement
  const nextFont = fontPreset(font.value)
  root.dataset.theme = layout.value
  root.dataset.font = nextFont.id
  root.style.setProperty('--font-site', nextFont.family)
  root.style.setProperty('--c-brand', hexToRgb(nextPrimary, '249 115 22'))
  root.style.setProperty('--c-brand-dark', hexToRgb(brandDark, '211 47 65'))
  root.style.setProperty(
    '--c-brand-soft',
    hexToRgb(isDark ? brandSoftDark : brandSoftLight, '255 241 242')
  )
  root.style.setProperty('--c-navy', hexToRgb(nextSecondary, '15 39 68'))
  root.style.setProperty('--c-ink', hexToRgb(nextSecondary, '15 39 68'))
  root.style.setProperty('--c-ink-2', hexToRgb(ink2, '30 58 95'))
  root.style.setProperty('--c-accent', hexToRgb(nextPrimary, '249 115 22'))
  root.style.setProperty('--theme-ink', nextSecondary)
  root.style.setProperty('--theme-ink-2', ink2)
  root.style.setProperty('--theme-accent', nextPrimary)

  // Never inline-override surface tokens while dark — let html.dark CSS win.
  if (isDark) {
    root.style.removeProperty('--c-page')
    root.style.removeProperty('--c-line')
    root.style.removeProperty('--c-surface')
    root.style.removeProperty('--c-text')
    root.style.removeProperty('--c-muted')
    root.style.removeProperty('--c-soft')
    root.style.setProperty('--theme-page', '#0f172a')
  } else {
    root.style.setProperty('--c-page', hexToRgb(page, '248 250 252'))
    root.style.setProperty('--c-line', hexToRgb(line, '226 232 240'))
    root.style.setProperty('--c-surface', '255 255 255')
    root.style.setProperty('--c-text', '30 41 59')
    root.style.setProperty('--c-muted', '100 116 139')
    root.style.setProperty('--c-soft', '71 85 105')
    root.style.setProperty('--theme-page', page)
  }

  const meta = document.querySelector('meta[name="theme-color"]')
  if (meta) meta.setAttribute('content', isDark ? '#0f172a' : nextPrimary)
}

/**
 * logo_light = for light backgrounds (dark-colored mark)
 * logo_dark  = for dark backgrounds / dark mode (light-colored mark)
 */
export function resolveBrandLogo(opts?: { forDarkBg?: boolean }): string {
  const darkBg =
    opts?.forDarkBg ??
    (typeof document !== 'undefined' &&
      document.documentElement.classList.contains('dark'))

  if (darkBg) {
    return logoDark.value || logoLight.value || siteLogo.value || ''
  }
  return logoLight.value || siteLogo.value || logoDark.value || ''
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
    font,
    primary,
    secondary,
    siteName,
    siteLogo,
    logoLight,
    logoDark,
    siteFavicon,
    ensureLoaded,
    applySiteTheme,
    resolveBrandLogo,
    preset: computed(() => themePreset(layout.value)),
    isDarkHero: computed(() => themePreset(layout.value).darkHero),
    heroStyle: computed(() => themePreset(layout.value).hero),
    plansVariant: computed(() => themePreset(layout.value).plans),
  }
}
