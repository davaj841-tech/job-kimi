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
  'coral',
  'lime',
  'indigo',
  'coffee',
  'cherry',
  'glacier',
  'sunset',
  'olive',
  'graphite',
  'blossom',
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

function preset(
  id: SiteThemeId,
  title: string,
  desc: string,
  primary: string,
  secondary: string,
  page: string,
  hero: HeroStyle,
  darkHero: boolean,
  plans: PlansVariant,
  preview: string,
): SiteThemePreset {
  return {
    id,
    title,
    desc,
    primary,
    secondary,
    page,
    hero,
    darkHero,
    plans,
    preview,
  }
}

export const SITE_THEME_PRESETS: Record<SiteThemeId, SiteThemePreset> = {
  atlas: preset('atlas', 'اطلس — حرفه‌ای', 'سرمه‌ای و نارنجی سازمانی.', '#f97316', '#0f2744', '#f8fafc', 'navy', true, 'compact', 'bg-gradient-to-l from-[#0f2744] to-[#f97316]'),
  editorial: preset('editorial', 'تحریریه — مجله‌ای', 'کاغذی گرم و آجری.', '#c2410c', '#7c2d12', '#f6f1e8', 'paper', false, 'strip', 'bg-gradient-to-l from-[#f6f1e8] to-[#c2410c]'),
  studio: preset('studio', 'استودیو — تیره', 'تیره شیشه‌ای با قرمز زنده.', '#e11d48', '#0b1a2e', '#0f172a', 'dark', true, 'dark', 'bg-gradient-to-l from-[#0b1a2e] to-[#e11d48]'),
  minimal: preset('minimal', 'مینیمال — خلوت', 'سفید و زغالی، بدون شلوغی.', '#0f172a', '#334155', '#ffffff', 'search', false, 'rows', 'bg-gradient-to-l from-slate-100 to-slate-500'),
  emerald: preset('emerald', 'زمرد — طبیعت', 'سبز جنگلی و طلایی.', '#d97706', '#065f46', '#ecfdf5', 'split', true, 'compact', 'bg-gradient-to-l from-[#065f46] to-[#d97706]'),
  ocean: preset('ocean', 'اقیانوس — آبی', 'فیروزه‌ای روی آبی عمیق.', '#06b6d4', '#0e7490', '#ecfeff', 'navy', true, 'compact', 'bg-gradient-to-l from-[#0e7490] to-[#67e8f9]'),
  royal: preset('royal', 'سلطنتی — بنفش', 'بنفش فاخر و طلایی.', '#f59e0b', '#4c1d95', '#f5f3ff', 'navy', true, 'compact', 'bg-gradient-to-l from-[#4c1d95] to-[#f59e0b]'),
  rose: preset('rose', 'رز — نرم', 'صورتی ملایم و گوشه‌های نرم.', '#e11d48', '#9f1239', '#fff1f2', 'paper', false, 'strip', 'bg-gradient-to-l from-[#fff1f2] to-[#e11d48]'),
  sand: preset('sand', 'شنزار — گرم', 'خاکی و کهربایی.', '#ea580c', '#78350f', '#fffbeb', 'split', false, 'strip', 'bg-gradient-to-l from-[#78350f] to-[#fbbf24]'),
  midnight: preset('midnight', 'نیمه‌شب — نئون', 'سیاه عمیق و آبی الکتریکی.', '#38bdf8', '#020617', '#020617', 'dark', true, 'dark', 'bg-gradient-to-l from-[#020617] to-[#38bdf8]'),
  coral: preset('coral', 'مرجان — زنده', 'مرجانی روشن روی سرمه‌ای.', '#fb7185', '#1e3a5f', '#fff1f2', 'navy', true, 'compact', 'bg-gradient-to-l from-[#1e3a5f] to-[#fb7185]'),
  lime: preset('lime', 'لیمو — تازه', 'سبز لیمویی و زغال.', '#65a30d', '#1a2e05', '#f7fee7', 'split', true, 'compact', 'bg-gradient-to-l from-[#1a2e05] to-[#65a30d]'),
  indigo: preset('indigo', 'نیلی — مدرن', 'نیلی و فیروزه‌ای سرد.', '#22d3ee', '#312e81', '#eef2ff', 'navy', true, 'compact', 'bg-gradient-to-l from-[#312e81] to-[#22d3ee]'),
  coffee: preset('coffee', 'قهوه — کلاسیک', 'قهوه‌ای گرم و کرم.', '#b45309', '#44403c', '#faf7f2', 'paper', false, 'strip', 'bg-gradient-to-l from-[#44403c] to-[#b45309]'),
  cherry: preset('cherry', 'گیلاس — رسمی', 'زرشکی تیره و کرم.', '#be123c', '#4c0519', '#fff7ed', 'navy', true, 'compact', 'bg-gradient-to-l from-[#4c0519] to-[#be123c]'),
  glacier: preset('glacier', 'یخچال — روشن', 'یخی و آبی کم‌رنگ.', '#0284c7', '#0c4a6e', '#f0f9ff', 'search', false, 'rows', 'bg-gradient-to-l from-[#0c4a6e] to-[#7dd3fc]'),
  sunset: preset('sunset', 'غروب — پرانرژی', 'سرخابی و نارنجی غروب.', '#f43f5e', '#7c2d12', '#fff7ed', 'dark', true, 'dark', 'bg-gradient-to-l from-[#7c2d12] to-[#f43f5e]'),
  olive: preset('olive', 'زیتون — آرام', 'زیتونی و کرم خاکی.', '#ca8a04', '#3f6212', '#f7fee7', 'split', false, 'strip', 'bg-gradient-to-l from-[#3f6212] to-[#ca8a04]'),
  graphite: preset('graphite', 'گرافیت — صنعتی', 'خاکستری فلزی و کهربایی.', '#facc15', '#18181b', '#f4f4f5', 'dark', true, 'dark', 'bg-gradient-to-l from-[#18181b] to-[#facc15]'),
  blossom: preset('blossom', 'شکوفه — لطیف', 'یاسی و صورتی شکوفه.', '#c084fc', '#6b21a8', '#fdf4ff', 'paper', false, 'strip', 'bg-gradient-to-l from-[#6b21a8] to-[#c084fc]'),
}

