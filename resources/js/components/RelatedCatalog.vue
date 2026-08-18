<template>
  <section
    v-if="exams.length || pdfs.length"
    class="mb-5 grid grid-cols-1 gap-4 md:grid-cols-2"
  >
    <div>
      <h2 class="mb-2 text-sm font-black text-desk-text">آزمون‌های مرتبط</h2>
      <div class="space-y-2">
        <RouterLink
          v-for="exam in exams"
          :key="'ex-' + exam.id"
          :to="`/exams/${exam.slug || exam.id}`"
          class="card-soft flex items-center justify-between border border-surface-line p-3 text-sm"
        >
          <span class="truncate font-bold text-desk-text">{{ exam.title }}</span>
          <span class="mr-2 shrink-0 text-xs text-desk-muted">{{
            exam.is_free ? 'رایگان' : formatPrice(exam.price)
          }}</span>
        </RouterLink>
        <p v-if="!exams.length" class="text-xs text-desk-muted">آزمونی نیست.</p>
      </div>
    </div>
    <div>
      <h2 class="mb-2 text-sm font-black text-desk-text">فایل‌های PDF</h2>
      <div class="space-y-2">
        <RouterLink
          v-for="pdf in pdfs"
          :key="'pdf-' + pdf.id"
          :to="`/pdfs/${pdf.id}`"
          class="card-soft flex items-center justify-between border border-surface-line p-3 text-sm"
        >
          <span class="truncate font-bold text-desk-text">{{ pdf.title }}</span>
          <span class="mr-2 shrink-0 text-xs text-desk-muted">{{
            formatPrice(pdf.price)
          }}</span>
        </RouterLink>
        <p v-if="!pdfs.length" class="text-xs text-desk-muted">فایلی نیست.</p>
      </div>
    </div>
  </section>
</template>

<script setup>
import { formatPrice } from '../utils/format'

defineProps({
  exams: { type: Array, default: () => [] },
  pdfs: { type: Array, default: () => [] },
})
</script>
