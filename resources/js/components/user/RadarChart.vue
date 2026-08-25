<template>
  <div
    v-if="chartData.labels.length"
    class="relative mx-auto h-56 w-full max-w-sm"
  >
    <Radar :data="chartData" :options="options" />
  </div>
  <p v-else class="py-8 text-center text-sm text-ink-muted dark:text-slate-400">
    دادهٔ کافی برای نمودار نیست
  </p>
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

ChartJS.register(
  RadialLinearScale,
  PointElement,
  LineElement,
  Filler,
  Tooltip,
  Legend
)

const props = defineProps<{
  data: Array<{ label: string; value: number }>
}>()

const chartData = computed(() => ({
  labels: props.data.map((d) => d.label),
  datasets: [
    {
      label: 'میانگین',
      data: props.data.map((d) => Number(d.value || 0)),
      backgroundColor: 'rgba(239, 57, 78, 0.18)',
      borderColor: '#ef394e',
      pointBackgroundColor: '#ef394e',
      pointBorderColor: '#fff',
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
  },
}
</script>
