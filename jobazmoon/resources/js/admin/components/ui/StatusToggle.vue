<template>
  <button
    type="button"
    class="relative inline-flex h-6 w-11 items-center rounded-full transition"
    :class="modelValue ? 'bg-emerald-500' : 'bg-slate-300'"
    @click="$emit('update:modelValue', !modelValue)"
  >
    <span
      class="inline-block h-4 w-4 transform rounded-full bg-white transition"
      :class="modelValue ? '-translate-x-6' : '-translate-x-1'"
    />
  </button>
</template>

<script setup>
defineProps({
  modelValue: { type: Boolean, default: false },
});
defineEmits(['update:modelValue']);
</script>
