<template>
  <div
    class="overflow-hidden rounded-xl bg-gradient-to-l from-indigo-600 to-purple-600 p-5 text-white shadow-sm"
  >
    <div
      class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
    >
      <div>
        <p class="text-lg font-bold">
          🔥 رکورد مطالعه: {{ toFaDigits(streak.current) }} روز متوالی
        </p>
        <p class="mt-1 text-sm text-indigo-100">
          {{ toFaDigits(streak.days_to_badge) }} روز دیگر تا نشان «{{
            streak.target_badge
          }}» — به رتبه {{ toFaDigits(streak.target_rank) }} نزدیک می‌شوید!
        </p>
      </div>
      <div class="flex gap-2">
        <div
          v-for="(day, i) in streak.week_days"
          :key="i"
          class="flex flex-col items-center gap-1"
        >
          <span
            class="flex h-8 w-8 items-center justify-center rounded-lg text-xs font-bold transition"
            :class="
              day.active
                ? 'bg-white text-indigo-700'
                : 'bg-white/20 text-white/70'
            "
          >
            {{ day.label }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { toFaDigits } from '@/utils/format'
import type { StreakData } from '@/stores/dashboardStore'

defineProps<{
  streak: StreakData
}>()
</script>
