<template>
  <span class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-700">
    <span
      class="h-2 w-2 rounded-full"
      :class="status === 'blocked' ? 'bg-red-500' : 'bg-emerald-500'"
    />
    {{ status === 'blocked' ? 'مسدود' : 'فعال' }}
  </span>
</template>

<script setup>
defineProps({
  status: { type: String, default: 'active' },
});
</script>
