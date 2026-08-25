<template>
  <div class="relative mx-auto h-64 max-w-xs">
    <Doughnut :data="chartData" :options="options" />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Doughnut } from 'vue-chartjs'
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js'

ChartJS.register(ArcElement, Tooltip, Legend)

const props = defineProps({
  data: { type: Array, default: () => [] },
})

const palette = [
  '#f97316',
  '#0f2744',
  '#22c55e',
  '#3b82f6',
  '#a855f7',
  '#eab308',
]

const chartData = computed(() => ({
  labels: props.data.map((d) => d.label),
  datasets: [
    {
      data: props.data.map((d) => Number(d.value || 0)),
      backgroundColor: props.data.map((_, i) => palette[i % palette.length]),
      borderWidth: 0,
    },
  ],
}))

const options = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom',
      labels: {
        boxWidth: 12,
        font: { size: 11, family: 'Estedad Variable, Estedad, Vazirmatn' },
      },
    },
  },
}
</script>
