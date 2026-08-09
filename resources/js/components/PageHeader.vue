<template>
  <div class="mb-4 flex items-center justify-between gap-3">
    <div>
      <h1 class="section-title">{{ title }}</h1>
      <p v-if="subtitle" class="mt-0.5 text-xs text-ink-muted">{{ subtitle }}</p>
    </div>
    <slot name="action" />
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
});
</script>
