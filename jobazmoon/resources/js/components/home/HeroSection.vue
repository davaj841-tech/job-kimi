<template>
  <section class="relative overflow-hidden bg-desk-dark text-white">
    <div class="pointer-events-none absolute -left-24 top-10 h-72 w-72 rounded-full bg-desk-orange/20 blur-3xl" />
    <div class="pointer-events-none absolute -right-16 bottom-0 h-64 w-64 rounded-full bg-desk-blue/60 blur-3xl" />

    <div class="desk-container relative grid items-center gap-10 py-16 lg:grid-cols-2 lg:py-20">
      <div class="text-right">
        <p class="mb-3 text-sm font-semibold text-desk-orange">پلتفرم آمادگی آزمون استخدامی</p>
        <h1 class="mb-4 text-4xl font-black leading-tight xl:text-5xl">
          جاب‌آزمون
        </h1>
        <p class="mb-8 max-w-xl text-base leading-8 text-white/80">
          آزمون آنلاین، آگهی‌های استخدام، فروشگاه فایل و رزومه‌ساز حرفه‌ای — همه در یک پلتفرم برای قبولی شما.
        </p>
        <div class="flex flex-row-reverse flex-wrap justify-end gap-3">
          <RouterLink
            v-for="cta in ctas"
            :key="cta.to"
            :to="cta.to"
            class="inline-flex items-center justify-center rounded-xl px-6 py-3 text-sm font-bold transition"
            :class="cta.primary
              ? 'bg-desk-orange text-white hover:bg-orange-500'
              : 'border border-white/30 bg-white/5 text-white hover:bg-white/10'"
          >
            {{ cta.label }}
          </RouterLink>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div
          v-for="card in featureCards"
          :key="card.title"
          class="rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur"
        >
          <div
            class="mb-3 flex h-11 w-11 items-center justify-center rounded-xl"
            :style="{ backgroundColor: card.tint }"
          >
            <DesktopIcon :name="card.icon" :size="22" class="text-white" />
          </div>
          <h3 class="mb-1 text-base font-semibold">{{ card.title }}</h3>
          <p class="text-sm leading-6 text-white/65">{{ card.text }}</p>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import DesktopIcon from '../DesktopIcon.vue';

const ctas = [
  { to: '/exams', label: 'شروع آزمون', primary: true },
  { to: '/jobs', label: 'مشاهده استخدام‌ها', primary: false },
  { to: '/resumes', label: 'ساخت رزومه', primary: false },
];

const featureCards = [
  { title: 'آزمون آنلاین', text: 'شبیه‌سازی واقعی با تحلیل نتیجه', icon: 'clipboard', tint: '#f97316' },
  { title: 'آگهی استخدام', text: 'جدیدترین فرصت‌های شغلی', icon: 'briefcase', tint: '#22c55e' },
  { title: 'فروشگاه فایل', text: 'جزوه و نمونه سوالات کاربردی', icon: 'file', tint: '#3b82f6' },
  { title: 'رزومه‌ساز', text: 'رزومه حرفه‌ای در چند دقیقه', icon: 'resume', tint: '#a855f7' },
];
</script>
