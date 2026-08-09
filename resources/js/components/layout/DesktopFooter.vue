<template>
  <footer class="mt-auto hidden bg-[#0a1c33] text-white lg:block">
    <div class="desk-container grid grid-cols-4 gap-8 py-12">
      <div>
        <p class="mb-3 text-xl font-black">جاب‌آزمون</p>
        <p class="mb-4 text-sm leading-7 text-white/70">
          پلتفرم آمادگی آزمون‌های استخدامی، رزومه‌ساز آنلاین و دسترسی به آگهی‌های شغلی.
        </p>
        <TrustBadges dark />
      </div>

      <div v-for="col in columns" :key="col.title">
        <h3 class="mb-4 text-base font-bold">{{ col.title }}</h3>
        <ul class="space-y-2.5">
          <li v-for="link in col.links" :key="link.to">
            <RouterLink :to="link.to" class="text-sm text-white/70 transition hover:text-desk-orange">
              {{ link.label }}
            </RouterLink>
          </li>
        </ul>
      </div>
    </div>

    <div class="border-t border-white/10">
      <div class="desk-container flex items-center justify-between py-4 text-xs text-white/55">
        <p>تمامی حقوق محفوظ است © {{ faYear }} جاب‌آزمون</p>
        <p>طراحی‌شده برای آمادگی آزمون‌های استخدامی</p>
      </div>
    </div>
  </footer>
</template>

<script setup>
import TrustBadges from '../TrustBadges.vue';

const faYear = new Intl.DateTimeFormat('fa-IR', { year: 'numeric' }).format(new Date());

const columns = [
  {
    title: 'خدمات ما',
    links: [
      { to: '/exams', label: 'آزمون‌های آنلاین' },
      { to: '/resumes', label: 'رزومه‌ساز' },
      { to: '/pdfs', label: 'فروشگاه فایل' },
      { to: '/blog', label: 'وبلاگ' },
    ],
  },
  {
    title: 'دسترسی سریع',
    links: [
      { to: '/', label: 'صفحه اصلی' },
      { to: '/jobs', label: 'استخدام‌ها' },
      { to: '/contact', label: 'تماس با ما' },
      { to: '/about', label: 'درباره ما' },
    ],
  },
  {
    title: 'قوانین',
    links: [
      { to: '/terms', label: 'قوانین و مقررات' },
      { to: '/privacy', label: 'حریم خصوصی' },
      { to: '/about', label: 'درباره ما' },
      { to: '/contact', label: 'تماس با ما' },
    ],
  },
];
</script>
