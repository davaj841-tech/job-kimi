<template>
  <section
    class="hero-bleed relative isolate overflow-hidden"
    :class="sectionClass"
    :style="heroInkStyle"
  >
    <!-- navy / ocean / royal -->
    <template v-if="hero === 'navy'">
      <div
        class="absolute inset-0"
        aria-hidden="true"
        :style="navyBg"
      />
      <div class="relative z-10 mx-auto grid max-w-7xl items-center gap-6 px-4 py-8 sm:px-6 sm:py-10 lg:grid-cols-2 lg:gap-10 lg:py-11">
        <div class="space-y-3 text-right">
          <p class="text-[11px] font-bold text-desk-orange sm:text-xs">جاب‌آزمون</p>
          <h1 class="text-2xl font-black leading-snug text-white sm:text-3xl lg:text-[2.15rem]">
            آمادگی امروز،
            <span class="text-amber-200">استخدام فردا</span>
          </h1>
          <p class="max-w-md text-xs leading-6 text-white/70 sm:text-sm sm:leading-7">
            آزمون شبیه‌سازی، آگهی استخدام، منابع PDF و رزومه‌ساز — در یک مسیر ساده.
          </p>
          <div class="flex flex-col gap-2 pt-1 sm:flex-row sm:items-center">
            <RouterLink
              to="/exams"
              class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-desk-orange px-5 py-2.5 text-sm font-bold text-white transition hover:opacity-90"
            >
              شروع آزمون
              <ArrowLeftIcon class="h-4 w-4" />
            </RouterLink>
            <RouterLink
              to="/jobs"
              class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-white/25 bg-white/5 px-5 py-2.5 text-sm font-bold text-white backdrop-blur-sm transition hover:bg-white/10"
            >
              مشاهده استخدام‌ها
            </RouterLink>
          </div>
        </div>
        <div class="hidden justify-center lg:flex">
          <div class="relative w-full max-w-sm">
            <BannerSlider
              v-if="heroBanners.length"
              position="home_hero"
              :banners="heroBanners"
              class="shadow-2xl ring-1 ring-white/10"
            />
            <div
              v-else
              class="rounded-2xl bg-black/30 p-2 shadow-2xl ring-1 ring-white/10"
            >
              <div class="rounded-xl bg-white p-3">
                <div class="mb-2 flex items-center justify-between">
                  <span class="h-2 w-16 rounded bg-desk-dark/15" />
                  <span class="flex gap-1">
                    <span class="h-1.5 w-1.5 rounded-full bg-red-400" />
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-400" />
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400" />
                  </span>
                </div>
                <div class="grid grid-cols-3 gap-2">
                  <div
                    v-for="n in 6"
                    :key="n"
                    class="h-10 rounded-lg"
                    :class="n === 2 ? 'bg-desk-orange/80' : 'bg-desk-dark/10'"
                  />
                </div>
              </div>
            </div>
            <div
              v-if="!heroBanners.length"
              class="absolute -bottom-3 -right-3 h-10 w-10 rounded-full bg-desk-orange/90 shadow-lg"
            />
          </div>
        </div>
        <div
          v-if="heroBanners.length"
          class="mt-4 lg:hidden"
        >
          <BannerSlider
            position="home_hero"
            :banners="heroBanners"
            class="overflow-hidden rounded-2xl ring-1 ring-white/15"
          />
        </div>
      </div>
    </template>

    <!-- paper / editorial / rose -->
    <template v-else-if="hero === 'paper'">
      <div class="mx-auto max-w-7xl px-4 py-7 sm:px-6 sm:py-9">
        <div class="grid items-end gap-6 lg:grid-cols-[1.2fr_0.8fr]">
          <div class="text-right">
            <p class="mb-2 text-[11px] font-bold tracking-wide text-desk-orange">
              مسیر استخدام
            </p>
            <h1 class="max-w-xl text-2xl font-black leading-snug text-desk-dark sm:text-4xl">
              آمادگی هدفمند برای آزمون‌های استخدامی
            </h1>
            <p class="mt-2 max-w-lg text-xs leading-6 text-desk-muted sm:text-sm sm:leading-7">
              آزمون، آگهی، جزوه و رزومه — بدون شلوغی، با تمرکز روی نتیجه.
            </p>
            <form
              class="mt-4 flex max-w-xl gap-2"
              @submit.prevent="goSearch"
            >
              <input
                v-model="q"
                type="search"
                placeholder="جستجوی آزمون یا استخدام..."
                class="h-11 flex-1 rounded-xl border border-surface-line bg-white px-3 text-sm outline-none focus:border-desk-orange"
              />
              <button
                type="submit"
                class="h-11 shrink-0 rounded-xl bg-desk-dark px-4 text-sm font-bold text-white hover:bg-desk-blue"
              >
                جستجو
              </button>
            </form>
          </div>
          <div class="flex flex-wrap gap-2 lg:justify-end">
            <RouterLink
              to="/exams"
              class="rounded-xl bg-desk-orange px-4 py-2.5 text-sm font-bold text-white"
            >
              شروع آزمون
            </RouterLink>
            <RouterLink
              to="/jobs"
              class="rounded-xl border border-desk-dark/15 bg-white px-4 py-2.5 text-sm font-bold text-desk-dark"
            >
              استخدام‌ها
            </RouterLink>
          </div>
        </div>
      </div>
    </template>

    <!-- dark / studio / midnight -->
    <template v-else-if="hero === 'dark'">
      <div
        class="absolute inset-0"
        aria-hidden="true"
        :style="darkBg"
      />
      <div class="relative z-10 mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-10">
        <div class="max-w-2xl text-right">
          <p class="mb-2 text-[11px] font-bold text-white/50">استودیو آمادگی</p>
          <h1 class="text-2xl font-black leading-snug text-white sm:text-3xl">
            تمرین واقعی، تصمیم سریع‌تر
          </h1>
          <p class="mt-2 max-w-md text-xs leading-6 text-white/65 sm:text-sm">
            محیط آزمون شبیه روز برگزاری، آگهی‌های تازه و رزومه آماده ارسال.
          </p>
          <div class="mt-4 flex flex-wrap gap-2">
            <RouterLink
              to="/exams"
              class="rounded-full bg-white px-5 py-2 text-sm font-bold text-desk-dark"
            >
              ورود به آزمون
            </RouterLink>
            <RouterLink
              to="/resumes"
              class="rounded-full border border-white/25 px-5 py-2 text-sm font-bold text-white"
            >
              ساخت رزومه
            </RouterLink>
            <RouterLink
              to="/jobs"
              class="rounded-full border border-white/25 px-5 py-2 text-sm font-bold text-white"
            >
              آگهی‌ها
            </RouterLink>
          </div>
        </div>
      </div>
    </template>

    <!-- split / emerald / sand -->
    <template v-else-if="hero === 'split'">
      <div class="mx-auto grid max-w-7xl gap-0 lg:grid-cols-2">
        <div
          class="px-4 py-8 text-white sm:px-6 sm:py-10"
          :style="{ background: 'var(--theme-ink)' }"
        >
          <p class="mb-2 text-[11px] font-bold text-desk-orange">جاب‌آزمون</p>
          <h1 class="text-2xl font-black leading-snug sm:text-3xl">
            مسیر روشن استخدام
          </h1>
          <p class="mt-2 max-w-md text-xs leading-6 text-white/70 sm:text-sm">
            آزمون، فایل آموزشی و رزومه حرفه‌ای در یک نگاه.
          </p>
          <RouterLink
            to="/exams"
            class="mt-4 inline-flex rounded-xl bg-desk-orange px-5 py-2.5 text-sm font-bold text-white"
          >
            شروع آزمون
          </RouterLink>
        </div>
        <div
          class="flex flex-col justify-center px-4 py-8 sm:px-6"
          :style="{ background: 'var(--theme-page)' }"
        >
          <form
            class="flex gap-2"
            @submit.prevent="goSearch"
          >
            <input
              v-model="q"
              type="search"
              placeholder="جستجو..."
              class="h-11 flex-1 rounded-xl border border-surface-line bg-white px-3 text-sm outline-none"
            />
            <button
              type="submit"
              class="h-11 rounded-xl bg-desk-dark px-4 text-sm font-bold text-white"
            >
              برو
            </button>
          </form>
          <div class="mt-3 flex flex-wrap gap-2 text-xs font-bold">
            <RouterLink
              to="/jobs"
              class="rounded-full bg-white px-3 py-1.5 text-desk-dark ring-1 ring-surface-line"
            >استخدام‌ها</RouterLink>
            <RouterLink
              to="/pdfs"
              class="rounded-full bg-white px-3 py-1.5 text-desk-dark ring-1 ring-surface-line"
            >فروشگاه</RouterLink>
          </div>
        </div>
      </div>
    </template>

    <!-- search / minimal -->
    <template v-else>
      <div class="mx-auto max-w-3xl px-4 py-6 text-right sm:py-8">
        <h1 class="text-xl font-black text-desk-dark sm:text-2xl">جاب‌آزمون</h1>
        <p class="mt-1 text-sm text-desk-muted">آزمون، استخدام، فایل و رزومه — بدون حاشیه</p>
        <form
          class="mt-4 flex gap-2"
          @submit.prevent="goSearch"
        >
          <input
            v-model="q"
            type="search"
            placeholder="چی می‌خوای پیدا کنی؟"
            class="h-11 flex-1 rounded-xl border border-surface-line bg-white px-3 text-sm outline-none focus:border-brand"
          />
          <button
            type="submit"
            class="h-11 rounded-xl bg-brand px-4 text-sm font-bold text-white"
          >
            برو
          </button>
        </form>
        <div class="mt-3 flex flex-wrap gap-2 text-xs font-bold">
          <RouterLink
            to="/exams"
            class="rounded-full bg-desk-dark px-3 py-1.5 text-white"
          >آزمون‌ها</RouterLink>
          <RouterLink
            to="/jobs"
            class="rounded-full bg-white px-3 py-1.5 text-desk-dark ring-1 ring-surface-line"
          >استخدام‌ها</RouterLink>
          <RouterLink
            to="/pdfs"
            class="rounded-full bg-white px-3 py-1.5 text-desk-dark ring-1 ring-surface-line"
          >فروشگاه</RouterLink>
        </div>
      </div>
    </template>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowLeftIcon } from '@heroicons/vue/24/outline'
