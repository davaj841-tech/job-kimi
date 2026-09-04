<template>
  <div class="space-y-2">
    <button
      v-for="item in fonts"
      :key="item.id"
      type="button"
      class="flex w-full items-center justify-between gap-3 rounded-xl border-2 px-4 py-3 text-right transition"
      :class="
        modelValue === item.id
          ? 'border-orange-500 bg-orange-50'
          : 'border-slate-200 bg-white hover:border-slate-300'
      "
      @click="$emit('update:modelValue', item.id)"
    >
      <span class="text-sm font-black text-slate-800">{{ item.title }}</span>
      <span
        class="text-xs leading-6 text-slate-600"
        :style="{ fontFamily: item.family }"
      >
        {{ item.sample }}
      </span>
    </button>
  </div>
</template>

<script setup>
import { SITE_FONT_PRESETS, SITE_FONTS } from '../../../theme/presets'

defineProps({
  modelValue: { type: String, default: 'estedad' },
})
defineEmits(['update:modelValue'])

const fonts = SITE_FONTS.map((id) => SITE_FONT_PRESETS[id])
</script>
