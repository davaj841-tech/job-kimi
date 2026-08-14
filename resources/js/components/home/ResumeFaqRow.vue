<template>
  <section class="bg-surface-page pt-3 pb-0 sm:pt-4 sm:pb-1">
    <div class="mx-auto grid max-w-7xl items-stretch gap-3 px-4 md:grid-cols-2">
      <ResumeBanner embedded />
      <AppDownloadSection embedded />
    </div>
  </section>
</template>

<script setup>
import AppDownloadSection from './AppDownloadSection.vue'
import ResumeBanner from './ResumeBanner.vue'
</script>
