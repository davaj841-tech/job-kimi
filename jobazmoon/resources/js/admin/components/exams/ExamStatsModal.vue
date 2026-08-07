<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" >
    <div class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
      <div class="mb-4 flex items-center justify-between">
        <h3 class="text-lg font-bold">آمار آزمون</h3>
        <button @click="$emit('close')">✕</button>
      </div>

      <div v-if="loading" class="py-10 text-center text-sm text-slate-500">در حال بارگذاری...</div>
      <template v-else-if="stats">
        <p class="mb-4 text-sm text-slate-600">{{ stats.exam?.title }}</p>
        <div class="mb-5 grid grid-cols-3 gap-3 text-center">
          <div class="rounded-xl bg-slate-50 p-3">
            <p class="text-xl font-black">{{ fa(stats.total_attempts) }}</p>
            <p class="text-[11px] text-slate-500">تلاش‌ها</p>
          </div>
          <div class="rounded-xl bg-slate-50 p-3">
            <p class="text-xl font-black">{{ fa(stats.average_score) }}</p>
            <p class="text-[11px] text-slate-500">میانگین نمره</p>
          </div>
          <div class="rounded-xl bg-slate-50 p-3">
            <p class="text-xl font-black">{{ fa(stats.pass_rate) }}%</p>
            <p class="text-[11px] text-slate-500">نرخ قبولی</p>
          </div>
        </div>

        <h4 class="mb-2 text-sm font-bold">۵ نفر برتر</h4>
        <ul class="mb-5 space-y-2">
          <li
            v-for="(p, i) in stats.top_participants || []"
            :key="i"
            class="flex items-center justify-between rounded-xl border border-slate-100 px-3 py-2 text-sm"
          >
            <span>{{ p.user_name }}</span>
            <span class="font-bold text-orange-600">{{ fa(p.score) }}</span>
          </li>
          <li v-if="!(stats.top_participants || []).length" class="text-xs text-slate-400">داده‌ای نیست</li>
        </ul>

        <h4 class="mb-2 text-sm font-bold">توزیع موضوعی سوالات</h4>
        <div class="h-56">
          <DoughnutChart :data="chartData" />
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import DoughnutChart from '../ui/DoughnutChart.vue';

const props = defineProps({
  open: Boolean,
  loading: Boolean,
  stats: { type: Object, default: null },
});

defineEmits(['close']);

const chartData = computed(() => props.stats?.subject_breakdown || []);

function fa(n) {
  return new Intl.NumberFormat('fa-IR').format(Number(n || 0));
}
</script>
