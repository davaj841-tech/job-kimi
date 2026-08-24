<template>
  <div class="flex flex-wrap items-center gap-2" :class="wrapClass">
    <a
      v-for="item in items"
      :key="item.key"
      :href="item.href"
      target="_blank"
      rel="noopener noreferrer"
      class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-xl bg-white/10 transition hover:bg-white/20"
      :title="item.label"
      :aria-label="item.label"
    >
      <img
        v-if="item.img"
        :src="item.img"
        :alt="item.label"
        class="h-6 w-6 object-contain"
      />
      <span
        v-else
        class="flex h-5 w-5 items-center justify-center"
        aria-hidden="true"
        v-html="item.icon"
      />
    </a>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useSiteTheme } from '../composables/useSiteTheme'

defineProps({
  wrapClass: { type: String, default: '' },
})

const {
  instagramUrl,
  telegramUrl,
  whatsappUrl,
  rubikaUrl,
  baleUrl,
  androidPlayUrl,
  androidBazaarUrl,
  androidDirectUrl,
  ensureLoaded,
} = useSiteTheme()

onMounted(() => {
  void ensureLoaded()
})

const ig = `<svg viewBox="0 0 24 24" width="18" height="18" fill="none"><rect x="3" y="3" width="18" height="18" rx="5" stroke="#fff" stroke-width="1.8"/><circle cx="12" cy="12" r="4" stroke="#fff" stroke-width="1.8"/><circle cx="17.2" cy="6.8" r="1" fill="#fff"/></svg>`
const tg = `<svg viewBox="0 0 24 24" width="18" height="18" fill="#fff"><path d="M20.7 4.3 3.9 10.8c-1.1.4-1.1 1 .2 1.3l4.3 1.3 1.6 5.1c.2.6.3.8.8.8.4 0 .6-.2.8-.5l2.3-3.1 4.5 3.3c.8.5 1.4.2 1.6-.8l2.9-13.6c.3-1.2-.4-1.7-1.2-1.3Z"/></svg>`
const wa = `<svg viewBox="0 0 24 24" width="18" height="18"><rect width="24" height="24" rx="6" fill="#25D366"/><path fill="#fff" d="M12 5.2A6.8 6.8 0 0 0 6.1 15.4L5.2 18.8l3.5-.9A6.8 6.8 0 1 0 12 5.2Zm3.9 9.7c-.16.45-.9.83-1.24.88-.33.05-.76.1-2.27-.47-1.86-.75-3.07-2.6-3.16-2.72-.1-.13-.7-.9-.7-1.72s.42-1.22.57-1.39c.16-.16.35-.2.47-.2h.33c.1 0 .25 0 .38.36l.55 1.3c.05.14 0 .27-.08.36l-.26.32c-.1.13-.24.28-.1.5.13.25.62 1.03 1.37 1.66.9.75 1.66.98 1.9 1.1.24.12.38.1.52-.08l.42-.5c.12-.14.28-.12.47-.07l1.36.64c.2.1.33.14.37.28-.02.1-.02.55-.2 1.03Z"/></svg>`
const rubika = `<svg viewBox="0 0 24 24" width="18" height="18"><rect width="24" height="24" rx="6" fill="#7B2CBF"/><path fill="#fff" d="M8 7.2h5.2c2.2 0 3.6 1.2 3.6 3.1 0 1.5-.8 2.6-2.2 3l2.5 3.5h-2.3L12.5 13H9.9v3.8H8V7.2Zm1.9 4.4h3.1c1.1 0 1.7-.5 1.7-1.3s-.6-1.3-1.7-1.3H9.9v2.6Z"/></svg>`
const bale = `<svg viewBox="0 0 24 24" width="18" height="18"><rect width="24" height="24" rx="6" fill="#00ADEF"/><path fill="#fff" d="M12.1 5.5 6.4 16.3h2.3l1.1-2.3h4.5l1.1 2.3h2.3L12.1 5.5Zm0 3.3 1.4 3H10.7l1.4-3Z"/></svg>`
const play = `<svg viewBox="0 0 24 24" width="18" height="18"><path fill="#EA4335" d="M4 3.2 14.1 12 4 20.8z"/><path fill="#FBBC04" d="M4 20.8 14.1 12l3.8 3.5L6.2 21.7z"/><path fill="#4285F4" d="M19.3 10.2 17.5 9.2 14.1 12l3.8 3.5 1.4-.8c1-.6 1-2 0-2.5z"/><path fill="#34A853" d="M4 3.2 15.7 9.5 14.1 12 4 3.2z"/></svg>`
const apk = `<svg viewBox="0 0 24 24" width="18" height="18"><path fill="#3DDC84" d="M17.2 9.4H6.8c-.4 0-.6.3-.6.6v7c0 .8.6 1.4 1.4 1.4h.5V20c0 .3.3.6.6.6s.6-.3.6-.6v-1.6h5.4V20c0 .3.3.6.6.6s.6-.3.6-.6v-1.6h.5c.8 0 1.4-.6 1.4-1.4v-7c0-.3-.3-.6-.6-.6Z"/><circle cx="9.2" cy="13" r=".8" fill="#fff"/><circle cx="14.8" cy="13" r=".8" fill="#fff"/><path fill="#3DDC84" d="M8.5 7.8 7.5 6a.4.4 0 1 1 .7-.4l1 1.7A6 6 0 0 1 12 6.8c1.1 0 2.1.2 3 .5l1-1.7a.4.4 0 1 1 .7.4l-1 1.8A5.7 5.7 0 0 1 12 6.8c-1.3 0-2.5.4-3.5 1Z"/></svg>`

const items = computed(() =>
  [
    { key: 'ig', label: 'اینستاگرام', href: instagramUrl.value, icon: ig },
    { key: 'tg', label: 'تلگرام', href: telegramUrl.value, icon: tg },
    { key: 'wa', label: 'واتساپ', href: whatsappUrl.value, icon: wa },
    { key: 'ru', label: 'روبیکا', href: rubikaUrl.value, icon: rubika },
    { key: 'bale', label: 'بله', href: baleUrl.value, icon: bale },
    { key: 'play', label: 'گوگل پلی', href: androidPlayUrl.value, icon: play },
    {
      key: 'bazaar',
      label: 'کافه بازار',
      href: androidBazaarUrl.value,
      img: '/icons/cafebazaar-icon.png',
    },
    {
      key: 'apk',
      label: 'دانلود مستقیم',
      href: androidDirectUrl.value,
      icon: apk,
    },
  ].filter((x) => !!x.href)
)
</script>
