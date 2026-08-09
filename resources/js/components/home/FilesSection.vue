<template>
  <section class="py-14">
    <div class="desk-container">
      <div class="mb-6 flex items-center justify-between">
        <div>
          <h2 class="desk-section-title">فروشگاه فایل</h2>
          <p class="mt-1 text-sm text-desk-muted">جزوات، نمونه سوالات و فایل‌های آمادگی آزمون</p>
        </div>
        <RouterLink to="/pdfs" class="text-sm font-bold text-desk-orange hover:underline">
          فروشگاه
        </RouterLink>
      </div>

      <div v-if="loading" class="py-10 text-center text-sm text-desk-muted">در حال بارگذاری...</div>
      <div v-else class="grid grid-cols-4 gap-5">
        <article
          v-for="file in displayFiles"
          :key="file.id"
          class="desk-card overflow-hidden"
        >
          <div
            class="flex aspect-video items-center justify-center"
            :class="file.tint"
          >
            <DesktopIcon name="file" :size="32" class="text-white" />
          </div>
          <div class="p-4 text-right">
            <h3 class="mb-2 line-clamp-2 text-base font-semibold text-desk-text">{{ file.title }}</h3>
            <p class="mb-3 text-sm font-bold text-desk-orange">{{ formatPrice(file.price) }}</p>
            <RouterLink
              :to="`/pdfs/${file.id}`"
              class="inline-flex w-full items-center justify-center rounded-lg border border-desk-dark/10 px-3 py-2.5 text-sm font-bold text-desk-dark hover:bg-desk-gray"
            >
              مشاهده
            </RouterLink>
          </div>
        </article>
      </div>

      <p v-if="!loading && !displayFiles.length" class="py-10 text-center text-sm text-desk-muted">
        فایلی یافت نشد.
      </p>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue';
import { formatPrice } from '../../utils/format';
import DesktopIcon from '../DesktopIcon.vue';

const props = defineProps({
  files: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const tints = ['bg-desk-dark', 'bg-desk-blue', 'bg-[#9a3412]', 'bg-[#166534]'];

const displayFiles = computed(() =>
  (props.files || []).slice(0, 4).map((file, i) => ({
    ...file,
    tint: tints[i % tints.length],
  }))
);
</script>
