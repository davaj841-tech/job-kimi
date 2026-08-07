<template>
  <header class="sticky top-0 z-30 bg-gradient-to-l from-desk-dark to-desk-blue shadow-[0_2px_8px_rgba(15,39,68,0.25)]">
    <div class="mx-auto flex h-14 max-w-app items-center gap-2 px-3">
      <RouterLink to="/" class="shrink-0 text-base font-black tracking-tight text-white">
        جاب‌آزمون
      </RouterLink>

      <div class="flex min-w-0 flex-1 items-center justify-center">
        <button
          type="button"
          class="flex h-9 w-9 items-center justify-center rounded-full text-white/90 transition hover:bg-white/10"
          aria-label="جستجو"
          @click="openSearch"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="7" />
            <path d="m20 20-3.5-3.5" />
          </svg>
        </button>
      </div>

      <div class="flex shrink-0 items-center gap-1.5">
        <NotificationBell v-if="auth.isAuthenticated" dark />
        <RouterLink
          v-if="auth.isAuthenticated"
          to="/profile"
          class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-full bg-white/15 text-sm font-bold text-white ring-1 ring-white/25"
          :title="auth.user?.name || 'پروفایل'"
        >
          <img
            v-if="auth.user?.avatar"
            :src="auth.user.avatar"
            alt=""
            class="h-full w-full object-cover"
          />
          <span v-else>{{ avatarLetter }}</span>
        </RouterLink>
        <RouterLink
          v-else
          to="/login"
          class="rounded-full bg-desk-orange px-3.5 py-1.5 text-xs font-bold text-white shadow-sm transition hover:bg-orange-500"
        >
          ورود
        </RouterLink>
      </div>
    </div>

    <!-- Search overlay -->
    <Teleport to="body">
      <div
        v-if="searchOpen"
        class="fixed inset-0 z-[60] lg:hidden"
        role="dialog"
        aria-modal="true"
        aria-label="جستجو"
      >
        <div class="absolute inset-0 bg-desk-dark/50" @click="closeSearch" />
        <div class="relative border-b border-white/10 bg-gradient-to-l from-desk-dark to-desk-blue px-3 pb-3 pt-[max(0.75rem,env(safe-area-inset-top))] shadow-lg">
          <div class="mx-auto flex max-w-app items-center gap-2">
            <button
              type="button"
              class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-white/90 hover:bg-white/10"
              aria-label="بستن"
              @click="closeSearch"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 6 6 18M6 6l12 12" />
              </svg>
            </button>
            <div class="min-w-0 flex-1">
              <GlobalSearch ref="searchRef" dark />
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </header>
</template>

<script setup>
import { computed, nextTick, ref } from 'vue';
import { useAuthStore } from '../stores/auth';
import GlobalSearch from './GlobalSearch.vue';
import NotificationBell from './NotificationBell.vue';

const auth = useAuthStore();
const searchOpen = ref(false);
const searchRef = ref(null);

const avatarLetter = computed(() => {
  const name = auth.user?.name?.trim();
  if (name) return name.charAt(0);
  const mobile = auth.user?.mobile || '';
  return mobile.slice(-2) || 'م';
});

async function openSearch() {
  searchOpen.value = true;
  await nextTick();
  const el = searchRef.value?.$el?.querySelector?.('input') || document.querySelector('.fixed input[type="search"]');
  el?.focus?.();
}

function closeSearch() {
  searchOpen.value = false;
}
</script>
