<template>
  <Card class="flex items-center gap-4 p-5">
    <div
      class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl"
      :class="iconBg"
    >
      <component :is="icon" class="h-6 w-6" :class="iconColor" />
    </div>
    <div class="min-w-0">
      <p class="text-sm text-ink-muted dark:text-slate-400">{{ label }}</p>
      <p
        class="truncate text-xl font-bold text-ink dark:text-white sm:text-2xl"
      >
        {{ value }}
      </p>
      <p v-if="trend" class="mt-0.5 text-xs" :class="trendColor">{{ trend }}</p>
    </div>
  </Card>
</template>

<script setup lang="ts">
import type { Component } from 'vue'
import Card from './Card.vue'

defineProps<{
  label: string
  value: string | number
  icon: Component
  iconBg?: string
  iconColor?: string
  trend?: string
  trendColor?: string
}>()
</script>