import BannerSlider from '../BannerSlider.vue'
import api from '../../api/client'
import { themePreset, type SiteThemeId } from '../../theme/presets'
import { unwrapList } from '../../utils/format'

const props = withDefaults(
  defineProps<{ variant?: SiteThemeId }>(),
  { variant: 'atlas' },
)

const router = useRouter()
const q = ref('')
const heroBanners = ref<any[]>([])
const preset = computed(() => themePreset(props.variant))
const hero = computed(() => preset.value.hero)

onMounted(async () => {
  try {
    const { data } = await api.get('/banners', { params: { position: 'home_hero' } })
    heroBanners.value = unwrapList(data) || data?.data || []
  } catch {
    heroBanners.value = []
  }
})

const sectionClass = computed(() => {
  if (hero.value === 'navy' || hero.value === 'dark') return ''
  if (hero.value === 'paper') return 'border-b border-surface-line bg-surface-page'
  if (hero.value === 'split') return 'bg-surface-page'
  return 'border-b border-surface-line bg-surface-page'
})

const heroInkStyle = computed(() =>
  hero.value === 'navy' || hero.value === 'dark'
    ? { backgroundColor: 'var(--theme-ink)' }
    : undefined
)

const navyBg = {
  background:
    'radial-gradient(ellipse 70% 80% at 8% 0%, color-mix(in srgb, var(--theme-accent) 28%, transparent), transparent 50%), linear-gradient(165deg, var(--theme-ink) 0%, var(--theme-ink-2) 55%, var(--theme-ink) 100%)',
}

const darkBg = {
  background:
    'radial-gradient(circle at 85% 20%, color-mix(in srgb, var(--theme-accent) 38%, transparent), transparent 38%), radial-gradient(circle at 10% 80%, color-mix(in srgb, var(--theme-accent) 18%, transparent), transparent 36%), var(--theme-ink)',
}

function goSearch() {
  const term = q.value.trim()
  router.push(term ? { path: '/exams', query: { search: term } } : '/exams')
}
</script>
