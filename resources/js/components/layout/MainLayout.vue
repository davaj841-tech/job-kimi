<template>
  <UserLayout v-if="isUserPanel">
    <slot />
  </UserLayout>
  <div v-else class="flex min-h-dvh flex-col bg-surface-page dark:bg-slate-900">
    <div class="lg:hidden">
      <MobileHeader v-if="!hideChrome" />
    </div>

    <DesktopHeader v-if="!hideChrome" />

    <main class="flex-1 pb-[calc(3.5rem+env(safe-area-inset-bottom))] lg:pb-0">
      <slot />
    </main>

    <DesktopFooter v-if="!hideChrome" />
    <MobileFooter v-if="!hideChrome" />
    <InstallPwaBanner v-if="!hideChrome" />

    <div class="lg:hidden">
      <BottomNav />
    </div>

    <OnboardingTour v-if="auth.isAuthenticated && !hideChrome" />
    <AppToast />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import MobileHeader from './MobileHeader.vue'
import AppToast from '../AppToast.vue'
import BottomNav from './BottomNav.vue'
import DesktopFooter from './DesktopFooter.vue'
import DesktopHeader from './DesktopHeader.vue'
import InstallPwaBanner from '../InstallPwaBanner.vue'
import MobileFooter from '../MobileFooter.vue'
import OnboardingTour from '../OnboardingTour.vue'
import UserLayout from '../../layouts/UserLayout.vue'
import { useAuthStore } from '../../stores/auth'
import { useDarkMode } from '../../composables/useDarkMode'

// Ensure theme class is applied globally
useDarkMode()

const route = useRoute()
const auth = useAuthStore()
const hideChrome = computed(() => Boolean(route.meta.hideNav))
const isUserPanel = computed(() => Boolean(route.meta.userPanel))
</script>
