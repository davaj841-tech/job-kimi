<template>
  <section :class="embedded ? '' : 'bg-surface-page py-6 sm:py-8'">
    <div :class="embedded ? '' : 'mx-auto max-w-3xl px-4'">
      <div
        class="flex flex-col overflow-hidden rounded-2xl border border-surface-line bg-white p-4 dark:border-slate-800 dark:bg-slate-900 md:h-full"
      >
        <div class="mb-3">
          <h2
            class="text-base font-black text-desk-text dark:text-white sm:text-lg"
          >
            دانلود اپلیکیشن اندروید
          </h2>
          <p class="mt-0.5 text-[11px] text-desk-muted">
            آزمون، آگهی و رزومه همیشه همراهتان
          </p>
        </div>

        <div class="grid flex-1 grid-cols-1 gap-2 sm:grid-cols-3">
          <a
            v-for="item in stores"
            :key="item.key"
            :href="item.href || undefined"
            :target="item.href && item.external ? '_blank' : undefined"
            :rel="
              item.href && item.external ? 'noopener noreferrer' : undefined
            "
            class="flex items-center gap-3 rounded-xl border border-surface-line bg-surface-page px-3 py-2.5 transition hover:border-brand/40 hover:bg-brand-soft/40 dark:border-slate-700 dark:bg-slate-800"
            :class="item.href ? '' : 'pointer-events-none opacity-50'"
          >
            <span
              class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white shadow-sm dark:bg-slate-900"
              aria-hidden="true"
            >
              <img
                v-if="item.img"
                :src="item.img"
                :alt="item.title"
                class="h-8 w-8 object-contain"
              />
              <span v-else v-html="item.icon" />
            </span>
            <span class="min-w-0 text-right">
              <span class="block text-[11px] text-desk-muted">{{
                item.kicker
              }}</span>
              <span
                class="block text-xs font-black text-desk-text dark:text-white"
              >
                {{ item.title }}
              </span>
            </span>
          </a>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useSiteTheme } from '../../composables/useSiteTheme'

defineProps({
  embedded: { type: Boolean, default: false },
})

const { androidPlayUrl, androidBazaarUrl, androidDirectUrl, ensureLoaded } =
  useSiteTheme()

onMounted(() => {
  void ensureLoaded()
})

const playIcon = `<svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true"><path fill="#EA4335" d="M3.6 2.2 14.2 12 3.6 21.8z"/><path fill="#FBBC04" d="M3.6 21.8 14.2 12l4.3 4-12.2 7z"/><path fill="#4285F4" d="M20.4 10.1 18.5 9 14.2 12l4.3 4 1.9-1.1c1.1-.6 1.1-2.2 0-2.8z"/><path fill="#34A853" d="M3.6 2.2 16 9.3l-1.8 1.7L3.6 2.2z"/></svg>`

const androidIcon = `<svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true"><path fill="#3DDC84" d="M17.6 9.2H6.4c-.4 0-.7.3-.7.7v7.6c0 .9.7 1.6 1.6 1.6h.5V21c0 .4.3.7.7.7s.7-.3.7-.7v-1.9h5.6V21c0 .4.3.7.7.7s.7-.3.7-.7v-1.9h.5c.9 0 1.6-.7 1.6-1.6V9.9c0-.4-.3-.7-.7-.7Z"/><circle cx="9" cy="13.1" r=".9" fill="#fff"/><circle cx="15" cy="13.1" r=".9" fill="#fff"/><path fill="#3DDC84" d="M8.2 7.4 7 5.2a.4.4 0 1 1 .7-.4l1.2 2.1c.9-.4 1.9-.6 3.1-.6s2.2.2 3.1.6l1.2-2.1a.4.4 0 1 1 .7.4L15.8 7.4A6.6 6.6 0 0 1 12 6.4c-1.4 0-2.7.4-3.8 1Z"/><path fill="#3DDC84" d="M5.2 11.2a.7.7 0 0 0-.7.7v4.2c0 .4.3.7.7.7s.7-.3.7-.7v-4.2c0-.4-.3-.7-.7-.7Zm13.6 0a.7.7 0 0 0-.7.7v4.2c0 .4.3.7.7.7s.7-.3.7-.7v-4.2c0-.4-.3-.7-.7-.7Z"/></svg>`

const stores = computed(() => [
  {
    key: 'play',
    kicker: 'گوگل‌پلی',
    title: 'دانلود از گوگل پلی',
    href: androidPlayUrl.value,
    external: true,
    icon: playIcon,
  },
  {
    key: 'bazaar',
    kicker: 'کافه‌بازار',
    title: 'دانلود از کافه بازار',
    href: androidBazaarUrl.value,
    external: true,
    img: '/icons/cafebazaar-icon.png',
  },
  {
    key: 'direct',
    kicker: 'فایل APK',
    title: 'دریافت مستقیم از سایت',
    href: androidDirectUrl.value,
    external: true,
    icon: androidIcon,
  },
])
</script>
