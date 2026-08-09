<template>
  <section class="bg-white py-14">
    <div class="desk-container">
      <div class="mb-8 text-center">
        <h2 class="desk-section-title">نظرات کاربران</h2>
        <p class="mt-2 text-sm text-desk-muted">تجربه واقعی داوطلبان آزمون‌های استخدامی</p>
      </div>

      <div class="grid grid-cols-3 gap-5">
        <article
          v-for="item in items"
          :key="item.id"
          class="desk-card p-6 text-right"
        >
          <DesktopIcon name="quote" :size="22" class="mb-4 text-desk-orange" />
          <p class="mb-5 text-sm leading-7 text-desk-text">{{ item.text }}</p>
          <div class="flex items-center justify-end gap-3">
            <div>
              <p class="text-sm font-bold text-desk-text">{{ item.name }}</p>
              <p class="text-xs text-desk-muted">{{ item.role }}</p>
            </div>
            <div
              class="flex h-11 w-11 items-center justify-center rounded-full text-sm font-bold text-white"
              :class="item.bg"
            >
              {{ item.initials }}
            </div>
          </div>
        </article>
      </div>
    </div>
  </section>
</template>

<script setup>
const items = [
  {
    id: 1,
    name: 'سارا محمدی',
    role: 'قبول‌شده آموزش و پرورش',
    initials: 'س',
    bg: 'bg-desk-orange',
    text: 'با آزمون‌های شبیه‌سازی جاب‌آزمون دقیقاً نقاط ضعفتم مشخص شد و تونستم روی همون‌ها تمرکز کنم.',
  },
  {
    id: 2,
    name: 'علی رضایی',
    role: 'داوطلب آزمون بانک',
    initials: 'ع',
    bg: 'bg-desk-blue',
    text: 'رزومه‌ساز خیلی سریع و تمیز بود. خروجی PDF رو مستقیم برای آگهی‌ها ارسال کردم.',
  },
  {
    id: 3,
    name: 'مریم کریمی',
    role: 'کاربر اشتراک حرفه‌ای',
    initials: 'م',
    bg: 'bg-desk-green',
    text: 'فروشگاه فایل و تحلیل پاسخبرگ باعث شد وقت کمتری برای پیدا کردن منابع هدر بدم.',
  },
];
</script>
