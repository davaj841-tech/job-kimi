<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    
  >
    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
      <h3 class="mb-2 text-lg font-bold text-slate-800">{{ title }}</h3>
      <p class="mb-6 text-sm leading-7 text-slate-500">{{ message }}</p>
      <div class="flex justify-end gap-2">
        <button
          type="button"
          class="rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-200"
          @click="$emit('cancel')"
        >
          انصراف
        </button>
        <button
          type="button"
          class="rounded-xl px-4 py-2.5 text-sm font-bold text-white"
          :class="danger ? 'bg-red-500 hover:bg-red-600' : 'bg-orange-500 hover:bg-orange-600'"
          @click="$emit('confirm')"
        >
          تایید
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: 'آیا مطمئن هستید؟' },
  message: { type: String, default: '' },
  danger: { type: Boolean, default: true },
});

defineEmits(['confirm', 'cancel']);
</script>
