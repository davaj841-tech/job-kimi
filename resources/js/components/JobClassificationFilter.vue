<template>
  <div class="-mx-1 flex gap-3 overflow-x-auto px-1 pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
    <button
      type="button"
      class="flex w-[88px] shrink-0 flex-col items-center gap-2 rounded-2xl border px-2 py-3 text-center transition"
      :class="!modelValue ? 'border-desk-orange bg-orange-50 shadow-sm' : 'border-surface-line bg-white hover:border-desk-orange/40'"
      @click="$emit('update:modelValue', null)"
    >
      <span class="flex h-12 w-12 items-center justify-center rounded-full bg-desk-dark text-white">
        <DesktopIcon name="grid" :size="22" />
      </span>
      <span class="line-clamp-2 text-[11px] font-bold text-desk-text">همه</span>
    </button>

    <button
      v-for="item in items"
      :key="item.id"
      type="button"
      class="flex w-[88px] shrink-0 flex-col items-center gap-2 rounded-2xl border px-2 py-3 text-center transition"
      :class="Number(modelValue) === Number(item.id) ? 'border-desk-orange bg-orange-50 shadow-sm' : 'border-surface-line bg-white hover:border-desk-orange/40'"
      @click="$emit('update:modelValue', item.id)"
    >
      <span
        class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-full text-lg text-white"
        :style="{ background: item.color || '#1e3a5f' }"
      >
        <img v-if="item.logo_url" :src="item.logo_url" :alt="item.name" class="h-full w-full object-cover" />
        <DesktopIcon v-else-if="isNamedIcon(item.icon)" :name="item.icon" :size="22" />
        <span v-else>{{ item.icon || '●' }}</span>
      </span>
      <span class="line-clamp-2 text-[11px] font-bold text-desk-text">{{ item.name }}</span>
    </button>
  </div>
</template>

<script setup>
import DesktopIcon from './DesktopIcon.vue';

defineProps({
  items: { type: Array, default: () => [] },
  modelValue: { type: [Number, String, null], default: null },
});

defineEmits(['update:modelValue']);

const named = ['school', 'bank', 'shield', 'building', 'city', 'briefcase', 'grid', 'book', 'users'];

function isNamedIcon(icon) {
  return named.includes(icon);
}
</script>
