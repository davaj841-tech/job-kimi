<template>
  <nav class="fixed inset-x-0 bottom-0 z-40 bg-white shadow-[0_-2px_10px_rgba(0,0,0,0.05)]">
    <div class="mx-auto grid h-16 max-w-app grid-cols-4 px-1 pb-[env(safe-area-inset-bottom)]">
      <RouterLink
        v-for="item in items"
        :key="item.to"
        :to="item.to"
        class="relative flex flex-col items-center justify-center gap-0.5 text-[11px] transition"
        :class="isActive(item.to) ? 'font-bold text-desk-orange' : 'text-gray-400'"
      >
        <span
          v-if="isActive(item.to)"
          class="absolute inset-x-4 top-0 h-0.5 rounded-b bg-desk-orange"
          aria-hidden="true"
        />
        <NavIcon :name="item.icon" :size="22" />
        <span>{{ item.label }}</span>
      </RouterLink>
    </div>
  </nav>
</template>

<script setup>
import { useRoute } from 'vue-router';
import NavIcon from './NavIcon.vue';

const route = useRoute();

const items = [
  { to: '/', label: 'خانه', icon: 'home' },
  { to: '/exams', label: 'آزمون‌ها', icon: 'exams' },
  { to: '/jobs', label: 'آگهی‌ها', icon: 'jobs' },
  { to: '/profile', label: 'پروفایل', icon: 'profile' },
];

function isActive(path) {
  if (path === '/') return route.path === '/';
  return route.path.startsWith(path);
}
</script>
