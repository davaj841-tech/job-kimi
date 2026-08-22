<template>
  <Card class="p-4">
    <h3 class="mb-4 text-lg font-bold text-ink dark:text-white">برنامه پیشنهادی امروز</h3>
    <div class="space-y-3">
      <div
        v-for="item in items"
        :key="item.num"
        class="flex items-start gap-3 rounded-xl border border-slate-100 p-3 dark:border-slate-700"
      >
        <span
          class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-sm font-bold text-white"
          :class="numClass(item.color)"
        >
          {{ toFaDigits(item.num) }}
        </span>
        <div class="min-w-0 flex-1">
          <p class="font-medium text-ink dark:text-white">{{ item.title }}</p>
          <p class="text-xs text-ink-muted dark:text-slate-400">{{ item.meta }}</p>
        </div>
        <RouterLink
          v-if="item.link"
          :to="item.link"
          class="shrink-0 rounded-lg px-3 py-1.5 text-xs font-bold text-white transition hover:opacity-90"
          :class="btnClass(item.color)"
        >
          {{ item.action }}
        </RouterLink>
      </div>
    </div>
  </Card>
</template>

<script setup lang="ts">
import Card from '../ui/Card.vue'
import { toFaDigits } from '@/utils/format'
import type { DailyPlanItem } from '@/stores/dashboardStore'

defineProps<{
  items: DailyPlanItem[]
}>()

function numClass(color: string): string {
  const map: Record<string, string> = {
    blue: 'bg-sky-500',
    emerald: 'bg-emerald-500',
    amber: 'bg-amber-500',
  }
  return map[color] || 'bg-brand'
}

function btnClass(color: string): string {
  return numClass(color)
}
</script>
