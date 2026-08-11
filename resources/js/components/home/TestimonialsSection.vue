<template>
  <section class="relative overflow-hidden bg-surface-page py-12 sm:py-16">
    <div class="relative mx-auto max-w-7xl px-4">
      <div class="animate-on-scroll mb-8 text-center sm:mb-10">
        <h2 class="text-xl font-black text-desk-dark sm:text-2xl">کاربران چه می‌گویند؟</h2>
        <p class="mt-1 text-sm text-desk-muted">تجربه واقعی داوطلبان استخدامی</p>
      </div>

      <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 md:gap-5">
        <article
          v-for="item in items"
          :key="item.id"
          class="animate-on-scroll rounded-2xl border border-surface-line bg-white p-5 shadow-sm"
        >
          <div class="mb-3 flex gap-0.5 text-amber-400">
            <span
              v-for="n in 5"
              :key="n"
              >★</span
            >
          </div>
          <p class="mb-5 text-sm leading-7 text-desk-muted">{{ item.text }}</p>
          <div class="flex items-center gap-3">
            <div
              class="flex h-10 w-10 items-center justify-center rounded-full text-xs font-bold text-white"
              :class="item.bg"
            >
              {{ item.initials }}
            </div>
            <div>
              <p class="text-sm font-bold text-desk-text">{{ item.name }}</p>
              <p class="text-xs text-desk-muted">{{ item.role }}</p>
            </div>
          </div>
        </article>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
const items = [
  {
    id: 1,
    text: 'با آزمون‌های شبیه‌سازی جاب‌آزمون برای مصاحبه بانک آماده شدم و حس اعتماد بیشتری داشتم.',
    name: 'سارا م.',
    role: 'قبول‌شده بانک',
    initials: 'س',
    bg: 'bg-brand',
  },
  {
    id: 2,
    text: 'رزومه‌ساز و منابع PDF دقیقاً همان چیزی بود که برای شروع مسیر استخدامی لازم داشتم.',
    name: 'امیر ر.',
    role: 'داوطلب آموزش‌وپرورش',
    initials: 'ا',
    bg: 'bg-desk-dark',
  },
  {
    id: 3,
    text: 'رابط ساده و جمع‌وجور موبایل باعث شد هر روز تمرین کنم بدون سردرگمی.',
    name: 'مینا ک.',
    role: 'کاربر فعال',
    initials: 'م',
    bg: 'bg-desk-orange',
  },
]
</script>
