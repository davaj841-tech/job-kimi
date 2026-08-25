<template>
  <RouterLink
    :to="to"
    class="group flex flex-col gap-2 rounded-2xl border border-surface-line bg-surface p-4 transition duration-300 hover:-translate-y-0.5 hover:border-brand/30 hover:shadow-md dark:border-slate-700 dark:bg-slate-800"
  >
    <div
      class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-soft text-brand transition group-hover:bg-brand group-hover:text-white dark:bg-slate-700"
    >
      <component :is="icon" class="h-5 w-5" />
    </div>
    <div>
      <p class="text-sm font-bold text-ink dark:text-white">{{ label }}</p>
      <p
        v-if="description"
        class="mt-0.5 text-xs text-ink-muted dark:text-slate-400"
      >
        {{ description }}
      </p>
    </div>
  </RouterLink>
</template>

<script setup lang="ts">
import type { Component } from 'vue'

defineProps<{
  to: string
  icon: Component
  label: string
  description?: string
}>()
</script>
