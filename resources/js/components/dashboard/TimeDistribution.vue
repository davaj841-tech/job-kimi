<template>
  <Card class="p-4">
    <h3 class="mb-4 text-lg font-bold text-ink dark:text-white">توزیع زمان مطالعه</h3>
    <div class="relative mx-auto h-[180px] max-w-xs md:h-[200px]">
      <Doughnut :data="chartData" :options="options" :plugins="[centerTextPlugin]" />
    </div>
  </Card>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Doughnut } from 'vue-chartjs'
import { Chart as ChartJS, ArcElement, Tooltip, Legend, type Plugin } from 'chart.js'
import Card from '../ui/Card.vue'
import { toFaDigits } from '@/utils/format'
import type { TimeDistributionItem } from '@/stores/dashboardStore'

ChartJS.register(ArcElement, Tooltip, Legend)

const props = defineProps<{
  data: TimeDistributionItem[]
}>()

const chartData = computed(() => ({
  labels: props.data.map((d) => d.label),
  datasets: [
    {
      data: props.data.map((d) => Number(d.value || 0)),
      backgroundColor: props.data.map((d) => d.color),
      borderWidth: 0,
    },
  ],
}))

const centerTextPlugin: Plugin<'doughnut'> = {
  id: 'centerText',
  beforeDraw(chart) {
    const total = (chart.data.datasets[0]?.data as number[] || []).reduce(
      (s, v) => s + Number(v || 0),
      0
    )
    const { ctx, chartArea } = chart
    if (!chartArea) return
    const x = (chartArea.left + chartArea.right) / 2
    const y = (chartArea.top + chartArea.bottom) / 2
    ctx.save()
    ctx.textAlign = 'center'
    ctx.textBaseline = 'middle'
    ctx.font = 'bold 18px Estedad Variable, Estedad, Vazirmatn'
    ctx.fillStyle = '#0f172a'
    ctx.fillText(toFaDigits(total.toFixed(1)), x, y - 6)
    ctx.font = '11px Estedad Variable, Estedad, Vazirmatn'
    ctx.fillStyle = '#64748b'
    ctx.fillText('ساعت این هفته', x, y + 14)
    ctx.restore()
  },
}

const options = {
  responsive: true,
  maintainAspectRatio: false,
  cutout: '68%',
  plugins: {
    legend: {
      position: 'bottom' as const,
      rtl: true,
      labels: {
        boxWidth: 12,
        font: { size: 11, family: 'Estedad Variable, Estedad, Vazirmatn' },
      },
    },
    tooltip: {
      rtl: true,
      textDirection: 'rtl',
      callbacks: {
        label: (ctx: { label?: string; parsed: number }) =>
          ` ${ctx.label}: ${toFaDigits(ctx.parsed.toFixed(1))} ساعت`,
      },
    },
  },
}
</script>
