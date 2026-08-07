<template>
  <div class="mx-auto max-w-4xl px-4 py-8 lg:py-12">
    <h1 class="mb-3 text-2xl font-black text-ink lg:text-3xl">درباره جاب‌آزمون</h1>
    <p class="mb-10 max-w-2xl text-sm leading-7 text-ink/80">
      جاب‌آزمون پلتفرم آمادگی آزمون‌های استخدامی است؛ از تمرین آزمون و تحلیل نتیجه تا رزومه‌ساز و آگهی‌های شغلی، همه در یکجا.
    </p>

    <section class="mb-12">
      <h2 class="mb-3 text-lg font-bold">مأموریت و چشم‌انداز</h2>
      <p class="text-sm leading-7 text-ink/80">
        مأموریت ما ساده‌سازی مسیر آمادگی شغلی برای کارجویان ایرانی است. چشم‌اندازمان این است که هر متقاضی با ابزارهای شفاف، تمرین هدفمند و محتوای به‌روز، شانس موفقیت خود را افزایش دهد.
      </p>
    </section>

    <section class="mb-12">
      <h2 class="mb-4 text-lg font-bold">تیم</h2>
      <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        <div v-for="m in team" :key="m.name" class="rounded-2xl border border-surface-line bg-white p-4 text-center">
          <div class="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-brand-soft text-xl font-black text-brand">
            {{ m.initials }}
          </div>
          <p class="text-sm font-bold">{{ m.name }}</p>
          <p class="text-xs text-ink-muted">{{ m.role }}</p>
        </div>
      </div>
    </section>

    <section class="mb-12 grid grid-cols-3 gap-3">
      <div v-for="s in stats" :key="s.label" class="rounded-2xl bg-[#0a1c33] p-4 text-center text-white">
        <p class="text-2xl font-black text-desk-orange">{{ s.value }}</p>
        <p class="mt-1 text-xs text-white/70">{{ s.label }}</p>
      </div>
    </section>

    <section class="mb-12">
      <h2 class="mb-3 text-lg font-bold">چرا جاب‌آزمون؟</h2>
      <ul class="space-y-2 text-sm leading-7 text-ink/80">
        <li>• آزمون‌های شبیه‌سازی‌شده با کارنامه و نمره منفی</li>
        <li>• رزومه‌ساز حرفه‌ای با قالب‌های قابل دانلود</li>
        <li>• فروشگاه فایل PDF و اشتراک منعطف</li>
        <li>• آگهی‌های استخدامی و محتوای آموزشی</li>
      </ul>
    </section>

    <section>
      <h2 class="mb-3 text-lg font-bold">اعتماد</h2>
      <div class="flex flex-wrap gap-3">
        <div class="flex h-20 w-32 items-center justify-center rounded-xl border border-dashed border-surface-line bg-slate-50 text-xs text-ink-muted">نماد اعتماد</div>
        <div class="flex h-20 w-32 items-center justify-center rounded-xl border border-dashed border-surface-line bg-slate-50 text-xs text-ink-muted">ساماندهی</div>
        <div class="flex h-20 w-32 items-center justify-center rounded-xl border border-dashed border-surface-line bg-slate-50 text-xs text-ink-muted">SSL Secure</div>
      </div>
    </section>
  </div>
</template>

<script setup>
const team = [
  { name: 'علی رضایی', role: 'مدیر محصول', initials: 'ع' },
  { name: 'سارا محمدی', role: 'طراحی تجربه', initials: 'س' },
  { name: 'نیما کریمی', role: 'توسعه بک‌اند', initials: 'ن' },
  { name: 'مریم احمدی', role: 'محتوا و آزمون', initials: 'م' },
];

const stats = [
  { value: '+۱۰ هزار', label: 'کاربر' },
  { value: '+۵۰۰', label: 'آزمون' },
  { value: '+۲ هزار', label: 'آگهی' },
];
</script>
