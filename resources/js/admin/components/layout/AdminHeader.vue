<template>
  <header
    class="flex h-16 items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 sm:px-6"
  >
    <div class="flex min-w-0 items-center gap-3">
      <button
        type="button"
        class="rounded-lg bg-slate-100 p-2 text-slate-700 hover:bg-slate-200 lg:hidden"
        aria-label="منو"
        @click="$emit('toggle-sidebar')"
      >
        ☰
      </button>
      <div class="min-w-0">
        <p class="truncate text-sm font-bold text-slate-800">{{ pageTitle }}</p>
        <p class="text-xs text-slate-500">مدیریت جاب‌آزمون</p>
      </div>
    </div>

    <div class="flex items-center gap-2 sm:gap-4">
      <div class="hidden items-center gap-2 md:flex">
        <RouterLink
          v-for="q in visibleQuickLinks"
          :key="q.to"
          :to="q.to"
          class="rounded-lg bg-slate-50 px-2.5 py-1.5 text-xs font-bold text-desk-dark hover:bg-orange-50 hover:text-orange-600"
        >
          {{ q.label }}
        </RouterLink>
      </div>
      <div class="hidden text-left sm:block" dir="rtl">
        <p class="text-xs font-bold text-desk-dark">{{ clockText }}</p>
        <p class="text-[11px] text-slate-400">شمسی</p>
      </div>
      <div class="text-left text-xs" dir="ltr">
        <p class="font-semibold text-slate-700">
          {{ auth.user?.name || 'ادمین' }}
        </p>
        <p class="text-slate-400">{{ auth.user?.role || '' }}</p>
      </div>
      <a
        href="/"
        class="rounded-lg bg-desk-dark px-3 py-2 text-xs font-bold text-white hover:bg-desk-blue"
      >
        صفحه اول
      </a>
      <button
        type="button"
        class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200"
        @click="onLogout"
      >
        خروج
      </button>
    </div>
  </header>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAdminAuthStore } from '../../stores/auth'
import { formatJalaliDateTime } from '../../../utils/jalali'

defineEmits(['toggle-sidebar'])

const auth = useAdminAuthStore()
const router = useRouter()
const route = useRoute()
const pageTitle = computed(() => route.meta.title || 'پنل مدیریت')
const now = ref(new Date())
let timer

const clockText = computed(() => formatJalaliDateTime(now.value))

const quickLinks = [
  { to: '/admin/exams', label: '+ آزمون', permission: 'exams' },
  { to: '/admin/users', label: 'کاربران', permission: 'users' },
  { to: '/admin/transactions', label: 'مالی', permission: 'transactions' },
  { to: '/admin/tickets', label: 'تیکت', permission: 'tickets' },
]
const visibleQuickLinks = computed(() =>
  quickLinks.filter((item) => auth.can(item.permission))
)

onMounted(() => {
  timer = setInterval(() => {
    now.value = new Date()
  }, 1000)
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
})

async function onLogout() {
  await auth.logout()
  router.replace('/admin/login')
}
</script>
