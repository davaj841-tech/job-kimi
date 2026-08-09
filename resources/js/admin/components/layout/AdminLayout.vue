<template>
  <div class="flex h-dvh overflow-hidden bg-slate-100">
    <AdminSidebar />
    <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
      <AdminHeader />
      <main class="flex-1 overflow-y-auto p-6">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import AdminHeader from './AdminHeader.vue';
import AdminSidebar from './AdminSidebar.vue';
</script>
