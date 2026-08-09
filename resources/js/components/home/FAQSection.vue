<template>
  <section class="bg-white py-14">
    <div class="desk-container">
      <div class="mb-8 text-center">
        <h2 class="desk-section-title">سوالات متداول</h2>
        <p class="mt-2 text-sm text-desk-muted">پاسخ سریع به سوالات پرتکرار کاربران</p>
      </div>

      <div class="grid grid-cols-4 gap-4">
        <button
          v-for="(item, index) in faqs"
          :key="item.q"
          type="button"
          class="desk-card p-5 text-right transition"
          :class="openIndex === index ? 'ring-2 ring-desk-orange' : ''"
          @click="toggle(index)"
        >
          <div class="mb-3 flex items-start justify-between gap-3">
            <DesktopIcon
              name="chevron"
              :size="18"
              class="mt-1 shrink-0 text-desk-muted transition"
              :class="openIndex === index ? 'rotate-180 text-desk-orange' : ''"
            />
            <h3 class="text-base font-semibold leading-7 text-desk-text">{{ item.q }}</h3>
          </div>
          <p
            v-show="openIndex === index"
            class="text-sm leading-7 text-desk-muted"
          >
            {{ item.a }}
          </p>
        </button>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref } from 'vue';
import DesktopIcon from '../DesktopIcon.vue';

const openIndex = ref(0);

const faqs = [
  {
    q: 'رزومه ساز آنلاین چگونه کار می‌کند؟',
    a: 'اطلاعات خود را وارد می‌کنید، قالب را انتخاب می‌کنید و خروجی PDF استاندارد دریافت می‌کنید.',
  },
  {
    q: 'چگونه در آزمون‌های آنلاین شرکت کنم؟',
    a: 'وارد حساب شوید، آزمون موردنظر را انتخاب کنید و پس از شروع، پاسخ‌ها به‌صورت خودکار ذخیره می‌شوند.',
  },
  {
    q: 'دانلود فایل‌ها چگونه انجام می‌شود؟',
    a: 'پس از خرید از فروشگاه فایل، فایل از بخش خریدهای من قابل دانلود است.',
  },
  {
    q: 'نحوه خرید اشتراک چگونه کار می‌کند؟',
    a: 'پلن را انتخاب کنید و از طریق کیف پول یا درگاه زرین‌پال پرداخت را انجام دهید.',
  },
];

function toggle(index) {
  openIndex.value = openIndex.value === index ? -1 : index;
}
</script>
