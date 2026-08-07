<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="$emit('close')">
    <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
      <div class="mb-4 flex items-center justify-between">
        <h3 class="font-bold">اشتراک‌گذاری</h3>
        <button type="button" @click="$emit('close')">✕</button>
      </div>
      <div class="mb-4 rounded-xl border border-slate-100 p-3">
        <p class="text-sm font-bold">{{ title }}</p>
        <p class="mt-1 line-clamp-2 text-xs text-slate-500">{{ description }}</p>
      </div>
      <div class="mb-4 grid grid-cols-4 gap-2 text-center text-xs">
        <a :href="telegram" target="_blank" rel="noopener" class="rounded-xl bg-sky-50 py-3 font-bold text-sky-700">تلگرام</a>
        <a :href="whatsapp" target="_blank" rel="noopener" class="rounded-xl bg-emerald-50 py-3 font-bold text-emerald-700">واتساپ</a>
        <a :href="twitter" target="_blank" rel="noopener" class="rounded-xl bg-slate-100 py-3 font-bold text-slate-700">X</a>
        <button type="button" class="rounded-xl bg-orange-50 py-3 font-bold text-brand" @click="copy">کپی لینک</button>
      </div>
      <input :value="url" readonly class="h-10 w-full rounded-xl border border-slate-200 px-3 text-xs" dir="ltr" />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useToast } from '../composables/useToast';

const props = defineProps({
  open: Boolean,
  title: { type: String, default: '' },
  description: { type: String, default: '' },
  url: { type: String, default: '' },
});
defineEmits(['close']);
const toast = useToast();

const shareText = computed(() => `${props.title} — ${props.url}`);
const telegram = computed(() => `https://t.me/share/url?url=${encodeURIComponent(props.url)}&text=${encodeURIComponent(props.title)}`);
const whatsapp = computed(() => `https://wa.me/?text=${encodeURIComponent(shareText.value)}`);
const twitter = computed(() => `https://twitter.com/intent/tweet?text=${encodeURIComponent(shareText.value)}`);

async function copy() {
  try {
    await navigator.clipboard.writeText(props.url);
    toast.success('لینک کپی شد');
  } catch {
    toast.error('کپی نشد');
  }
}
</script>
