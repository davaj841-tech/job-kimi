<template>
  <div class="page-shell">
    <header v-if="title || $slots.title || $slots.actions" class="page-head">
      <div class="min-w-0">
        <h1 class="page-title">
          <slot name="title">{{ title }}</slot>
        </h1>
        <p v-if="subtitle" class="page-sub">{{ subtitle }}</p>
      </div>
      <div v-if="$slots.actions" class="shrink-0">
        <slot name="actions" />
      </div>
    </header>
    <slot />
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
})
</script>
