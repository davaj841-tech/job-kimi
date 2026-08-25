<template>
  <section class="bg-surface-page py-10 sm:py-14">
    <div class="mx-auto max-w-7xl px-4">
      <div class="mb-5 flex items-end justify-between gap-3 sm:mb-6">
        <div>
          <h2 class="text-xl font-black text-desk-dark sm:text-2xl">
            پرفروش‌ترین فایل‌ها
          </h2>
          <p class="mt-1 text-xs text-desk-muted sm:text-sm">
            جزوات و منابع آمادگی آزمون
          </p>
        </div>
        <RouterLink
          to="/pdfs"
          class="text-xs font-bold text-brand hover:underline sm:text-sm"
        >
          فروشگاه
        </RouterLink>
      </div>

      <div v-if="loading" class="py-10 text-center text-sm text-desk-muted">
        در حال بارگذاری...
      </div>
      <div
        v-else
        class="scrollbar-hide -mx-4 flex gap-3 overflow-x-auto px-4 pb-1 sm:mx-0 sm:grid sm:grid-cols-2 sm:gap-4 sm:overflow-visible sm:px-0 lg:grid-cols-4"
      >
        <RouterLink
          v-for="file in displayFiles"
          :key="file.id"
          :to="`/pdfs/${file.id}`"
          class="group w-[9.5rem] shrink-0 sm:w-auto"
        >
          <article
            class="overflow-hidden rounded-2xl border border-surface-line bg-white shadow-sm transition group-hover:-translate-y-0.5 group-hover:shadow-md"
          >
            <div
              class="relative aspect-[3/4] overflow-hidden"
              :class="file.tint"
            >
              <img
                v-if="file.thumbnail_url"
                :src="file.thumbnail_url"
                :alt="file.title"
                class="h-full w-full object-cover"
                loading="lazy"
              />
              <div v-else class="flex h-full items-center justify-center">
                <DesktopIcon name="file" :size="36" class="text-white/80" />
              </div>
            </div>
            <div class="p-3 text-right">
              <h3
                class="line-clamp-2 text-xs font-semibold leading-5 text-desk-text sm:text-sm"
              >
                {{ file.title }}
              </h3>
              <p class="mt-2 text-xs font-bold text-emerald-600 sm:text-sm">
                {{ formatPrice(file.price) }}
              </p>
            </div>
          </article>
        </RouterLink>
      </div>

      <p
        v-if="!loading && !displayFiles.length"
        class="py-10 text-center text-sm text-desk-muted"
      >
        فایلی یافت نشد.
      </p>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { formatPrice } from '../../utils/format'
import DesktopIcon from '../DesktopIcon.vue'

const props = defineProps({
  files: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
})

const tints = ['bg-desk-dark', 'bg-desk-blue', 'bg-[#9a3412]', 'bg-[#166534]']

const displayFiles = computed(() =>
  (props.files || []).slice(0, 4).map((file, i) => ({
    ...file,
    tint: tints[i % tints.length],
  }))
)
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}
.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
