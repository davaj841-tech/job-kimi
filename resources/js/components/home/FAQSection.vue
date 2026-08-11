<template>
  <section :class="embedded ? 'h-full' : 'bg-surface-page py-6 sm:py-8'">
    <div :class="embedded ? 'h-full' : 'mx-auto max-w-3xl px-4'">
      <div :class="embedded ? 'mb-2' : 'mb-4 text-center'">
        <h2 class="text-base font-black text-desk-dark sm:text-lg">سوالات متداول</h2>
        <p class="mt-0.5 text-[11px] text-desk-muted">پاسخ کوتاه به پرسش‌های پرتکرار</p>
      </div>

      <div class="divide-y divide-surface-line overflow-hidden rounded-2xl border border-surface-line bg-white">
        <button
          v-for="(item, index) in faqs"
          :key="item.q"
          type="button"
          class="w-full px-4 py-2 text-right transition hover:bg-surface-page"
          @click="toggle(index)"
        >
          <div class="flex items-start justify-between gap-3">
            <h3 class="text-sm font-bold leading-6 text-desk-text">
              {{ item.q }}
            </h3>
            <span
              class="mt-0.5 shrink-0 text-desk-muted transition"
              :class="openIndex === index ? 'rotate-180 text-brand' : ''"
              aria-hidden="true"
              >▾</span
            >
          </div>
          <p
            v-show="openIndex === index"
            class="mt-1.5 pl-6 text-xs leading-6 text-desk-muted"
          >
            {{ item.a }}
          </p>
        </button>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref } from 'vue'

defineProps({
  embedded: { type: Boolean, default: false },
})

const openIndex = ref(0)

const faqs = [
  {
    q: 'چگونه در آزمون آنلاین شرکت کنم؟',
    a: 'وارد حساب شوید، آزمون را انتخاب کنید و پس از شروع، پاسخ‌ها به‌صورت خودکار ذخیره می‌شوند.',
  },
  {
    q: 'رزومه‌ساز چگونه کار می‌کند؟',
    a: 'اطلاعات را وارد می‌کنید، قالب را انتخاب می‌کنید و خروجی PDF استاندارد دریافت می‌کنید.',
  },
  {
    q: 'دانلود فایل‌های PDF چگونه است؟',
    a: 'پس از خرید از فروشگاه، فایل از بخش خریدهای من قابل دانلود است.',
  },
]

function toggle(index) {
  openIndex.value = openIndex.value === index ? -1 : index
}
</script>
