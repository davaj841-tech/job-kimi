<template>
  <section class="bg-surface-page py-3 sm:py-4">
    <div class="mx-auto grid max-w-7xl items-stretch gap-3 px-4 md:grid-cols-2">
      <ResumeBanner embedded />
      <div class="hidden md:block">
        <FAQSection embedded />
      </div>
    </div>
  </section>
</template>

<script setup>
import FAQSection from './FAQSection.vue'
import ResumeBanner from './ResumeBanner.vue'
</script>
