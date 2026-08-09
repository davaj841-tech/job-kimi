<template>
  <div class="animate-pulse rounded-2xl border border-surface-line bg-white p-4">
    <div class="mb-4 h-4 w-1/4 rounded bg-slate-200" />
    <div class="flex h-40 items-end gap-2">
      <div v-for="(h, i) in bars" :key="i" class="flex-1 rounded-t bg-slate-100" :style="{ height: h }" />
    </div>
  </div>
</template>

<script setup>
const bars = ['40%', '70%', '55%', '85%', '45%', '65%', '50%'];
</script>
