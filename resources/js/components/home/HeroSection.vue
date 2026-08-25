<template>
  <section
    class="hero-bleed relative isolate min-h-[min(88dvh,720px)] overflow-hidden"
  >
    <!-- Full-bleed visual plane -->
    <div class="absolute inset-0" aria-hidden="true">
      <div class="absolute inset-0 bg-desk-dark" />
      <div
        class="absolute inset-0 opacity-90"
        style="
          background:
            radial-gradient(
              ellipse 80% 60% at 15% 20%,
              rgba(239, 57, 78, 0.28),
              transparent 55%
            ),
            radial-gradient(
              ellipse 70% 50% at 90% 80%,
              rgba(249, 115, 22, 0.18),
              transparent 50%
            ),
            linear-gradient(165deg, #0f2744 0%, #163556 42%, #0c1d33 100%);
        "
      />
      <!-- Soft paper/desk texture hint -->
      <div
        class="absolute inset-0 opacity-[0.12]"
        style="
          background-image:
            linear-gradient(rgba(255, 255, 255, 0.06) 1px, transparent 1px),
            linear-gradient(
              90deg,
              rgba(255, 255, 255, 0.06) 1px,
              transparent 1px
            );
          background-size: 56px 56px;
          mask-image: radial-gradient(
            ellipse at 60% 40%,
            black 20%,
            transparent 75%
          );
        "
      />
      <!-- Dominant brand watermark -->
      <p
        class="hero-mark pointer-events-none absolute -left-4 bottom-[-0.12em] select-none font-display font-black leading-none text-white/[0.06] sm:-left-8"
        style="font-size: clamp(6rem, 28vw, 18rem)"
      >
        JA
      </p>
    </div>

    <div
      class="relative z-10 mx-auto flex min-h-[min(88dvh,720px)] w-full max-w-7xl flex-col justify-end px-4 pb-12 pt-10 sm:px-6 sm:pb-16 lg:justify-center lg:pb-20 lg:pt-16"
    >
      <div class="hero-copy max-w-xl space-y-5 text-right lg:max-w-2xl">
        <p
          class="font-display font-black tracking-tight text-white"
          style="font-size: clamp(2.75rem, 9vw, 4.75rem); line-height: 1.05"
        >
          جاب‌آزمون
        </p>

        <h1
          class="text-xl font-bold leading-snug text-white/95 sm:text-2xl lg:text-3xl"
        >
          آمادگی امروز، استخدام فردا
        </h1>

        <p
          class="max-w-md text-sm leading-7 text-white/70 sm:text-base sm:leading-8"
        >
          آزمون شبیه‌سازی، آگهی استخدام، منابع PDF و رزومه‌ساز — همه‌چیز در یک
          مسیر ساده و قابل اعتماد.
        </p>

        <div class="flex flex-col gap-2.5 pt-1 sm:flex-row sm:items-center">
          <RouterLink
            to="/exams"
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand px-7 py-3.5 text-sm font-bold text-white transition hover:bg-brand-dark active:scale-[0.98]"
          >
            شروع آزمون
            <ArrowLeftIcon class="h-4 w-4" />
          </RouterLink>
          <RouterLink
            to="/jobs"
            class="inline-flex items-center justify-center rounded-xl border border-white/25 bg-white/5 px-7 py-3.5 text-sm font-bold text-white backdrop-blur-sm transition hover:bg-white/10 active:scale-[0.98]"
          >
            مشاهده استخدام‌ها
          </RouterLink>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { ArrowLeftIcon } from '@heroicons/vue/24/outline'
import gsap from 'gsap'

onMounted(() => {
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  if (reduce) return
  gsap.from('.hero-copy > *', {
    y: 28,
    opacity: 0,
    duration: 0.7,
    stagger: 0.1,
    ease: 'power3.out',
  })
  gsap.from('.hero-mark', {
    opacity: 0,
    scale: 1.06,
    duration: 1.2,
    ease: 'power2.out',
  })
})
</script>
