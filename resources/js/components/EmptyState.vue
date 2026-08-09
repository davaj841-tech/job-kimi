<template>
  <div class="flex flex-col items-center justify-center px-6 py-14 text-center">
    <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-surface-page text-2xl text-ink-muted">
      {{ icon }}
    </div>
    <p class="mb-1 text-sm font-bold text-ink">{{ title }}</p>
    <p v-if="description" class="mb-4 text-xs leading-6 text-ink-muted">{{ description }}</p>
    <slot />
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, default: 'موردی یافت نشد' },
  description: { type: String, default: '' },
  icon: { type: String, default: '∅' },
});
</script>
