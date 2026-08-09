<template>
  <div class="relative h-64">
    <Bar :data="chartData" :options="options" />
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Tooltip,
  Legend,
} from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend);

const props = defineProps({
  data: { type: Array, default: () => [] },
  color: { type: String, default: '#0f2744' },
});

const chartData = computed(() => ({
  labels: props.data.map((d) => formatLabel(d.date)),
  datasets: [
    {
      label: 'کاربران',
      data: props.data.map((d) => Number(d.count || 0)),
      backgroundColor: props.color,
      borderRadius: 6,
      maxBarThickness: 18,
    },
  ],
}));

const options = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: {
    x: { grid: { display: false }, ticks: { maxTicksLimit: 8, font: { size: 10 } } },
    y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } } },
  },
};

function formatLabel(date) {
  if (!date) return '';
  try {
    return new Date(date).toLocaleDateString('fa-IR', { month: 'numeric', day: 'numeric' });
  } catch {
    return String(date);
  }
}
</script>
