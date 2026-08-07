<template>
  <aside class="flex h-dvh w-64 shrink-0 flex-col bg-[#0f2744] text-white">
    <div class="shrink-0 border-b border-white/10 px-5 py-5">
      <p class="text-lg font-black">جاب‌آزمون</p>
      <p class="mt-1 text-xs text-white/60">پنل مدیریت</p>
    </div>

    <nav ref="navEl" class="min-h-0 flex-1 space-y-1 overflow-y-auto overscroll-contain p-3" @scroll="onScroll">
      <RouterLink
        v-for="item in items"
        :key="item.to"
        :to="item.to"
        class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition"
        :class="isActive(item.to)
          ? 'bg-orange-500 text-white'
          : 'text-white/75 hover:bg-white/10 hover:text-white'"
      >
        <span class="text-base">{{ item.icon }}</span>
        <span>{{ item.label }}</span>
      </RouterLink>
    </nav>

    <div class="shrink-0 border-t border-white/10 p-4 text-xs text-white/50">
      <a href="/api/documentation" target="_blank" rel="noopener" class="mb-2 block text-orange-300 hover:text-orange-200">مستندات API ↗</a>
      جاب‌آزمون · پنل مدیریت
    </div>
  </aside>
</template>

<script setup>
import { nextTick, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';

const SCROLL_KEY = 'admin_sidebar_scroll';
const route = useRoute();
const navEl = ref(null);

const items = [
  { to: '/admin/dashboard', label: 'داشبورد', icon: '▣' },
  { to: '/admin/users', label: 'کاربران', icon: '◎' },
  { to: '/admin/exams', label: 'آزمون‌ها', icon: '☰' },
  { to: '/admin/questions', label: 'سوالات', icon: '❓' },
  { to: '/admin/job-posts', label: 'آگهی‌ها', icon: '▢' },
  { to: '/admin/blog-posts', label: 'بلاگ', icon: '◈' },
  { to: '/admin/pdf-products', label: 'فایل‌ها', icon: '▤' },
  { to: '/admin/subscriptions', label: 'اشتراک‌ها', icon: '★' },
  { to: '/admin/transactions', label: 'تراکنش‌ها', icon: '₪' },
  { to: '/admin/coupons', label: 'کد تخفیف', icon: '%' },
  { to: '/admin/wallets', label: 'کیف پول‌ها', icon: '◈' },
  { to: '/admin/ai', label: 'هوش مصنوعی', icon: '✦' },
  { to: '/admin/tickets', label: 'تیکت‌ها', icon: '✉' },
  { to: '/admin/banners', label: 'بنرها', icon: '▣' },
  { to: '/admin/pages', label: 'صفحات', icon: '▤' },
  { to: '/admin/backups', label: 'بکاپ', icon: '💾' },
  { to: '/admin/audit-logs', label: 'حسابرسی', icon: '☰' },
  { to: '/admin/site-errors', label: 'خطاهای سایت', icon: '⚠' },
  { to: '/admin/settings', label: 'تنظیمات', icon: '⚙' },
];

function isActive(path) {
  return route.path === path || route.path.startsWith(`${path}/`);
}

function onScroll() {
  if (navEl.value) {
    sessionStorage.setItem(SCROLL_KEY, String(navEl.value.scrollTop));
  }
}

function restoreScroll() {
  const y = Number(sessionStorage.getItem(SCROLL_KEY) || 0);
  if (navEl.value) navEl.value.scrollTop = y;
}

onMounted(() => {
  nextTick(restoreScroll);
});

watch(
  () => route.fullPath,
  () => nextTick(restoreScroll)
);
</script>
