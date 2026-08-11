<template>
  <div
    v-if="meta?.last_page"
    class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white px-4 py-3 text-sm shadow-sm"
  >
    <p class="text-slate-500">
      نمایش {{ fa(meta.from || 0) }} تا {{ fa(meta.to || 0) }} از
      {{ fa(meta.total || 0) }}
    </p>
    <div class="flex gap-1">
      <button
        :disabled="(meta.current_page || 1) <= 1"
        @click="$emit('page', (meta.current_page || 1) - 1)"
      >
        قبلی
      </button>
      <button
        v-for="p in pages"
        :key="p"
        class="min-w-8 rounded-lg px-2 py-1 text-xs font-bold"
        :class="
          p === meta.current_page ? 'bg-orange-500 text-white' : 'bg-slate-100'
        "
        @click="$emit('page', p)"
      >
        {{ fa(p) }}
      </button>
      <button
        :disabled="(meta.current_page || 1) >= (meta.last_page || 1)"
        @click="$emit('page', (meta.current_page || 1) + 1)"
      >
        بعدی
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  meta: { type: Object, default: () => ({}) },
})
defineEmits(['page'])

const pages = computed(() => {
  const cur = props.meta.current_page || 1
  const last = props.meta.last_page || 1
  const out = []
  for (
    let i = Math.max(1, cur - 2);
    i <= Math.min(last, Math.max(1, cur - 2) + 4);
    i++
  )
    out.push(i)
  return out
})

function fa(n) {
  return new Intl.NumberFormat('fa-IR').format(Number(n || 0))
}
</script>
