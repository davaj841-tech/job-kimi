<template>
  <article
    class="group cursor-pointer overflow-hidden rounded-xl border border-surface-line bg-surface transition hover:border-brand/30 hover:shadow-md"
    role="button"
    tabindex="0"
    @click="$emit('click')"
    @keydown.enter.prevent="$emit('click')"
  >
    <div class="flex gap-3 p-3">
      <div
        class="relative h-20 w-16 shrink-0 overflow-hidden rounded-lg bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-700 dark:to-slate-800"
      >
        <img
          v-if="cover"
          :src="cover"
          :alt="pdf.title"
          class="h-full w-full object-cover"
          loading="lazy"
        />
        <div
          v-else
          class="flex h-full items-center justify-center text-lg"
          aria-hidden="true"
        >
          📄
        </div>
        <span
          v-if="pdf.is_new"
          class="absolute left-1 top-1 rounded bg-emerald-500 px-1 py-0.5 text-[9px] font-bold text-white"
          >جدید</span
        >
      </div>

      <div class="min-w-0 flex-1">
        <h3
          class="line-clamp-2 text-sm font-bold text-desk-text transition-colors group-hover:text-brand"
        >
          {{ pdf.title }}
        </h3>
        <p class="mt-0.5 truncate text-[11px] text-desk-muted">
          {{ pdf.category || pdf.classification?.name || 'منبع آموزشی' }}
        </p>
        <div class="mt-2 flex items-center justify-between gap-2">
          <span class="text-sm font-bold text-brand">
            <template v-if="pdf.is_free">رایگان</template>
            <template v-else>{{ formatPrice(pdf.price) }}</template>
          </span>
          <span
            class="rounded-md px-2 py-0.5 text-[10px] font-bold"
            :class="badgeClass"
          >
            {{ badgeLabel }}
          </span>
        </div>
      </div>
    </div>
  </article>
</template>

<script setup>
import { computed } from 'vue'
import { formatPrice } from '../../utils/format'

const props = defineProps({
  pdf: { type: Object, required: true },
})
defineEmits(['click'])

const cover = computed(() => props.pdf.cover || props.pdf.thumbnail_url || null)

const badgeLabel = computed(() => {
  if (props.pdf.is_purchased) return 'خریداری‌شده'
  if (props.pdf.is_free) return 'رایگان'
  return 'خرید'
})

const badgeClass = computed(() => {
  if (props.pdf.is_purchased) return 'bg-emerald-50 text-emerald-700'
  if (props.pdf.is_free) return 'bg-brand-soft text-brand'
  return 'bg-surface-page text-desk-muted'
})
</script>
