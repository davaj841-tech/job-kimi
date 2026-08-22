<template>
  <Card class="p-4">
    <h3 class="mb-4 text-lg font-bold text-ink dark:text-white">نقاط قوت و ضعف</h3>
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
      <div>
        <p class="mb-3 text-sm font-bold text-emerald-600">✅ قوی‌ترین‌ها</p>
        <div class="space-y-3">
          <div v-for="item in strengths" :key="item.name">
            <div class="mb-1 flex justify-between text-sm">
              <span>{{ item.name }}</span>
              <span class="font-medium">{{ toFaDigits(Math.round(item.score)) }}٪</span>
            </div>
            <ProgressBar :percent="item.score" color="green" />
          </div>
          <p v-if="!strengths.length" class="text-sm text-ink-muted">داده‌ای موجود نیست</p>
        </div>
      </div>
      <div>
        <p class="mb-3 text-sm font-bold text-red-500">⚠️ نیاز به تمرین</p>
        <div class="space-y-3">
          <div v-for="item in weaknesses" :key="item.name">
            <div class="mb-1 flex justify-between text-sm">
              <span>{{ item.name }}</span>
              <span class="font-medium">{{ toFaDigits(Math.round(item.score)) }}٪</span>
            </div>
            <ProgressBar :percent="item.score" color="brand" />
          </div>
          <p v-if="!weaknesses.length" class="text-sm text-ink-muted">داده‌ای موجود نیست</p>
        </div>
      </div>
    </div>
    <div
      v-if="suggestion"
      class="mt-4 rounded-xl bg-sky-50 px-4 py-3 text-sm text-sky-800 dark:bg-sky-900/30 dark:text-sky-200"
    >
      💡 {{ suggestion }}
    </div>
  </Card>
</template>

<script setup lang="ts">
import Card from '../ui/Card.vue'
import ProgressBar from '../ui/ProgressBar.vue'
import { toFaDigits } from '@/utils/format'
import type { SkillItem } from '@/stores/dashboardStore'

defineProps<{
  strengths: SkillItem[]
  weaknesses: SkillItem[]
  suggestion: string
}>()
</script>
