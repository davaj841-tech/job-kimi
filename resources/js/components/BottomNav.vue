<template>
  <nav
    class="fixed inset-x-0 bottom-0 z-40 border-t border-surface-line bg-white/95 backdrop-blur-md"
    style="padding-bottom: env(safe-area-inset-bottom)"
    aria-label="ناوبری اصلی"
  >
    <div class="mx-auto grid h-14 max-w-app grid-cols-5 px-1">
      <RouterLink
        v-for="item in items"
        :key="item.to"
        :to="item.to"
        class="relative flex flex-col items-center justify-center gap-0.5 text-[10px] transition"
        :class="
          isActive(item.to)
            ? 'font-bold text-desk-dark'
            : 'font-medium text-slate-400'
        "
      >
        <span
          v-if="isActive(item.to)"
          class="absolute inset-x-3 top-0 h-0.5 rounded-b bg-brand"
          aria-hidden="true"
        />
        <NavIcon
          :name="item.icon"
          :size="20"
        />
        <span>{{ item.label }}</span>
      </RouterLink>
    </div>
  </nav>
</template>

<script setup>
import { useRoute } from 'vue-router'
import NavIcon from './NavIcon.vue'

const route = useRoute()

const items = [
  { to: '/', label: 'خانه', icon: 'home' },
  { to: '/exams', label: 'آزمون‌ها', icon: 'exams' },
  { to: '/jobs', label: 'آگهی‌ها', icon: 'jobs' },
  { to: '/pdfs', label: 'فروشگاه', icon: 'pdf' },
  { to: '/profile', label: 'پروفایل', icon: 'profile' },
]

function isActive(path) {
  if (path === '/') return route.path === '/'
  return route.path.startsWith(path)
}
</script>
