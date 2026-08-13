<template>
  <section class="bg-surface-page py-6 sm:py-7">
    <div class="mx-auto max-w-7xl px-4">
      <div class="mb-3 flex items-end justify-between gap-3">
        <div>
          <h2 class="text-lg font-black text-desk-text sm:text-xl">📄 فروشگاه فایل</h2>
          <p class="mt-0.5 text-xs text-desk-muted">جزوه‌ها و فایل‌های PDF آمادگی آزمون</p>
        </div>
        <RouterLink
          to="/pdfs"
          class="text-xs font-bold text-brand hover:underline sm:text-sm"
        >
          مشاهده همه
        </RouterLink>
      </div>

      <div
        v-if="loading"
        class="py-8 text-center text-sm text-desk-muted"
      >
        در حال بارگذاری...
      </div>
      <HomeRail v-else-if="cards.length">
        <RouterLink
          v-for="file in cards"
          :key="file.id"
          :to="`/pdfs/${file.id}`"
          class="home-rail-card"
        >
          <div class="mb-2.5 flex items-start justify-between gap-2">
            <span
              v-if="file.is_new"
              class="rounded-md bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-600"
              >جدید</span
            >
            <span
              v-else
              class="rounded-md bg-red-50 px-1.5 py-0.5 text-[10px] font-bold text-red-600"
              >PDF</span
            >
            <span
              class="flex h-10 w-10 items-center justify-center rounded-xl bg-desk-dark text-lg"
              aria-hidden="true"
            >
              📄
            </span>
          </div>
          <p class="line-clamp-2 flex-1 text-sm font-bold text-desk-text">{{ file.title }}</p>
          <p class="mt-1 text-[11px] font-bold text-desk-orange">
            {{ formatPrice(file.price) }}
          </p>
        </RouterLink>
      </HomeRail>
      <p
        v-else
        class="py-8 text-center text-sm text-desk-muted"
      >
        فایلی یافت نشد.
      </p>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { formatPrice } from '../../utils/format'
import HomeRail from './HomeRail.vue'

const props = defineProps({
  files: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
})

const cards = computed(() => (props.files || []).slice(0, 12))
</script>
