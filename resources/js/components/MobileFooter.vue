<template>
  <footer
    class="site-footer mt-0 border-t border-white/10 px-4 pt-3 text-white lg:hidden"
    style="
      background: var(--theme-ink);
      padding-bottom: calc(0.75rem + 3.5rem + env(safe-area-inset-bottom));
    "
  >
    <p class="mb-2 text-center text-sm font-black">جاب‌آزمون</p>
    <div
      class="mb-2.5 flex flex-wrap justify-center gap-x-3 gap-y-1 text-sm text-white/70"
    >
      <RouterLink to="/articles">مقالات</RouterLink>
      <RouterLink to="/about">درباره ما</RouterLink>
      <RouterLink to="/contact">تماس</RouterLink>
      <RouterLink to="/terms">قوانین</RouterLink>
      <RouterLink to="/privacy">حریم خصوصی</RouterLink>
    </div>
    <div class="mb-3 flex justify-center">
      <SocialLinks />
    </div>
    <div class="mb-2 flex justify-center">
      <TrustBadges dark compact />
    </div>
    <p class="text-center text-xs text-white/50">
      تمامی حقوق محفوظ است © {{ year }} جاب‌آزمون
    </p>
  </footer>
</template>

<script setup>
import SocialLinks from './SocialLinks.vue'
import TrustBadges from './TrustBadges.vue'

const year = new Intl.DateTimeFormat('fa-IR', { year: 'numeric' }).format(
  new Date()
)
</script>
