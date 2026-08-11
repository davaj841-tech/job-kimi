<template>
  <footer class="border-t border-surface-line bg-white px-4 py-4 lg:hidden">
    <p class="mb-2 text-center text-xs font-black text-desk-dark">جاب‌آزمون</p>
    <div
      class="mb-2.5 flex flex-wrap justify-center gap-x-3 gap-y-1 text-[11px] text-desk-muted"
    >
      <RouterLink to="/articles">مقالات</RouterLink>
      <RouterLink to="/about">درباره ما</RouterLink>
      <RouterLink to="/contact">تماس</RouterLink>
      <RouterLink to="/terms">قوانین</RouterLink>
      <RouterLink to="/privacy">حریم خصوصی</RouterLink>
    </div>
    <div class="mb-2 flex justify-center">
      <TrustBadges compact />
    </div>
    <p class="text-center text-[10px] text-ink-muted">
      تمامی حقوق محفوظ است © {{ year }} جاب‌آزمون
    </p>
  </footer>
</template>

<script setup>
import TrustBadges from './TrustBadges.vue'

const year = new Intl.DateTimeFormat('fa-IR', { year: 'numeric' }).format(
  new Date()
)
</script>
