<template>
  <Card class="p-4">
    <h3 class="mb-4 text-lg font-bold text-ink dark:text-white">فعالیت‌های اخیر</h3>
    <div v-if="!items.length" class="py-6 text-center text-sm text-ink-muted">
      فعالیتی ثبت نشده
    </div>
    <ol v-else class="relative space-y-4 border-r-2 border-slate-200 pr-4 dark:border-slate-700">
      <li
        v-for="(item, i) in items"
        :key="i"
        class="relative opacity-0 motion-safe:animate-[fadeSlideUp_0.4s_ease-out_forwards]"
        :style="{ animationDelay: `${i * 100}ms` }"
      >
        <span
          class="absolute -right-[1.35rem] top-1 flex h-6 w-6 items-center justify-center rounded-full text-xs"
          :class="dotClass(item.color)"
        >
          {{ item.icon }}
        </span>
        <p class="font-medium text-ink dark:text-white">{{ item.title }}</p>
        <p class="text-xs text-ink-muted dark:text-slate-400">{{ item.meta }}</p>
      </li>
    </ol>
  </Card>
</template>

<script setup lang="ts">
import Card from '../ui/Card.vue'
import type { ActivityItem } from '@/stores/dashboardStore'

defineProps<{
  items: ActivityItem[]
}>()

function dotClass(color: ActivityItem['color']): string {
  const map: Record<ActivityItem['color'], string> = {
    blue: 'bg-sky-100 text-sky-700',
    emerald: 'bg-emerald-100 text-emerald-700',
    purple: 'bg-purple-100 text-purple-700',
    orange: 'bg-orange-100 text-orange-700',
  }
  return map[color] || map.blue
}
</script>

<style scoped>
@keyframes fadeSlideUp {
  from {
    opacity: 0;
    transform: translateY(8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
