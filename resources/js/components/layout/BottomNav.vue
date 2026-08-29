<template>
  <nav
    class="fixed inset-x-0 bottom-0 z-50 border-t border-surface-line bg-surface dark:border-slate-700 dark:bg-slate-900 lg:hidden"
    style="
      padding-bottom: env(safe-area-inset-bottom);
      touch-action: manipulation;
    "
    aria-label="ناوبری اصلی"
  >
    <div class="mx-auto grid h-16 w-full max-w-full grid-cols-5 px-1">
      <RouterLink
        v-for="item in items"
        :key="item.to"
        :to="item.to"
        class="touch-target relative flex min-h-12 flex-col items-center justify-center gap-0.5 text-[11px] transition-colors duration-300 active:opacity-70"
        :class="
          isActive(item.to)
            ? 'font-bold text-brand dark:text-brand'
            : 'font-medium text-desk-muted dark:text-slate-400'
        "
        @click="flash(item.to)"
      >
        <span
          v-if="isActive(item.to) || pressed === item.to"
          class="absolute inset-x-3 top-0 h-0.5 rounded-b bg-brand"
          aria-hidden="true"
        />
        <NavIcon :name="item.icon" :size="24" />
        <span>{{ item.label }}</span>
      </RouterLink>
    </div>
  </nav>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import NavIcon from '../NavIcon.vue'

const route = useRoute()
const router = useRouter()
const pressed = ref('')

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

function flash(to) {
  pressed.value = to
  setTimeout(() => {
    if (pressed.value === to) pressed.value = ''
  }, 180)
}

onMounted(() => {
  for (const item of items) {
    try {
      const resolved = router.resolve(item.to)
      resolved.matched.forEach((r) => {
        const comp = r.components?.default
        if (typeof comp === 'function') {
          try {
            comp()
          } catch {
            /* ignore */
          }
        }
      })
    } catch {
      /* ignore */
    }
  }

  import('../../api/client')
    .then(({ default: api }) =>
      api.get('/job-posts', { params: { per_page: 30 } }).catch(() => null)
    )
    .catch(() => null)
})
</script>
