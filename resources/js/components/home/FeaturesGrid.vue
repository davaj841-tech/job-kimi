<template>
  <section class="border-b border-surface-line bg-white py-7 sm:py-9">
    <div class="mx-auto max-w-7xl px-4">
      <div class="animate-on-scroll mb-5 sm:mb-6">
        <h2 class="text-lg font-black text-desk-dark sm:text-xl">
          از کجا شروع کنیم؟
        </h2>
        <p class="mt-1 text-xs text-desk-muted sm:text-sm">
          چهار مسیر اصلی آمادگی استخدام در جاب‌آزمون
        </p>
      </div>

      <div
        class="grid grid-cols-2 gap-px overflow-hidden rounded-2xl bg-surface-line sm:grid-cols-4"
      >
        <RouterLink
          v-for="item in items"
          :key="item.to"
          :to="item.to"
          class="animate-on-scroll group flex flex-col gap-2 bg-white px-4 py-5 transition hover:bg-surface-page sm:px-5 sm:py-6"
        >
          <span
            class="flex h-9 w-9 items-center justify-center rounded-lg bg-desk-dark/[0.06] text-desk-dark transition group-hover:bg-brand-soft group-hover:text-brand"
          >
            <component :is="item.icon" class="h-5 w-5" />
          </span>
          <span class="text-sm font-bold text-desk-text">{{ item.label }}</span>
          <span
            class="text-[11px] leading-5 text-desk-muted sm:text-xs sm:leading-6"
            >{{ item.desc }}</span
          >
        </RouterLink>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import {
  AcademicCapIcon,
  BriefcaseIcon,
  DocumentTextIcon,
  SparklesIcon,
} from '@heroicons/vue/24/outline'

const items = [
  {
    to: '/exams',
    label: 'آزمون‌ها',
    desc: 'شبیه‌سازی واقعی و تحلیل نتیجه',
    icon: AcademicCapIcon,
  },
  {
    to: '/jobs',
    label: 'استخدام‌ها',
    desc: 'آگهی‌های تأییدشده و به‌روز',
    icon: BriefcaseIcon,
  },
  {
    to: '/pdfs',
    label: 'منابع PDF',
    desc: 'فایل‌های آموزشی هدفمند',
    icon: DocumentTextIcon,
  },
  {
    to: '/resumes',
    label: 'رزومه‌ساز',
    desc: 'ساخت رزومه استاندارد PDF',
    icon: SparklesIcon,
  },
]
</script>
