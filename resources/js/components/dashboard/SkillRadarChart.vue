<template>
  <Card class="p-4">
    <h3 class="mb-4 text-lg font-bold text-ink dark:text-white">تحلیل مهارت‌ها</h3>
    <div v-if="!labels.length" class="py-10 text-center text-sm text-ink-muted">
      دادهٔ کافی برای نمودار نیست
    </div>
    <div v-else class="relative mx-auto h-[180px] max-w-md md:h-[200px]">
      <Radar :data="chartData" :options="options" />
    </div>
    <div class="mt-3 flex justify-center gap-4 text-xs text-ink-muted">
      <span class="flex items-center gap-1">
        <span class="inline-block h-2 w-4 rounded bg-emerald-500" /> شما
      </span>
      <span class="flex items-center gap-1">
        <span class="inline-block h-0.5 w-4 border-t-2 border-dashed border-slate-400" /> میانگین
      </span>
    </div>
  </Card>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Radar } from 'vue-chartjs'
import {
  Chart as ChartJS,
  RadialLinearScale,
  PointElement,
  LineElement,
  Filler,
  Tooltip,
  Legend,
} from 'chart.js'
import Card from '../ui/Card.vue'
import { toFaDigits } from '@/utils/format'

ChartJS.register(RadialLinearScale, PointElement, LineElement, Filler, Tooltip, Legend)

const props = defineProps<{
  labels: string[]
  userScores: number[]
  avgScores: number[]
}>()

const chartData = computed(() => ({
  labels: props.labels,
  datasets: [
    {
      label: 'شما',
      data: props.userScores.map((v) => Number(v || 0)),
      backgroundColor: 'rgba(16, 185, 129, 0.2)',
      borderColor: '#10b981',
      pointBackgroundColor: '#10b981',
      borderWidth: 2,
    },
    {
      label: 'میانگین کاربران',
      data: props.avgScores.map((v) => Number(v || 0)),
      backgroundColor: 'transparent',
      borderColor: '#94a3b8',
      borderDash: [6, 4],
      pointRadius: 0,
      borderWidth: 2,
    },
  ],
}))

const options = {
  responsive: true,
  maintainAspectRatio: false,
  scales: {
    r: {
      beginAtZero: true,
      max: 100,
      ticks: { display: false },
      grid: { color: 'rgba(148, 163, 184, 0.35)' },
      pointLabels: {
        font: { family: 'Estedad Variable, Estedad, Vazirmatn', size: 11 },
      },
    },
  },
  plugins: {
    legend: { display: false },
    tooltip: {
      rtl: true,
      textDirection: 'rtl',
      callbacks: {
        label: (ctx: { dataset: { label?: string }; parsed: { r: number } }) =>
          ` ${ctx.dataset.label}: ${toFaDigits(Math.round(ctx.parsed.r))}٪`,
      },
    },
  },
}
</script>
