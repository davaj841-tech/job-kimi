<template>
  <div
    class="animate-pulse rounded bg-slate-200 dark:bg-slate-700"
    :class="$attrs.class"
  >
    <slot />
  </div>
</template>

<script setup lang="ts">
defineOptions({ inheritAttrs: false })
</script>
