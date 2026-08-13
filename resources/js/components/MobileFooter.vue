<template>
  <footer class="site-footer border-t border-white/10 bg-desk-dark px-4 py-4 text-white lg:hidden">
    <p class="mb-2 text-center text-xs font-black">جاب‌آزمون</p>
    <div
      class="mb-2.5 flex flex-wrap justify-center gap-x-3 gap-y-1 text-[11px] text-white/65"
    >
      <RouterLink to="/articles">مقالات</RouterLink>
      <RouterLink to="/about">درباره ما</RouterLink>
      <RouterLink to="/contact">تماس</RouterLink>
      <RouterLink to="/terms">قوانین</RouterLink>
      <RouterLink to="/privacy">حریم خصوصی</RouterLink>
    </div>
    <div class="mb-2 flex justify-center">
      <TrustBadges
        dark
        compact
      />
    </div>
    <p class="text-center text-[10px] text-white/50">
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
