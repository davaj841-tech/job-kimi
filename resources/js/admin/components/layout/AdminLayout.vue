<template>
  <div class="flex h-dvh overflow-hidden bg-slate-100">
    <div
      v-if="sidebarOpen"
      class="fixed inset-0 z-40 bg-black/40 lg:hidden"
      @click="sidebarOpen = false"
    />
    <div
      class="fixed inset-y-0 right-0 z-50 lg:static lg:z-auto"
      :class="sidebarOpen ? 'block' : 'hidden lg:block'"
    >
      <AdminSidebar :open="sidebarOpen" @close="sidebarOpen = false" />
    </div>

    <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
      <AdminHeader @toggle-sidebar="sidebarOpen = !sidebarOpen" />
      <main class="flex-1 overflow-y-auto p-4 pb-20 sm:p-6 lg:pb-6">
        <RouterView />
      </main>
      <nav
        class="fixed inset-x-0 bottom-0 z-40 grid grid-cols-3 border-t border-surface-line bg-white lg:hidden"
        style="padding-bottom: env(safe-area-inset-bottom)"
        aria-label="بازگشت"
      >
        <a
          href="/"
          class="flex flex-col items-center justify-center gap-0.5 py-2 text-[10px] font-bold text-desk-dark"
        >
          صفحه اول
        </a>
        <RouterLink
          to="/admin/dashboard"
          class="flex flex-col items-center justify-center gap-0.5 py-2 text-[10px] font-bold text-slate-500"
        >
          داشبورد
        </RouterLink>
        <RouterLink
          v-if="isAdmin"
          to="/admin/settings"
          class="flex flex-col items-center justify-center gap-0.5 py-2 text-[10px] font-bold text-slate-500"
        >
          تنظیمات
        </RouterLink>
      </nav>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useAdminAuthStore } from '../../stores/auth'
import AdminHeader from './AdminHeader.vue'
import AdminSidebar from './AdminSidebar.vue'

const sidebarOpen = ref(false)
const auth = useAdminAuthStore()
const isAdmin = computed(() => auth.isAdmin)

onMounted(() => {
  const views = import.meta.glob('../../views/*.vue')
  Object.values(views).forEach((load) => {
    load().catch(() => {})
  })
})
</script>
