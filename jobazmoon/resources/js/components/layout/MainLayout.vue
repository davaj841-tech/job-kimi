<template>
  <div class="flex min-h-dvh flex-col">
    <!-- Mobile chrome -->
    <div class="lg:hidden">
      <AppHeader v-if="!hideChrome" />
    </div>

    <!-- Desktop chrome -->
    <DesktopHeader v-if="!hideChrome" />

    <main class="min-h-[70dvh] flex-1" :class="hideChrome ? '' : 'lg:pb-0'">
      <div :class="hideChrome ? '' : 'pb-20 lg:pb-0'">
        <slot />
      </div>
    </main>

    <DesktopFooter v-if="!hideChrome" />
    <MobileFooter v-if="!hideChrome" />

    <div class="lg:hidden">
      <BottomNav v-if="!hideChrome" />
    </div>

    <OnboardingTour v-if="auth.isAuthenticated && !hideChrome" />
    <AppToast />
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import AppHeader from '../AppHeader.vue';
import AppToast from '../AppToast.vue';
import BottomNav from '../BottomNav.vue';
import DesktopFooter from './DesktopFooter.vue';
import DesktopHeader from './DesktopHeader.vue';
import MobileFooter from '../MobileFooter.vue';
import OnboardingTour from '../OnboardingTour.vue';
import { useAuthStore } from '../../stores/auth';

const route = useRoute();
const auth = useAuthStore();
const hideChrome = computed(() => Boolean(route.meta.hideNav));
</script>
