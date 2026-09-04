<template>
  <div
    v-if="hasBadges"
    class="flex flex-wrap items-center"
    :class="compact ? 'gap-1.5' : 'gap-3'"
  >
    <a
      v-if="enamadEnabled && enamadUrl && enamadLogoUrl"
      :href="enamadUrl"
      target="_blank"
      rel="noopener noreferrer"
      referrerpolicy="origin"
      class="inline-flex shrink-0 items-center justify-center overflow-hidden rounded-md bg-white/95 transition hover:opacity-90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
      :class="enamadSizeClass"
      title="نماد اعتماد الکترونیکی"
      aria-label="نماد اعتماد الکترونیکی — مشاهده در سایت رسمی enamad.ir"
    >
      <img
        :src="enamadLogoUrl"
        alt="نماد اعتماد الکترونیکی"
        referrerpolicy="origin"
        loading="lazy"
        decoding="async"
        width="120"
        height="120"
        class="h-full w-full cursor-pointer object-contain"
        :code="enamadCode"
      />
    </a>

    <a
      v-if="samandehiUrl"
      :href="samandehiUrl"
      target="_blank"
      rel="noopener noreferrer"
      class="inline-flex shrink-0 items-center justify-center rounded-md border px-2 text-center text-[10px] leading-4 transition hover:opacity-90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
      :class="samandehiClass"
      title="نماد ساماندهی"
      aria-label="نماد ساماندهی"
    >
      <span>ساماندهی</span>
    </a>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useSiteTheme } from '../composables/useSiteTheme'

const props = defineProps({
  compact: { type: Boolean, default: false },
})

const {
  enamadEnabled,
  enamadUrl,
  enamadLogoUrl,
  enamadCode,
  samandehiUrl,
  ensureLoaded,
} = useSiteTheme()

const enamadSizeClass = computed(() =>
  props.compact ? 'h-16 w-[4.5rem]' : 'h-24 w-28'
)

const samandehiClass = computed(() => [
  props.compact ? 'h-8 min-w-[4.5rem]' : 'h-10 min-w-28',
  'border-white/30 bg-white/5 text-white/80',
])

const hasBadges = computed(
  () =>
    (enamadEnabled.value && enamadUrl.value && enamadLogoUrl.value) ||
    Boolean(samandehiUrl.value)
)

onMounted(() => {
  ensureLoaded()
})
</script>
