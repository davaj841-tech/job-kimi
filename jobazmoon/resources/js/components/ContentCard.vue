<template>
  <article
    class="overflow-hidden rounded-xl border border-surface-line bg-white shadow-sm transition active:bg-slate-50"
    @click="$emit('click')"
  >
    <div class="p-3">
      <div class="mb-1 flex items-start justify-between gap-2">
        <h3 class="mobile-card-title line-clamp-2">{{ title }}</h3>
        <span
          v-if="badge"
          class="shrink-0 rounded-md px-1.5 py-0.5 text-[10px] font-bold"
          :class="badgeClass"
        >
          {{ badge }}
        </span>
      </div>
      <p v-if="subtitle" class="mb-2 line-clamp-1 text-xs text-desk-muted">{{ subtitle }}</p>
      <div class="flex items-center justify-between text-xs text-desk-muted">
        <span>{{ meta }}</span>
        <span v-if="price !== null" class="font-bold tabular-nums text-desk-orange">{{ formatPrice(price) }}</span>
      </div>
    </div>
  </article>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  meta: { type: String, default: '' },
  badge: { type: String, default: '' },
  price: { type: [Number, String], default: null },
});

defineEmits(['click']);

const badgeClass = computed(() => {
  if (props.badge === 'رایگان') return 'bg-emerald-50 text-desk-green';
  if (props.badge === 'ویژه') return 'bg-orange-50 text-desk-orange';
  return 'bg-slate-100 text-desk-blue';
});

function formatPrice(value) {
  if (Number(value) === 0) return 'رایگان';
  return new Intl.NumberFormat('fa-IR').format(Number(value)) + ' ریال';
}
</script>
