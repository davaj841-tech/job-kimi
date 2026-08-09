<template>
  <div class="rounded-2xl bg-white p-5 shadow-sm">
    <div class="mb-3 flex items-start justify-between">
      <div
        class="flex h-11 w-11 items-center justify-center rounded-xl text-lg text-white"
        :style="{ backgroundColor: color }"
      >
        {{ icon }}
      </div>
      <span
        v-if="trend"
        class="rounded-md px-2 py-0.5 text-[11px] font-bold"
        :class="trend.startsWith('-') ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-600'"
      >
        {{ trend }}
      </span>
    </div>
    <p class="text-sm text-slate-500">{{ title }}</p>
    <p class="mt-1 text-2xl font-black text-slate-800">{{ value }}</p>
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  value: { type: [String, Number], required: true },
  icon: { type: String, default: '•' },
  color: { type: String, default: '#0f2744' },
  trend: { type: String, default: '' },
});
</script>
