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
const fontSize = ref(16)
const primary = ref('#f97316')
const secondary = ref('#0f2744')
const siteName = ref('جاب‌آزمون')
const siteLogo = ref('')
const logoMobile = ref('')
const logoDark = ref('')
const siteFavicon = ref('')
const instagramUrl = ref('')
const telegramUrl = ref('')
const whatsappUrl = ref('')
const rubikaUrl = ref('')
const baleUrl = ref('')
const androidPlayUrl = ref('')
const androidBazaarUrl = ref('')
const androidDirectUrl = ref('')
const enamadEnabled = ref(false)
const enamadUrl = ref('')
const enamadLogoUrl = ref('')
const enamadCode = ref('')
const samandehiUrl = ref('')
let loaded = false
let inflight: Promise<void> | null = null

function asUrl(v: unknown): string {
  return typeof v === 'string' ? v.trim() : ''
}

function applyFavicon(href: string) {
  if (typeof document === 'undefined' || !href) return
  let link = document.querySelector<HTMLLinkElement>(
    'link[rel="icon"][data-ja]'
  )
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
  site_font_size?: unknown
  site_name?: unknown
  site_logo?: unknown
  logo_mobile?: unknown
  logo_light?: unknown
  logo_dark?: unknown
  site_favicon?: unknown
  instagram_url?: unknown
  telegram_url?: unknown
  whatsapp_url?: unknown
  rubika_url?: unknown
  bale_url?: unknown
  android_play_url?: unknown
  android_bazaar_url?: unknown
  android_direct_url?: unknown
  enamad_enabled?: unknown
  enamad_url?: unknown
  enamad_logo_url?: unknown
  enamad_code?: unknown
  samandehi_url?: unknown
}): void {
  if (typeof input.site_name === 'string' && input.site_name.trim()) {
    siteName.value = input.site_name.trim()
  }
  if ('site_logo' in input) siteLogo.value = asUrl(input.site_logo)
  if ('logo_mobile' in input) logoMobile.value = asUrl(input.logo_mobile)
  if ('logo_light' in input && !asUrl(input.logo_mobile)) {
    logoMobile.value = asUrl(input.logo_light)
  }
  if ('logo_dark' in input) logoDark.value = asUrl(input.logo_dark)
  if ('instagram_url' in input) instagramUrl.value = asUrl(input.instagram_url)
  if ('telegram_url' in input) telegramUrl.value = asUrl(input.telegram_url)
  if ('whatsapp_url' in input) whatsappUrl.value = asUrl(input.whatsapp_url)
  if ('rubika_url' in input) rubikaUrl.value = asUrl(input.rubika_url)
  if ('bale_url' in input) baleUrl.value = asUrl(input.bale_url)
  if ('android_play_url' in input)
    androidPlayUrl.value = asUrl(input.android_play_url)
  if ('android_bazaar_url' in input)
    androidBazaarUrl.value = asUrl(input.android_bazaar_url)
  if ('android_direct_url' in input)
    androidDirectUrl.value = asUrl(input.android_direct_url)
  if ('enamad_enabled' in input)
    enamadEnabled.value = Boolean(input.enamad_enabled)
  if ('enamad_url' in input) enamadUrl.value = asUrl(input.enamad_url)
  if ('enamad_logo_url' in input)
    enamadLogoUrl.value = asUrl(input.enamad_logo_url)
  if ('enamad_code' in input) enamadCode.value = asUrl(input.enamad_code)
  if ('samandehi_url' in input) samandehiUrl.value = asUrl(input.samandehi_url)
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
  if ('site_font_size' in input) {
    const n = Number(input.site_font_size)
    if (Number.isFinite(n))
      fontSize.value = Math.min(20, Math.max(15, Math.round(n)))
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
  root.style.setProperty('--font-size-site', `${fontSize.value}px`)
  root.style.fontSize = `${fontSize.value}px`
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

/** لوگوی سایت از فیلد اصلی؛ در صورت خالی بودن از لوگوهای قدیمی */
export function resolveBrandLogo(_opts?: { mobile?: boolean }): string {
  return siteLogo.value || logoDark.value || logoMobile.value || ''
}

export function useSiteTheme() {
  async function ensureLoaded(force = false) {
    if (loaded && !force) return
    if (inflight && !force) return inflight
    if (force) {
      loaded = false
      inflight = null
    }
    inflight = api
      .get('/settings/public', {
        params: force ? { _ts: Date.now() } : undefined,
      })
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
    fontSize,
    primary,
    secondary,
    siteName,
    siteLogo,
    logoMobile,
    logoDark,
    siteFavicon,
    instagramUrl,
    telegramUrl,
    whatsappUrl,
    rubikaUrl,
    baleUrl,
    androidPlayUrl,
    androidBazaarUrl,
    androidDirectUrl,
    enamadEnabled,
    enamadUrl,
    enamadLogoUrl,
    enamadCode,
    samandehiUrl,
    ensureLoaded,
    applySiteTheme,
    resolveBrandLogo,
    preset: computed(() => themePreset(layout.value)),
    isDarkHero: computed(() => themePreset(layout.value).darkHero),
    heroStyle: computed(() => themePreset(layout.value).hero),
    plansVariant: computed(() => themePreset(layout.value).plans),
  }
}
