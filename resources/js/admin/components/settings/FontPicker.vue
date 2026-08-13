<template>
  <div class="grid gap-3 sm:grid-cols-5">
    <button
      v-for="item in fonts"
      :key="item.id"
      type="button"
      class="rounded-2xl border-2 p-3 text-right transition"
      :class="
        modelValue === item.id
          ? 'border-orange-500 bg-orange-50'
          : 'border-slate-200 bg-white hover:border-slate-300'
      "
      @click="$emit('update:modelValue', item.id)"
    >
      <p class="text-sm font-black text-slate-800">{{ item.title }}</p>
      <p
        class="mt-2 text-xs leading-6 text-slate-600"
        :style="{ fontFamily: item.family }"
      >
        {{ item.sample }}
      </p>
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
