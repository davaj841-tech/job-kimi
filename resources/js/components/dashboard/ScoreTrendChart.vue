<template>
  <Card class="p-4">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
      <h3 class="text-lg font-bold text-ink dark:text-white">روند نمرات</h3>
      <p
        v-if="growthLabel"
        class="text-xs font-medium text-emerald-600 dark:text-emerald-400"
      >
        {{ growthLabel }}
      </p>
    </div>
    <div v-if="!data.length" class="py-10 text-center text-sm text-ink-muted">
      هنوز داده‌ای برای نمودار نیست
    </div>
    <div v-else class="relative h-[180px] md:h-[200px]">
      <Line :data="chartData" :options="options" />
    </div>
  </Card>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Line } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Tooltip,
  Filler,
  Legend,
} from 'chart.js'
import type { ChartOptions, TooltipItem } from 'chart.js'
import Card from '../ui/Card.vue'
import { toFaDigits } from '@/utils/format'
import type { ScoreHistoryItem } from '@/stores/dashboardStore'

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Tooltip,
  Filler,
  Legend
)

const props = defineProps<{
  data: ScoreHistoryItem[]
  growthLabel?: string
}>()

const chartData = computed(() => ({
  labels: props.data.map((d) => d.exam),
  datasets: [
    {
      label: 'نمره',
      data: props.data.map((d) => Number(d.score || 0)),
      borderColor: '#3b82f6',
      backgroundColor: 'rgba(59, 130, 246, 0.15)',
      fill: true,
      tension: 0.35,
      pointRadius: 4,
      pointHoverRadius: 6,
      pointBackgroundColor: '#3b82f6',
    },
  ],
}))

const options = computed<ChartOptions<'line'>>(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      rtl: true,
      textDirection: 'rtl',
      callbacks: {
        label: (ctx: TooltipItem<'line'>) => {
          const y = ctx.parsed.y
          if (y == null) {
            return ''
          }

          return ` نمره: ${toFaDigits(Math.round(y))}٪`
        },
      },
    },
  },
  scales: {
    x: {
      grid: { display: false },
      ticks: {
        font: { family: 'Estedad Variable, Estedad, Vazirmatn', size: 10 },
      },
    },
    y: {
      min: 0,
      max: 100,
      ticks: {
        font: { size: 10 },
        callback: (v: string | number) => toFaDigits(v) + '٪',
      },
    },
  },
}))
</script>
