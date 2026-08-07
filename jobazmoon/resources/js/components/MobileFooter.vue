<template>
  <footer class="border-t border-surface-line bg-white px-4 py-8 lg:hidden">
    <p class="mb-3 text-center text-sm font-black text-brand">جاب‌آزمون</p>
    <div class="mb-4 flex flex-wrap justify-center gap-x-4 gap-y-2 text-xs text-ink-muted">
      <RouterLink to="/about">درباره ما</RouterLink>
      <RouterLink to="/contact">تماس</RouterLink>
      <RouterLink to="/terms">قوانین</RouterLink>
      <RouterLink to="/privacy">حریم خصوصی</RouterLink>
    </div>
    <div class="mb-4 flex justify-center">
      <TrustBadges />
    </div>
    <p class="text-center text-[11px] text-ink-muted">تمامی حقوق محفوظ است © {{ year }} جاب‌آزمون</p>
  </footer>
</template>

<script setup>
import TrustBadges from './TrustBadges.vue';

const year = new Intl.DateTimeFormat('fa-IR', { year: 'numeric' }).format(new Date());
</script>
