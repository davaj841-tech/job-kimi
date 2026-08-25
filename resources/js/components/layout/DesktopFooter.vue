<template>
  <footer
    class="site-footer mt-auto hidden text-white lg:block"
    style="background: var(--theme-ink)"
  >
    <div class="desk-container grid grid-cols-4 gap-6 py-6 text-sm">
      <div>
        <p class="mb-1.5 text-lg font-black">جاب‌آزمون</p>
        <p class="mb-3 leading-7 text-white/70">
          آزمون استخدامی، رزومه‌ساز و آگهی شغلی در یک مسیر.
        </p>
        <TrustBadges dark compact />
        <SocialLinks wrap-class="mt-3" />
      </div>

      <div v-for="col in columns" :key="col.title">
        <h3 class="mb-2 font-bold">{{ col.title }}</h3>
        <ul class="space-y-1.5">
          <li v-for="link in col.links" :key="link.to + link.label">
            <RouterLink
              :to="link.to"
              class="text-white/70 transition hover:text-desk-orange"
            >
              {{ link.label }}
            </RouterLink>
          </li>
        </ul>
      </div>
    </div>

    <div class="border-t border-white/10">
      <div
        class="desk-container flex items-center justify-between py-2.5 text-xs text-white/50"
      >
        <p>تمامی حقوق محفوظ است © {{ faYear }} جاب‌آزمون</p>
        <p>آمادگی آزمون‌های استخدامی</p>
      </div>
    </div>
  </footer>
</template>

<script setup>
import SocialLinks from '../SocialLinks.vue'
import TrustBadges from '../TrustBadges.vue'

const faYear = new Intl.DateTimeFormat('fa-IR', { year: 'numeric' }).format(
  new Date()
)

const columns = [
  {
    title: 'خدمات',
    links: [
      { to: '/exams', label: 'آزمون‌ها' },
      { to: '/resumes', label: 'رزومه‌ساز' },
      { to: '/pdfs', label: 'فروشگاه' },
      { to: '/articles', label: 'مقالات' },
    ],
  },
  {
    title: 'دسترسی',
    links: [
      { to: '/', label: 'صفحه اصلی' },
      { to: '/jobs', label: 'استخدام‌ها' },
      { to: '/blog', label: 'وبلاگ' },
      { to: '/contact', label: 'تماس با ما' },
    ],
  },
  {
    title: 'قوانین',
    links: [
      { to: '/terms', label: 'قوانین و مقررات' },
      { to: '/privacy', label: 'حریم خصوصی' },
      { to: '/about', label: 'درباره ما' },
    ],
  },
]
</script>
