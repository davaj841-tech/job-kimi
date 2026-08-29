<template>
  <AppHeader v-bind="$attrs" />
</template>

<script setup lang="ts">
import AppHeader from '../AppHeader.vue'

defineOptions({ inheritAttrs: false })
</script>
