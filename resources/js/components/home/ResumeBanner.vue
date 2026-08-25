<template>
  <section :class="embedded ? 'h-full' : 'bg-white py-4'">
    <div :class="embedded ? 'h-full' : 'mx-auto max-w-7xl px-4'">
      <div
        class="relative flex h-full overflow-hidden rounded-2xl text-white shadow-lg shadow-desk-dark/10"
        :class="embedded ? 'min-h-[132px]' : 'min-h-[148px]'"
        style="
          background: linear-gradient(
            125deg,
            var(--theme-ink) 0%,
            var(--theme-ink-2) 58%,
            color-mix(in srgb, var(--theme-accent) 55%, var(--theme-ink)) 100%
          );
        "
      >
        <span
          class="pointer-events-none absolute -left-8 -top-10 h-32 w-32 rounded-full bg-white/10"
          aria-hidden="true"
        />
        <span
          class="bg-[var(--theme-accent)]/25 pointer-events-none absolute bottom-[-28px] right-10 h-24 w-24 rounded-full"
          aria-hidden="true"
        />

        <div
          class="relative z-10 flex flex-1 items-center gap-4 px-4 py-4 sm:gap-5 sm:px-6"
        >
          <div
            class="hidden h-[88px] w-[68px] shrink-0 rounded-lg bg-white p-1.5 shadow-xl sm:block"
            aria-hidden="true"
          >
            <div class="mb-1.5 h-1.5 w-8 rounded bg-desk-dark/15" />
            <div class="space-y-1">
              <div class="h-1 w-full rounded bg-slate-200" />
              <div class="h-1 w-5/6 rounded bg-slate-200" />
              <div class="h-1 w-4/6 rounded bg-slate-200" />
            </div>
            <div class="mt-2 grid grid-cols-2 gap-1">
              <div class="bg-[var(--theme-accent)]/30 h-4 rounded" />
              <div class="h-4 rounded bg-slate-100" />
            </div>
          </div>

          <div class="min-w-0 flex-1">
            <p class="text-[10px] font-bold text-desk-orange sm:text-[11px]">
              رزومه‌ساز آنلاین
            </p>
            <h2 class="mt-0.5 text-base font-black leading-snug sm:text-lg">
              رزومه حرفه‌ای، آماده ارسال
            </h2>
            <p class="mt-1 hidden text-[11px] leading-5 text-white/70 sm:block">
              قالب استاندارد و خروجی PDF برای فرصت‌های استخدامی.
            </p>
          </div>

          <RouterLink
            to="/resumes"
            class="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-white px-3.5 py-2 text-xs font-black text-desk-dark shadow-sm transition hover:bg-amber-50 sm:px-4 sm:text-sm"
          >
            ساخت رزومه
          </RouterLink>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
defineProps({
  embedded: { type: Boolean, default: false },
})
</script>
