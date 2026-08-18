<template>
  <div
    class="pointer-events-none fixed bottom-20 left-3 z-40 flex flex-col gap-2 sm:bottom-6 sm:left-5"
  >
    <button
      type="button"
      class="pointer-events-auto flex h-10 w-10 items-center justify-center rounded-full border border-surface-line bg-white text-desk-text shadow-md dark:border-slate-600 dark:bg-slate-800 dark:text-white"
      title="بالای صفحه"
      @click="toTop"
    >
      <ChevronUpIcon class="h-5 w-5" />
    </button>
    <button
      type="button"
      class="pointer-events-auto flex h-10 w-10 items-center justify-center rounded-full border border-surface-line bg-white text-desk-text shadow-md dark:border-slate-600 dark:bg-slate-800 dark:text-white"
      title="پایین صفحه"
      @click="toBottom"
    >
      <ChevronDownIcon class="h-5 w-5" />
    </button>
  </div>
</template>

<script setup>
import { ChevronDownIcon, ChevronUpIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  target: { type: [Object, String], default: null },
})

function el() {
  if (typeof props.target === 'string') {
    return document.querySelector(props.target)
  }
  return props.target || null
}

function toTop() {
  const node = el()
  if (node && node.scrollHeight > node.clientHeight + 8) {
    node.scrollTo({ top: 0, behavior: 'smooth' })
    return
  }
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function toBottom() {
  const node = el()
  if (node && node.scrollHeight > node.clientHeight + 8) {
    node.scrollTo({ top: node.scrollHeight, behavior: 'smooth' })
    return
  }
  window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'smooth' })
}
</script>
