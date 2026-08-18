<template>
  <div class="space-y-5">
    <div class="grid gap-5 md:grid-cols-2">
      <div class="flex flex-col items-center justify-center rounded-2xl bg-slate-50 p-5 dark:bg-slate-800/50">
        <p class="mb-3 text-sm font-bold text-ink dark:text-white">نمودار کلی نتیجه</p>
        <svg viewBox="0 0 120 120" class="h-40 w-40">
          <circle
            cx="60"
            cy="60"
            r="48"
            fill="none"
            stroke="currentColor"
            class="text-slate-200 dark:text-slate-700"
            stroke-width="12"
          />
          <circle
            cx="60"
            cy="60"
            r="48"
            fill="none"
            :stroke="ringColor"
            stroke-width="12"
            stroke-linecap="round"
            :stroke-dasharray="circumference"
            :stroke-dashoffset="dashOffset"
            transform="rotate(-90 60 60)"
          />
          <text
            x="60"
            y="56"
            text-anchor="middle"
            class="fill-current text-ink dark:fill-white"
            style="font-size: 22px; font-weight: 800"
          >
            {{ faPct }}٪
          </text>
          <text x="60" y="74" text-anchor="middle" fill="#94a3b8" style="font-size: 9px">
            عملکرد کلی
          </text>
        </svg>
        <div class="mt-3 flex flex-wrap justify-center gap-3 text-[11px]">
          <span class="inline-flex items-center gap-1 text-emerald-600">
            <i class="h-2 w-2 rounded-full bg-emerald-500" /> درست {{ fa(correct) }}
          </span>
          <span class="inline-flex items-center gap-1 text-brand">
            <i class="h-2 w-2 rounded-full bg-brand" /> غلط {{ fa(wrong) }}
          </span>
          <span class="inline-flex items-center gap-1 text-slate-500">
            <i class="h-2 w-2 rounded-full bg-slate-400" /> نزده {{ fa(blank) }}
          </span>
        </div>
      </div>

      <div class="rounded-2xl bg-slate-50 p-5 dark:bg-slate-800/50">
        <p class="mb-4 text-sm font-bold text-ink dark:text-white">تحلیل درس‌ها</p>
        <div v-if="subjects.length" class="space-y-3">
          <div v-for="row in subjects" :key="row.subject || row.subject_label">
            <div class="mb-1 flex items-center justify-between gap-2 text-xs">
              <span class="min-w-0 font-medium dark:text-slate-200">
                {{ row.emoji || '📘' }} {{ row.subject_label || row.subject }}
              </span>
              <span class="shrink-0 font-bold" :class="pctClass(row.percentage)">
                {{ fa(Math.round(row.percentage || 0)) }}٪
              </span>
            </div>
            <div class="h-3 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
              <div
                class="h-full rounded-full transition-all duration-700"
                :class="barClass(row.percentage)"
                :style="{ width: `${Math.min(100, row.percentage || 0)}%` }"
              />
            </div>
            <p class="mt-0.5 text-[10px] text-ink-muted">
              {{ fa(row.correct) }} از {{ fa(row.total) }} صحیح
            </p>
          </div>
        </div>
        <p v-else class="text-center text-xs text-ink-muted">داده درسی موجود نیست.</p>
      </div>
    </div>

    <div class="rounded-2xl bg-slate-50 p-5 dark:bg-slate-800/50">
      <p class="mb-4 text-sm font-bold text-ink dark:text-white">نمودار میله‌ای دروس</p>
      <div v-if="subjects.length" class="overflow-x-auto">
        <div class="flex min-w-full items-end justify-start gap-3" :style="{ minHeight: '220px' }">
          <div
            v-for="row in subjects"
            :key="'bar-' + (row.subject || row.subject_label)"
            class="flex w-20 shrink-0 flex-col items-center justify-end"
          >
            <span class="mb-1 text-xs font-bold" :class="pctClass(row.percentage)">
              {{ fa(Math.round(row.percentage || 0)) }}٪
            </span>
            <div class="flex h-40 w-full items-end justify-center rounded-md bg-slate-200/70 dark:bg-slate-700/60 px-2">
              <div
                class="w-8 rounded-t-md transition-all duration-700"
                :class="barClass(row.percentage)"
                :style="{ height: `${Math.min(100, Math.max(8, row.percentage || 0))}%` }"
              />
            </div>
            <p class="mt-2 w-full text-center text-[11px] font-bold leading-5 text-ink dark:text-slate-200">
              {{ row.emoji || '📘' }}
              <br />
              {{ row.subject_label || row.subject }}
            </p>
          </div>
        </div>
      </div>
      <p v-else class="text-center text-xs text-ink-muted">داده درسی موجود نیست.</p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { toFaDigits } from '../../utils/format'

const props = defineProps({
  percentage: { type: Number, default: 0 },
  correct: { type: Number, default: 0 },
  wrong: { type: Number, default: 0 },
  blank: { type: Number, default: 0 },
  subjects: { type: Array, default: () => [] },
})

const circumference = 2 * Math.PI * 48
const pct = computed(() => Math.max(0, Math.min(100, Number(props.percentage) || 0)))
const dashOffset = computed(() => circumference * (1 - pct.value / 100))
const faPct = computed(() => toFaDigits(Math.round(pct.value)))
const ringColor = computed(() => {
  if (pct.value >= 70) return '#10b981'
  if (pct.value >= 50) return '#f59e0b'
  return '#ef394e'
})

function fa(v) {
  return toFaDigits(v ?? 0)
}

function barClass(p) {
  const n = Number(p) || 0
  if (n >= 70) return 'bg-emerald-500'
  if (n >= 50) return 'bg-amber-500'
  return 'bg-brand'
}

function pctClass(p) {
  const n = Number(p) || 0
  if (n >= 70) return 'text-emerald-600'
  if (n >= 50) return 'text-amber-600'
  return 'text-brand'
}
</script>
