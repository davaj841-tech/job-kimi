<template>
  <RouterLink
    :to="to"
    class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors duration-200"
    :class="
      active
        ? 'bg-brand-soft text-brand dark:bg-brand/20 dark:text-brand'
        : 'text-ink-muted hover:bg-slate-100 hover:text-ink dark:text-slate-400 dark:hover:bg-slate-700/60 dark:hover:text-white'
    "
  >
    <component :is="icon" class="h-5 w-5 shrink-0" />
    <span>{{ label }}</span>
  </RouterLink>
</template>

<script setup lang="ts">
import type { Component } from 'vue'
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const props = defineProps<{
  to: string
  icon: Component
  label: string
}>()

const route = useRoute()
const active = computed(
  () => route.path === props.to || route.path.startsWith(props.to + '/')
)
</script>
