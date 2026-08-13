<template>
  <header class="sticky top-0 z-30 border-b border-surface-line bg-surface">
    <div
      class="mx-auto flex h-13 max-w-app items-center gap-2.5 px-3 pt-[env(safe-area-inset-top)]"
      style="height: 3.25rem"
    >
      <RouterLink
        to="/"
        class="shrink-0"
        aria-label="صفحه اصلی"
      >
        <SiteBrandLogo
          img-class="h-8 w-auto max-w-[8.5rem]"
          text-class="text-[15px] text-desk-text"
        />
      </RouterLink>

      <button
        type="button"
        class="flex min-w-0 flex-1 items-center justify-start gap-2 rounded-xl border border-surface-line bg-surface-page px-3 py-2 text-right text-xs text-desk-muted transition active:opacity-80"
        @click="openSearch"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="15"
          height="15"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          class="shrink-0"
        >
          <circle cx="11" cy="11" r="7" />
          <path d="m20 20-3.5-3.5" />
        </svg>
        <span class="truncate">جستجو…</span>
      </button>

      <div class="flex shrink-0 items-center gap-1.5">
        <ThemeToggle />
        <NotificationBell v-if="auth.isAuthenticated" />
        <RouterLink
          v-if="auth.isAuthenticated"
          to="/profile"
          class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-lg bg-desk-dark/[0.06] text-xs font-bold text-desk-text dark:bg-white/10"
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
          class="rounded-lg bg-brand px-3 py-1.5 text-xs font-bold text-white"
        >
          ورود
        </RouterLink>
      </div>
    </div>

    <Teleport to="body">
      <div
        v-if="searchOpen"
        class="fixed inset-0 z-[60] lg:hidden"
        role="dialog"
        aria-modal="true"
        aria-label="جستجو"
      >
        <div class="absolute inset-0 bg-desk-dark/40" @click="closeSearch" />
        <div
          class="relative border-b border-surface-line bg-surface px-3 pb-3 pt-[max(0.75rem,env(safe-area-inset-top))] shadow-lg"
        >
          <div class="mx-auto flex max-w-app items-center gap-2">
            <button
              type="button"
              class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-desk-muted hover:bg-surface-page"
              aria-label="بستن"
              @click="closeSearch"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path d="M18 6 6 18M6 6l12 12" />
              </svg>
            </button>
            <div class="min-w-0 flex-1">
              <GlobalSearch ref="searchRef" />
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </header>
</template>

<script setup>
import { computed, nextTick, ref } from 'vue'
import { useAuthStore } from '../stores/auth'
import GlobalSearch from './GlobalSearch.vue'
import NotificationBell from './NotificationBell.vue'
import SiteBrandLogo from './SiteBrandLogo.vue'
import ThemeToggle from './ThemeToggle.vue'

const auth = useAuthStore()
const searchOpen = ref(false)
const searchRef = ref(null)

const avatarLetter = computed(() => {
  const name = auth.user?.name?.trim()
  if (name) return name.charAt(0)
  const mobile = auth.user?.mobile || ''
  return mobile.slice(-2) || 'م'
})

async function openSearch() {
  searchOpen.value = true
  await nextTick()
  const el =
    searchRef.value?.$el?.querySelector?.('input') ||
    document.querySelector('.fixed input[type="search"]')
  el?.focus?.()
}

function closeSearch() {
  searchOpen.value = false
}
</script>
