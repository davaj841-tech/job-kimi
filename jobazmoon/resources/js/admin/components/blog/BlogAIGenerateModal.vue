<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" >
    <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
      <div class="mb-4 flex items-center justify-between">
        <h3 class="text-lg font-bold">تولید مقاله با AI</h3>
        <button @click="$emit('close')">✕</button>
      </div>
      <input v-model="topic" class="field mb-3" placeholder="موضوع مقاله" />
      <div v-if="preview" class="mb-3 max-h-48 overflow-y-auto rounded-xl bg-slate-50 p-3 text-sm leading-7">
        <p class="mb-2 font-bold">{{ preview.title }}</p>
        <p class="text-slate-600">{{ preview.excerpt || preview.content?.slice?.(0, 280) }}</p>
      </div>
      <p v-if="message" class="mb-2 text-sm text-emerald-600">{{ message }}</p>
      <p v-if="error" class="mb-2 text-sm text-red-500">{{ error }}</p>
      <div class="flex justify-end gap-2">
        <button class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-bold" @click="$emit('close')">بستن</button>
        <button class="rounded-xl bg-orange-500 px-4 py-2 text-sm font-bold text-white disabled:opacity-50" :disabled="!topic || loading" @click="run">
          {{ loading ? 'در حال تولید...' : 'تولید' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';

defineProps({ open: Boolean });
const emit = defineEmits(['close', 'generate']);

const topic = ref('');
const loading = ref(false);
const error = ref('');
const message = ref('');
const preview = ref(null);

watch(topic, () => {
  error.value = '';
  message.value = '';
});

function run() {
  loading.value = true;
  emit('generate', topic.value);
}

defineExpose({
  setLoading(v) {
    loading.value = v;
  },
  setError(msg) {
    error.value = msg;
    loading.value = false;
  },
  setResult(data) {
    preview.value = data?.preview || data || null;
    message.value = 'پیش‌نویس در صف بررسی ذخیره شد.';
    loading.value = false;
  },
});
</script>

<style scoped>
.field { @apply h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-orange-400; }
</style>
