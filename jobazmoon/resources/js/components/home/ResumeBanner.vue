<template>
  <section class="py-6">
    <div class="desk-container">
      <div class="overflow-hidden rounded-2xl bg-gradient-to-l from-desk-dark via-desk-blue to-[#123556] text-white">
        <div class="grid items-center gap-8 px-8 py-10 lg:grid-cols-2 lg:px-12">
          <div class="text-right">
            <p class="mb-2 text-sm font-semibold text-desk-orange">رزومه‌ساز آنلاین</p>
            <h2 class="mb-3 text-2xl font-black md:text-3xl">رزومه حرفه‌ای بسازید و در آزمون‌ها بدرخشید</h2>
            <p class="mb-6 max-w-lg text-sm leading-7 text-white/75">
              با قالب‌های استاندارد، خروجی PDF و پیشنهادهای هوشمند، رزومه‌ای آماده ارسال برای فرصت‌های استخدامی بسازید.
            </p>
            <RouterLink
              to="/resumes"
              class="inline-flex items-center gap-2 rounded-xl bg-desk-orange px-6 py-3 text-sm font-bold text-white transition hover:bg-orange-500"
            >
              شروع ساخت رزومه
              <DesktopIcon name="arrow" :size="16" />
            </RouterLink>
          </div>
          <div class="flex justify-center">
            <div class="flex h-44 w-full max-w-sm items-center justify-center rounded-2xl border border-white/15 bg-white/10">
              <DesktopIcon name="resume" :size="64" class="text-white/90" />
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import DesktopIcon from '../DesktopIcon.vue';
</script>
