<template>
  <div
    class="animate-pulse overflow-hidden rounded-2xl border border-surface-line bg-white"
  >
    <div class="grid grid-cols-4 gap-2 border-b bg-slate-50 px-4 py-3">
      <div v-for="i in 4" :key="i" class="h-3 rounded bg-slate-200" />
    </div>
    <div
      v-for="r in rows"
      :key="r"
      class="grid grid-cols-4 gap-2 border-b border-slate-50 px-4 py-3"
    >
      <div v-for="i in 4" :key="i" class="h-3 rounded bg-slate-100" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  rows: { type: Number, default: 5 },
})
</script>
