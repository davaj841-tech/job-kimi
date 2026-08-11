<template>
  <article
    class="group cursor-pointer overflow-hidden rounded-2xl border border-surface-line bg-white transition-all duration-300 hover:-translate-y-1 hover:border-brand/30 hover:shadow-xl hover:shadow-brand/5 dark:border-slate-800 dark:bg-slate-900"
    role="button"
    tabindex="0"
    @click="$emit('click')"
    @keydown.enter.prevent="$emit('click')"
  >
    <div
      class="relative aspect-[3/4] overflow-hidden bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-800 dark:to-slate-700"
    >
      <img
        v-if="cover"
        :src="cover"
        :alt="pdf.title"
        class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
      />
      <div
        v-else
        class="absolute inset-0 flex items-center justify-center p-4 text-center"
      >
        <div>
          <DocumentTextIcon class="mx-auto mb-2 h-10 w-10 text-slate-400" />
          <span class="line-clamp-3 text-xs text-desk-muted">{{ pdf.title }}</span>
        </div>
      </div>

      <div class="absolute left-2 top-2 flex flex-col gap-1">
        <span
          v-if="pdf.is_new"
          class="rounded-lg bg-emerald-500 px-2 py-0.5 text-[10px] font-bold text-white shadow"
          >جدید</span
        >
        <span
          v-if="pdf.is_free"
          class="rounded-lg bg-brand px-2 py-0.5 text-[10px] font-bold text-white shadow"
          >رایگان</span
        >
        <span
          v-if="pdf.is_purchased"
          class="rounded-lg bg-desk-dark px-2 py-0.5 text-[10px] font-bold text-white shadow"
          >خریداری‌شده</span
        >
      </div>
    </div>

    <div class="p-3">
      <h3
        class="line-clamp-2 min-h-[2.5rem] text-sm font-bold text-desk-text transition-colors group-hover:text-brand dark:text-white"
      >
        {{ pdf.title }}
      </h3>
      <p class="mt-1 truncate text-xs text-desk-muted">
        {{ pdf.category || pdf.classification?.name || 'منبع آموزشی' }}
      </p>

      <div
        class="mt-3 flex items-center justify-between border-t border-surface-line pt-3 dark:border-slate-800"
      >
        <span class="text-sm font-bold text-brand">
          <template v-if="pdf.is_free">رایگان</template>
          <template v-else>{{ formatPrice(pdf.price) }}</template>
        </span>
        <span
          class="flex h-8 w-8 items-center justify-center rounded-lg"
          :class="actionClass"
        >
          <component
            :is="actionIcon"
            class="h-4 w-4"
          />
        </span>
      </div>
    </div>
  </article>
</template>

<script setup>
import { computed } from 'vue'
import {
  ArrowDownTrayIcon,
  DocumentTextIcon,
  EyeIcon,
  ShoppingCartIcon,
} from '@heroicons/vue/24/outline'
import { formatPrice } from '../../utils/format'

const props = defineProps({
  pdf: { type: Object, required: true },
})
defineEmits(['click'])

const cover = computed(() => props.pdf.cover || props.pdf.thumbnail_url || null)

const actionClass = computed(() => {
  if (props.pdf.is_purchased) return 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30'
  if (props.pdf.is_free) return 'bg-brand-soft text-brand'
  return 'bg-slate-100 text-desk-muted dark:bg-slate-800'
})

const actionIcon = computed(() => {
  if (props.pdf.is_purchased) return EyeIcon
  if (props.pdf.is_free) return ArrowDownTrayIcon
  return ShoppingCartIcon
})
</script>
