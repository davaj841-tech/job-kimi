<template>
  <footer
    class="site-footer mt-0 border-t border-white/10 px-4 pt-3 text-white lg:hidden"
    style="
      background: var(--theme-ink);
      padding-bottom: calc(0.75rem + 4rem + env(safe-area-inset-bottom));
    "
  >
    <p class="mb-3 text-center text-sm font-black">جاب‌آزمون</p>

    <details class="mb-2 rounded-xl border border-white/10 bg-white/5">
      <summary
        class="cursor-pointer list-none px-3 py-2.5 text-sm font-bold after:float-left after:content-['▾'] open:after:rotate-180"
      >
        لینک‌های مفید
      </summary>
      <div
        class="flex flex-col gap-2 border-t border-white/10 px-3 py-2 text-sm text-white/75"
      >
        <RouterLink to="/articles">مقالات</RouterLink>
        <RouterLink to="/about">درباره ما</RouterLink>
        <RouterLink to="/contact">تماس</RouterLink>
        <RouterLink to="/terms">قوانین</RouterLink>
        <RouterLink to="/privacy">حریم خصوصی</RouterLink>
      </div>
    </details>

    <details class="mb-3 rounded-xl border border-white/10 bg-white/5">
      <summary
        class="cursor-pointer list-none px-3 py-2.5 text-sm font-bold after:float-left after:content-['▾'] open:after:rotate-180"
      >
        شبکه‌های اجتماعی
      </summary>
      <div class="border-t border-white/10 px-3 py-2">
        <SocialLinks />
      </div>
    </details>

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

<style scoped>
details > summary::-webkit-details-marker {
  display: none;
}
</style>
