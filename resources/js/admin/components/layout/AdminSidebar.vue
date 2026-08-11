<template>
  <aside
    class="flex h-dvh w-64 shrink-0 flex-col bg-desk-dark text-white transition-transform duration-300 lg:translate-x-0"
    :class="open ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'"
  >
    <div class="flex shrink-0 items-center justify-between border-b border-white/10 px-5 py-5">
      <div>
        <p class="text-lg font-black">جاب‌آزمون</p>
        <p class="mt-1 text-xs text-white/60">
          {{ auth.isAdmin ? 'پنل مدیر' : 'پنل اپراتور' }}
        </p>
      </div>
      <button
        type="button"
        class="rounded-lg p-1.5 hover:bg-white/10 lg:hidden"
        aria-label="بستن منو"
        @click="$emit('close')"
      >
        ✕
      </button>
    </div>

    <nav
      ref="navEl"
      class="min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain p-3"
      @scroll="onScroll"
    >
      <div v-for="group in visibleGroups" :key="group.title">
        <p class="mb-1 px-3 text-[10px] font-bold uppercase tracking-wider text-white/35">
          {{ group.title }}
        </p>
        <div class="space-y-0.5">
          <RouterLink
            v-for="item in group.items"
            :key="item.to"
            :to="item.to"
            class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition"
            :class="
              isActive(item.to)
                ? 'bg-brand text-white'
                : 'text-white/75 hover:bg-white/10 hover:text-white'
            "
            @click="$emit('close')"
          >
            <span class="text-base">{{ item.icon }}</span>
            <span>{{ item.label }}</span>
          </RouterLink>
        </div>
      </div>
    </nav>

    <div class="shrink-0 space-y-2 border-t border-white/10 p-4 text-xs text-white/50">
      <a
        href="/filament"
        class="block text-desk-orange hover:text-white"
        >Filament ↗</a
      >
      <a
        href="/api/documentation"
        target="_blank"
        rel="noopener"
        class="block text-desk-orange hover:text-white"
        >مستندات API ↗</a
      >
    </div>
  </aside>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAdminAuthStore } from '../../stores/auth'

defineProps({ open: { type: Boolean, default: false } })
defineEmits(['close'])

const SCROLL_KEY = 'admin_sidebar_scroll'
const route = useRoute()
const auth = useAdminAuthStore()
const navEl = ref(null)

const groups = [
  {
    title: 'اصلی',
    items: [
      { to: '/admin/dashboard', label: 'داشبورد', icon: '▣' },
      { to: '/admin/users', label: 'کاربران', icon: '◎' },
      { to: '/admin/tickets', label: 'تیکت‌ها', icon: '✉' },
    ],
  },
  {
    title: 'محتوا',
    items: [
      { to: '/admin/exams', label: 'آزمون‌ها', icon: '☰' },
      { to: '/admin/questions', label: 'سوالات', icon: '❓' },
      { to: '/admin/blog-posts', label: 'بلاگ', icon: '◈' },
      { to: '/admin/generated-contents', label: 'تولید محتوا', icon: '✎' },
      { to: '/admin/pdf-products', label: 'فایل‌ها', icon: '▤' },
      { to: '/admin/banners', label: 'بنرها', icon: '▣' },
      { to: '/admin/pages', label: 'صفحات', icon: '▤' },
      { to: '/admin/ai', label: 'هوش مصنوعی', icon: '✦' },
    ],
  },
  {
    title: 'آگهی و تجمیع',
    items: [
      { to: '/admin/job-posts', label: 'آگهی‌ها', icon: '▢' },
      { to: '/admin/job-sources', label: 'منابع تجمیع', icon: '◎' },
      { to: '/admin/aggregation-settings', label: 'زمان‌بندی تجمیع', icon: '◷' },
      { to: '/admin/crawl-monitoring', label: 'پایش خزش', icon: '◷' },
      { to: '/admin/aggregated-jobs', label: 'بررسی تجمیع', icon: '☑' },
    ],
  },
  {
    title: 'مالی',
    items: [
      { to: '/admin/subscriptions', label: 'اشتراک‌ها', icon: '★' },
      { to: '/admin/transactions', label: 'تراکنش‌ها', icon: '₪' },
      { to: '/admin/coupons', label: 'کد تخفیف', icon: '%' },
      { to: '/admin/wallets', label: 'کیف پول‌ها', icon: '◈' },
    ],
  },
  {
    title: 'سیستم',
    adminOnly: true,
    items: [
      { to: '/admin/backups', label: 'بکاپ', icon: '💾' },
      { to: '/admin/audit-logs', label: 'حسابرسی', icon: '☰' },
      { to: '/admin/site-errors', label: 'خطاهای سایت', icon: '⚠' },
      { to: '/admin/settings', label: 'تنظیمات', icon: '⚙' },
    ],
  },
]

const visibleGroups = computed(() =>
  groups.filter((g) => !g.adminOnly || auth.isAdmin)
)

function isActive(path) {
  return route.path === path || route.path.startsWith(`${path}/`)
}

function onScroll() {
  if (navEl.value) {
    sessionStorage.setItem(SCROLL_KEY, String(navEl.value.scrollTop))
  }
}

function restoreScroll() {
  const y = Number(sessionStorage.getItem(SCROLL_KEY) || 0)
  if (navEl.value) navEl.value.scrollTop = y
}

onMounted(() => nextTick(restoreScroll))
watch(
  () => route.fullPath,
  () => nextTick(restoreScroll)
)
</script>
