<template>
  <header class="sticky top-0 z-40 hidden border-b border-white/10 bg-desk-dark lg:block">
    <div class="desk-container flex h-16 items-center justify-between gap-6">
      <div class="flex items-center gap-8">
        <RouterLink to="/" class="text-xl font-black tracking-tight text-white">
          جاب‌آزمون
        </RouterLink>
        <nav class="flex items-center gap-1">
          <RouterLink
            v-for="item in navItems"
            :key="item.to"
            :to="item.to"
            class="rounded-lg px-3 py-2 text-sm font-medium transition"
            :class="isActive(item.to) ? 'bg-white/10 text-desk-orange' : 'text-white/80 hover:bg-white/5 hover:text-white'"
          >
            {{ item.label }}
          </RouterLink>
        </nav>
      </div>

      <div class="mx-4 hidden min-w-0 flex-1 xl:block">
        <GlobalSearch dark />
      </div>

      <div class="flex items-center gap-3">
        <NotificationBell v-if="auth.isAuthenticated" dark />
        <RouterLink
          v-if="auth.isAuthenticated"
          to="/dashboard"
          class="rounded-lg px-3 py-2 text-sm font-medium text-white/85 hover:bg-white/5"
        >
          داشبورد
        </RouterLink>
        <RouterLink
          v-if="auth.isAuthenticated"
          to="/wallet"
          class="rounded-lg bg-white/10 px-3 py-2 text-sm font-bold text-white"
        >
          کیف پول
        </RouterLink>
        <RouterLink
          v-else
          to="/login"
          class="rounded-lg bg-desk-orange px-4 py-2 text-sm font-bold text-white transition hover:bg-orange-500"
        >
          ورود / ثبت‌نام
        </RouterLink>
      </div>
    </div>
  </header>
</template>

<script setup>
import { useRoute } from 'vue-router';
import GlobalSearch from '../GlobalSearch.vue';
import NotificationBell from '../NotificationBell.vue';
import { useAuthStore } from '../../stores/auth';

const route = useRoute();
const auth = useAuthStore();

const navItems = [
  { to: '/', label: 'صفحه اصلی' },
  { to: '/jobs', label: 'استخدام‌ها' },
  { to: '/exams', label: 'آزمون‌ها' },
  { to: '/pdfs', label: 'فروشگاه فایل' },
  { to: '/resumes', label: 'رزومه‌ساز' },
  { to: '/blog', label: 'وبلاگ' },
];

function isActive(path) {
  if (path === '/') return route.path === '/';
  return route.path.startsWith(path);
}
</script>
