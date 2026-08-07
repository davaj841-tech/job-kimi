<template>
  <div v-if="message" class="mb-3 rounded-xl border border-brand/20 bg-brand-soft px-3 py-2.5 text-sm text-brand">
    {{ message }}
  </div>
</template>

<script setup>
defineProps({
  message: { type: String, default: '' },
});
</script>
