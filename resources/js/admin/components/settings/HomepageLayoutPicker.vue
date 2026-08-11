<template>
  <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
    <button
      v-for="item in presets"
      :key="item.id"
      type="button"
      class="rounded-2xl border-2 p-2.5 text-right transition"
      :class="
        modelValue === item.id
          ? 'border-orange-500 bg-orange-50'
          : 'border-slate-200 bg-white hover:border-slate-300'
      "
      @click="$emit('update:modelValue', item.id)"
    >
      <div
        class="mb-2 h-12 overflow-hidden rounded-xl"
        :class="item.preview"
      >
        <div class="flex h-full items-end justify-between px-2 pb-2">
          <span class="h-2 w-8 rounded bg-white/40" />
          <span
            class="h-5 w-10 rounded"
            :style="{ backgroundColor: item.primary }"
          />
        </div>
      </div>
      <p class="text-[13px] font-black text-slate-800">{{ item.title }}</p>
      <p class="mt-1 text-[10px] leading-4 text-slate-500">{{ item.desc }}</p>
    </button>
  </div>
</template>

<script setup>
import { SITE_THEME_PRESETS, SITE_THEMES } from '../../../theme/presets'

defineProps({
  modelValue: { type: String, default: 'atlas' },
})
defineEmits(['update:modelValue'])

const presets = SITE_THEMES.map((id) => SITE_THEME_PRESETS[id])
</script>
