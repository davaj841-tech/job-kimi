export const SITE_THEMES = [
  'atlas',
  'editorial',
  'studio',
  'minimal',
  'emerald',
  'ocean',
  'royal',
  'rose',
  'sand',
  'midnight',
] as const

export type SiteThemeId = (typeof SITE_THEMES)[number]
export type HeroStyle = 'navy' | 'paper' | 'dark' | 'search' | 'split'
export type PlansVariant = 'compact' | 'strip' | 'dark' | 'rows'

export interface SiteThemePreset {
  id: SiteThemeId
  title: string
  desc: string
  primary: string
  secondary: string
  page: string
  hero: HeroStyle
  darkHero: boolean
  plans: PlansVariant
  preview: string
}

export const SITE_THEME_PRESETS: Record<SiteThemeId, SiteThemePreset> = {
  atlas: {
    id: 'atlas',
    title: 'اطلس — حرفه‌ای',
    desc: 'سرمه‌ای و نارنجی سازمانی؛ مناسب برند رسمی جاب‌آزمون.',
    primary: '#f97316',
    secondary: '#0f2744',
    page: '#f8fafc',
    hero: 'navy',
    darkHero: true,
    plans: 'compact',
    preview: 'bg-gradient-to-l from-[#0f2744] to-[#163556]',
  },
  editorial: {
    id: 'editorial',
    title: 'تحریریه — مجله‌ای',
    desc: 'کاغذی گرم و آجری؛ حس تحریریه و مطالعه.',
    primary: '#c2410c',
    secondary: '#7c2d12',
    page: '#f6f1e8',
    hero: 'paper',
    darkHero: false,
    plans: 'strip',
    preview: 'bg-gradient-to-l from-[#f6f1e8] to-[#c2410c]',
  },
  studio: {
    id: 'studio',
    title: 'استودیو — تیره مدرن',
    desc: 'پس‌زمینه تیره شیشه‌ای با قرمز زنده.',
    primary: '#e11d48',
    secondary: '#0b1a2e',
    page: '#0f172a',
    hero: 'dark',
    darkHero: true,
    plans: 'dark',
    preview: 'bg-gradient-to-l from-[#0b1a2e] to-[#e11d48]',
  },
  minimal: {
    id: 'minimal',
    title: 'مینیمال — خلوت',
    desc: 'سفید، زغال و خطوط ساده بدون شلوغی.',
    primary: '#0f172a',
    secondary: '#334155',
    page: '#ffffff',
    hero: 'search',
    darkHero: false,
    plans: 'rows',
    preview: 'bg-gradient-to-l from-slate-100 to-slate-400',
  },
  emerald: {
    id: 'emerald',
    title: 'زمرد — طبیعت',
    desc: 'سبز جنگلی و طلایی؛ حس رشد و قبولی.',
    primary: '#d97706',
    secondary: '#065f46',
    page: '#ecfdf5',
    hero: 'split',
    darkHero: true,
    plans: 'compact',
    preview: 'bg-gradient-to-l from-[#065f46] to-[#d97706]',
  },
  ocean: {
    id: 'ocean',
    title: 'اقیانوس — آبی',
    desc: 'فیروزه‌ای روشن روی آبی عمیق.',
    primary: '#06b6d4',
    secondary: '#0e7490',
    page: '#ecfeff',
    hero: 'navy',
    darkHero: true,
    plans: 'compact',
    preview: 'bg-gradient-to-l from-[#0e7490] to-[#67e8f9]',
  },
  royal: {
    id: 'royal',
    title: 'سلطنتی — بنفش',
    desc: 'بنفش فاخر و طلایی برای حس ویژه.',
    primary: '#f59e0b',
    secondary: '#4c1d95',
    page: '#f5f3ff',
    hero: 'navy',
    darkHero: true,
    plans: 'compact',
    preview: 'bg-gradient-to-l from-[#4c1d95] to-[#f59e0b]',
  },
  rose: {
    id: 'rose',
    title: 'رز — نرم',
    desc: 'صورتی ملایم و گوشه‌های نرم.',
    primary: '#e11d48',
    secondary: '#9f1239',
    page: '#fff1f2',
    hero: 'paper',
    darkHero: false,
    plans: 'strip',
    preview: 'bg-gradient-to-l from-[#fff1f2] to-[#e11d48]',
  },
  sand: {
    id: 'sand',
    title: 'شنزار — گرم',
    desc: 'خاکی و کهربایی، گرم و صمیمی.',
    primary: '#ea580c',
    secondary: '#78350f',
    page: '#fffbeb',
    hero: 'split',
    darkHero: false,
    plans: 'strip',
    preview: 'bg-gradient-to-l from-[#78350f] to-[#fbbf24]',
  },
  midnight: {
    id: 'midnight',
    title: 'نیمه‌شب — نئون',
    desc: 'سیاه عمیق و آبی الکتریکی.',
    primary: '#38bdf8',
    secondary: '#020617',
    page: '#020617',
    hero: 'dark',
    darkHero: true,
    plans: 'dark',
    preview: 'bg-gradient-to-l from-[#020617] to-[#38bdf8]',
  },
}

export function isSiteTheme(value: unknown): value is SiteThemeId {
  return typeof value === 'string' && (SITE_THEMES as readonly string[]).includes(value)
}

export function themePreset(id: unknown): SiteThemePreset {
  return isSiteTheme(id) ? SITE_THEME_PRESETS[id] : SITE_THEME_PRESETS.atlas
}
