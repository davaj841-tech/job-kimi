<template>
  <div>
    <p class="mb-2 text-xs font-bold text-desk-muted">قالب رزومه (A4)</p>
    <div class="space-y-3">
      <div v-for="g in RESUME_THEME_GROUPS" :key="g.id">
        <p class="mb-1.5 text-[11px] font-bold text-slate-500">{{ g.name }}</p>
        <div class="grid grid-cols-3 gap-1.5 sm:grid-cols-5">
          <button
            v-for="t in themesBy(g.id)"
            :key="t.id"
            type="button"
            class="overflow-hidden rounded-lg border text-right transition"
            :class="
              Number(modelValue) === t.id
                ? 'border-brand ring-2 ring-brand/30'
                : 'border-slate-200 hover:border-slate-300'
            "
            @click="$emit('update:modelValue', t.id)"
          >
            <span class="block h-8" :style="{ background: t.header }" />
            <span class="block truncate px-1 py-1 text-[9px] leading-tight">{{
              t.name
            }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { RESUME_THEME_GROUPS, RESUME_THEMES } from '../../data/resumeThemes'

defineProps({
  modelValue: { type: [Number, String], default: 1 },
})
defineEmits(['update:modelValue'])

function themesBy(group) {
  return RESUME_THEMES.filter((t) => t.group === group)
}
</script>
