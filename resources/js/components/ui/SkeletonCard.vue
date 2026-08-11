<template>
  <div v-if="count" class="space-y-2">
    <div
      v-for="i in count"
      :key="i"
      class="animate-pulse rounded-2xl border border-surface-line bg-white p-4"
    >
      <div class="mb-3 h-4 w-1/3 rounded bg-slate-200" />
      <div class="mb-2 h-3 w-full rounded bg-slate-100" />
      <div class="h-3 w-2/3 rounded bg-slate-100" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  count: { type: Number, default: 3 },
})
</script>
