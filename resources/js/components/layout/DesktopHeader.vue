<template>
  <header
    class="sticky top-0 z-40 hidden border-b lg:block"
    :class="navy ? 'border-white/10' : 'border-surface-line bg-surface'"
    :style="
      navy
        ? {
            backgroundColor:
              'color-mix(in srgb, var(--theme-ink) 96%, transparent)',
          }
        : undefined
    "
  >
    <div
      class="desk-container flex h-[4.5rem] items-center justify-between gap-6"
    >
      <div class="flex min-w-0 items-center gap-8">
        <RouterLink to="/" class="shrink-0" aria-label="صفحه اصلی">
          <SiteBrandLogo
            variant="desktop"
            size="lg"
            :text-class="
              navy
                ? 'text-xl text-white'
                : 'text-xl text-desk-text dark:text-white'
            "
          />
        </RouterLink>
        <nav class="flex items-center gap-0.5">
          <RouterLink
            v-for="item in navItems"
            :key="item.to"
            :to="item.to"
            class="rounded-xl px-3 py-2 text-sm font-medium transition"
            :class="navClass(item.to)"
          >
            {{ item.label }}
          </RouterLink>
        </nav>
      </div>

      <div class="mx-2 hidden min-w-0 flex-1 xl:block">
        <GlobalSearch />
      </div>

      <div class="flex shrink-0 items-center gap-2">
        <ThemeToggle :inverted="navy" />
        <NotificationBell v-if="auth.isAuthenticated" />
        <RouterLink
          v-if="auth.isAuthenticated"
          to="/dashboard"
          class="rounded-xl px-3 py-2 text-sm font-medium"
          :class="
            navy
              ? 'text-white/75 hover:bg-white/10 hover:text-white'
              : 'text-desk-muted hover:bg-slate-50 hover:text-desk-dark'
          "
        >
          داشبورد
        </RouterLink>
        <RouterLink
          v-if="auth.isAuthenticated"
          to="/wallet"
          class="rounded-xl px-3 py-2 text-sm font-bold"
          :class="
            navy
              ? 'border border-white/25 text-white hover:bg-white/10'
              : 'border border-surface-line text-desk-dark hover:bg-slate-50'
          "
        >
          کیف پول
        </RouterLink>
        <RouterLink
          v-else
          to="/login"
          class="rounded-xl bg-brand px-4 py-2.5 text-sm font-bold text-white shadow-sm shadow-brand/20 transition hover:bg-brand-dark"
        >
          ورود / ثبت‌نام
        </RouterLink>
      </div>
    </div>
  </header>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import GlobalSearch from '../GlobalSearch.vue'
import NotificationBell from '../NotificationBell.vue'
import SiteBrandLogo from '../SiteBrandLogo.vue'
import ThemeToggle from '../ThemeToggle.vue'
import { useAuthStore } from '../../stores/auth'
import { useHomepageLayout } from '../../composables/useHomepageLayout'

const route = useRoute()
const auth = useAuthStore()
const { ensureLoaded, isDarkHero } = useHomepageLayout()
const navy = computed(() => isDarkHero.value)

onMounted(() => {
  ensureLoaded()
})

const navItems = [
  { to: '/', label: 'صفحه اصلی' },
  { to: '/jobs', label: 'استخدام‌ها' },
  { to: '/exams', label: 'آزمون‌ها' },
  { to: '/pdfs', label: 'فروشگاه' },
  { to: '/resumes', label: 'رزومه‌ساز' },
  { to: '/articles', label: 'مقالات' },
  { to: '/blog', label: 'وبلاگ' },
]

function isActive(path) {
  if (path === '/') return route.path === '/'
  return route.path.startsWith(path)
}

function navClass(path) {
  if (navy.value) {
    return isActive(path)
      ? 'bg-white/15 text-white'
      : 'text-white/70 hover:bg-white/10 hover:text-white'
  }
  return isActive(path)
    ? 'bg-brand-soft text-brand'
    : 'text-desk-muted hover:bg-surface-page hover:text-desk-text'
}
</script>
