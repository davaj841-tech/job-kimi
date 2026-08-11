<template>
  <div class="relative h-64">
    <Line :data="chartData" :options="options" />
  </div>
</template>

<script setup>
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

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Tooltip,
  Filler,
  Legend
)

const props = defineProps({
  data: { type: Array, default: () => [] },
  color: { type: String, default: '#f97316' },
  valueKey: { type: String, default: 'amount' },
})

const chartData = computed(() => ({
  labels: props.data.map((d) => formatLabel(d.date)),
  datasets: [
    {
      label: 'مقدار',
      data: props.data.map((d) =>
        Number(d[props.valueKey] ?? d.amount ?? d.count ?? 0)
      ),
      borderColor: props.color,
      backgroundColor: `${props.color}22`,
      fill: true,
      tension: 0.35,
      pointRadius: 2,
    },
  ],
}))

const options = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: {
    x: {
      grid: { display: false },
      ticks: { maxTicksLimit: 8, font: { size: 10 } },
    },
    y: { beginAtZero: true, ticks: { font: { size: 10 } } },
  },
}

function formatLabel(date) {
  if (!date) return ''
  try {
    return new Date(date).toLocaleDateString('fa-IR', {
      month: 'numeric',
      day: 'numeric',
    })
  } catch {
    return String(date)
  }
}
</script>
