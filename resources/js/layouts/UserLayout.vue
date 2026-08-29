<template>
  <div class="min-h-dvh bg-surface-page dark:bg-slate-900">
    <!-- Mobile overlay -->
    <Transition
      enter-active-class="transition-opacity duration-200"
      leave-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
    >
      <div
        v-if="sidebarOpen"
        class="fixed inset-0 z-40 bg-black/40 lg:hidden"
        @click="sidebarOpen = false"
      />
    </Transition>

    <!-- Sidebar -->
    <aside
      class="fixed inset-y-0 right-0 z-50 flex w-64 flex-col border-l border-surface-line bg-surface transition-transform duration-300 dark:border-slate-700 dark:bg-slate-800"
      :class="
        sidebarOpen ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'
      "
    >
      <div
        class="flex h-16 items-center gap-2 border-b border-surface-line px-5 dark:border-slate-700"
      >
        <SiteBrandLogo variant="desktop" size="sm" />
        <div class="min-w-0">
          <p class="truncate text-sm font-black text-ink dark:text-white">
            {{ siteName }}
          </p>
          <p class="text-[10px] text-ink-muted dark:text-slate-400">
            پنل کاربری
          </p>
        </div>
      </div>

      <nav class="flex-1 space-y-1 overflow-y-auto p-3">
        <NavItem
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          :icon="item.icon"
          :label="item.label"
          @click="sidebarOpen = false"
        />
      </nav>

      <div class="border-t border-surface-line p-4 dark:border-slate-700">
        <div class="flex items-center gap-3">
          <div
            class="flex h-10 w-10 items-center justify-center rounded-full bg-desk-dark text-sm font-bold text-white"
          >
            {{ initials }}
          </div>
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium text-ink dark:text-white">
              {{ user?.name || 'کاربر' }}
            </p>
            <p
              class="truncate text-xs text-ink-muted dark:text-slate-400"
              dir="ltr"
            >
              {{ user?.mobile || '' }}
            </p>
          </div>
        </div>
      </div>
    </aside>

    <!-- Main -->
    <div class="lg:mr-64">
      <header
        class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-surface-line bg-surface/95 px-4 backdrop-blur dark:border-slate-700 dark:bg-slate-800/95 sm:px-6"
      >
        <div class="flex items-center gap-3">
          <button
            type="button"
            class="rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-slate-700 lg:hidden"
            :aria-label="sidebarOpen ? 'بستن منو' : 'باز کردن منو'"
            @click="sidebarOpen = !sidebarOpen"
          >
            <XMarkIcon
              v-if="sidebarOpen"
              class="h-6 w-6 text-ink dark:text-white"
            />
            <Bars3Icon v-else class="h-6 w-6 text-ink dark:text-white" />
          </button>
          <h1 class="text-sm font-bold text-ink dark:text-white sm:text-base">
            {{ pageTitle }}
          </h1>
        </div>

        <div class="flex items-center gap-1 sm:gap-2">
          <button
            type="button"
            class="rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-slate-700"
            aria-label="حالت تاریک"
            @click="toggleDark"
          >
            <SunIcon v-if="isDark" class="h-5 w-5 text-amber-400" />
            <MoonIcon v-else class="h-5 w-5 text-ink-muted" />
          </button>

          <RouterLink
            to="/notifications"
            class="relative rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-slate-700"
          >
            <BellIcon class="h-5 w-5 text-ink dark:text-white" />
            <span
              v-if="unreadCount > 0"
              class="absolute left-1.5 top-1.5 h-2 w-2 rounded-full bg-brand"
            />
          </RouterLink>

          <RouterLink
            to="/"
            class="rounded-lg px-3 py-1.5 text-xs font-medium text-ink-muted hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-700"
          >
            صفحه اول
          </RouterLink>
        </div>
      </header>

      <div class="animate-panel-in p-4 pb-24 sm:p-6 lg:pb-6">
        <slot />
      </div>
    </div>

    <div class="lg:hidden">
      <BottomNav />
    </div>

    <AppToast />
    <PageScrollFab />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import {
  AcademicCapIcon,
  Bars3Icon,
  BellIcon,
  ChatBubbleLeftRightIcon,
  DocumentTextIcon,
  HomeIcon,
  MoonIcon,
  ShoppingBagIcon,
  StarIcon,
  SunIcon,
  UserCircleIcon,
  WalletIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'
import AppToast from '../components/AppToast.vue'
import BottomNav from '../components/layout/BottomNav.vue'
import SiteBrandLogo from '../components/SiteBrandLogo.vue'
import NavItem from '../components/user/NavItem.vue'
import PageScrollFab from '../components/ui/PageScrollFab.vue'
import { useDarkMode } from '../composables/useDarkMode'
import { useSiteTheme } from '../composables/useSiteTheme'
import { useAuthStore } from '../stores/auth'
import { useNotificationsStore } from '../stores/notifications'

const route = useRoute()
const auth = useAuthStore()
const notifications = useNotificationsStore()
const { isDark, toggle: toggleDark } = useDarkMode()
const { siteName } = useSiteTheme()
const sidebarOpen = ref(false)

const user = computed(() => auth.user)
const unreadCount = computed(() => notifications.unreadCount || 0)
const pageTitle = computed(() => (route.meta.title as string) || 'پنل کاربری')

const initials = computed(() => {
  const name = user.value?.name || 'کاربر'
  return name.trim().slice(0, 1)
})

const navItems = [
  { to: '/dashboard', label: 'داشبورد', icon: HomeIcon },
  { to: '/user/exams', label: 'تاریخچه آزمون', icon: AcademicCapIcon },
  { to: '/wallet', label: 'کیف پول', icon: WalletIcon },
  { to: '/subscription', label: 'اشتراک', icon: StarIcon },
  { to: '/my-purchases', label: 'خریدها', icon: ShoppingBagIcon },
  { to: '/resumes', label: 'رزومه', icon: DocumentTextIcon },
  { to: '/support', label: 'پشتیبانی', icon: ChatBubbleLeftRightIcon },
  { to: '/profile', label: 'پروفایل', icon: UserCircleIcon },
]

onMounted(() => {
  void notifications.fetchUnreadCount()
})
</script>