export function isSiteTheme(value: unknown): value is SiteThemeId {
  return typeof value === 'string' && (SITE_THEMES as readonly string[]).includes(value)
}

export function themePreset(id: unknown): SiteThemePreset {
  return isSiteTheme(id) ? SITE_THEME_PRESETS[id] : SITE_THEME_PRESETS.atlas
}

export const SITE_FONTS = [
  'estedad',
  'vazirmatn',
  'shabnam',
  'samim',
  'sahel',
] as const

export type SiteFontId = (typeof SITE_FONTS)[number]

export const SITE_FONT_PRESETS: Record<
  SiteFontId,
  { id: SiteFontId; title: string; family: string; sample: string }
> = {
  estedad: {
    id: 'estedad',
    title: 'استمداد',
    family: '"Estedad Variable", Estedad, Tahoma, sans-serif',
    sample: 'جاب‌آزمون — آمادگی استخدام',
  },
  vazirmatn: {
    id: 'vazirmatn',
    title: 'وزیرمتن',
    family: 'Vazirmatn, Tahoma, sans-serif',
    sample: 'جاب‌آزمون — آمادگی استخدام',
  },
  shabnam: {
    id: 'shabnam',
    title: 'شبنم',
    family: 'Shabnam, Tahoma, sans-serif',
    sample: 'جاب‌آزمون — آمادگی استخدام',
  },
  samim: {
    id: 'samim',
    title: 'صمیم',
    family: 'Samim, Tahoma, sans-serif',
    sample: 'جاب‌آزمون — آمادگی استخدام',
  },
  sahel: {
    id: 'sahel',
    title: 'ساحل',
    family: 'Sahel, Tahoma, sans-serif',
    sample: 'جاب‌آزمون — آمادگی استخدام',
  },
}

export function isSiteFont(value: unknown): value is SiteFontId {
  return typeof value === 'string' && (SITE_FONTS as readonly string[]).includes(value)
}

export function fontPreset(id: unknown) {
  return isSiteFont(id) ? SITE_FONT_PRESETS[id] : SITE_FONT_PRESETS.estedad
}
