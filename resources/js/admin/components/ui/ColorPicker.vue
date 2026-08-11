<template>
  <div class="flex items-center gap-3">
    <input
      type="color"
      :value="modelValue || '#f97316'"
      class="h-10 w-14 cursor-pointer rounded-lg border border-slate-200 bg-white p-1"
      @input="$emit('update:modelValue', $event.target.value)"
    />
    <input
      :value="modelValue"
      type="text"
      dir="ltr"
      class="field flex-1"
      placeholder="#f97316"
      @input="$emit('update:modelValue', $event.target.value)"
    />
    <div class="flex gap-1">
      <button
        v-for="c in presets"
        :key="c"
        type="button"
        class="h-7 w-7 rounded-full border border-slate-200"
        :style="{ backgroundColor: c }"
        @click="$emit('update:modelValue', c)"
      />
    </div>
  </div>
</template>

<script setup>
defineProps({
  modelValue: { type: String, default: '#f97316' },
})
defineEmits(['update:modelValue'])

const presets = [
  '#f97316',
  '#0f2744',
  '#059669',
  '#2563eb',
  '#dc2626',
  '#7c3aed',
]
</script>

<style scoped>
.field {
  @apply h-10 rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-orange-400;
}
</style>
