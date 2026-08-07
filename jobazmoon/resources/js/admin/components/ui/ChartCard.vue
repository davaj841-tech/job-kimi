<template>
  <div class="rounded-2xl bg-white p-5 shadow-sm">
    <h3 class="mb-4 text-base font-bold text-slate-800">{{ title }}</h3>
    <slot />
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
});
</script>
