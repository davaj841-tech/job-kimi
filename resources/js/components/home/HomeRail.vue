<template>
  <div class="relative">
    <div
      ref="scroller"
      class="scrollbar-hide flex gap-3 overflow-x-auto scroll-smooth pb-1 pe-12"
    >
      <slot />
    </div>
    <button
      v-if="canScroll"
      type="button"
      class="absolute left-0 top-1/2 z-10 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-surface-line bg-white text-desk-dark shadow-md transition hover:bg-desk-dark hover:text-white"
      aria-label="موارد بیشتر"
      @click="scrollMore"
    >
      <ChevronLeftIcon class="h-5 w-5" />
    </button>
  </div>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { ChevronLeftIcon } from '@heroicons/vue/24/outline'

const scroller = ref(null)
const canScroll = ref(false)
let observer

function measure() {
  const el = scroller.value
  if (!el) {
    canScroll.value = false
    return
  }
  canScroll.value = el.scrollWidth > el.clientWidth + 8
}

function scrollMore() {
  const el = scroller.value
  if (!el) return
  el.scrollBy({ left: -Math.round(el.clientWidth * 0.8), behavior: 'smooth' })
}

onMounted(async () => {
  await nextTick()
  measure()
  window.addEventListener('resize', measure)
  if (typeof ResizeObserver !== 'undefined' && scroller.value) {
    observer = new ResizeObserver(measure)
    observer.observe(scroller.value)
  }
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', measure)
  observer?.disconnect()
})

defineExpose({ measure })
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
