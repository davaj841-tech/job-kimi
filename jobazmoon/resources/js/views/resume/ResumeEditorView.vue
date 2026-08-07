<template>
  <div class="min-h-dvh bg-white px-4 py-4">
    <div class="mb-4 flex items-center justify-between">
      <button class="text-sm text-ink-muted" @click="$router.back()">بازگشت</button>
      <button class="rounded-lg bg-brand px-3 py-2 text-xs font-bold text-white" :disabled="saving" @click="save">
        ذخیره
      </button>
    </div>
    <LoadingSpinner v-if="loading" />
    <template v-else-if="form">
      <h1 class="mb-4 text-lg font-black">{{ form.title }}</h1>
      <div class="space-y-3">
        <input v-model="form.data.personal.full_name" class="input-field" placeholder="نام و نام خانوادگی" />
        <input v-model="form.data.target_job" class="input-field" placeholder="شغل هدف" />
        <textarea v-model="form.data.summary" class="input-field h-24 py-2" placeholder="خلاصه حرفه‌ای" />
        <select v-model.number="form.template_id" class="input-field">
          <option :value="1">قالب مدرن</option>
          <option :value="2">قالب مینیمال</option>
          <option :value="3">قالب کلاسیک</option>
        </select>
        <button class="btn-ghost w-full border border-surface-line" @click="aiSuggest">پیشنهاد AI</button>
        <a class="btn-primary block text-center" href="#" @click.prevent="downloadPdf">دانلود PDF</a>
      </div>
      <div v-if="suggestions.length" class="mt-4 space-y-2">
        <p class="text-sm font-bold">پیشنهادها (دستی اعمال کنید):</p>
        <div v-for="(s, i) in suggestions" :key="i" class="card-soft p-3 text-xs">
          <span class="font-bold text-brand">{{ s.section }}</span> — {{ s.suggestion }}
        </div>
      </div>
      <p v-if="message" class="mt-3 text-center text-sm text-brand">{{ message }}</p>
    </template>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import api from '../../api/client';
import LoadingSpinner from '../../components/LoadingSpinner.vue';
import { apiErrorMessage } from '../../utils/format';
import { useToast } from '../../composables/useToast';

const route = useRoute();
const toast = useToast();
const form = ref(null);
const loading = ref(true);
const saving = ref(false);
const message = ref('');
const suggestions = ref([]);

onMounted(async () => {
  try {
    const { data } = await api.get(`/resumes/${route.params.id}`);
    form.value = data.data;
  } finally {
    loading.value = false;
  }
});

async function save() {
  saving.value = true;
  message.value = '';
  try {
    const { data } = await api.put(`/resumes/${route.params.id}`, {
      title: form.value.title,
      template_id: form.value.template_id,
      data: form.value.data,
    });
    form.value = data.data;
    message.value = 'ذخیره شد.';
    toast.success('رزومه ذخیره شد.');
  } catch (e) {
    message.value = apiErrorMessage(e, 'خطا در ذخیره.');
  } finally {
    saving.value = false;
  }
}

async function aiSuggest() {
  message.value = '';
  try {
    const { data } = await api.post(`/resumes/${route.params.id}/ai-suggest`);
    suggestions.value = data.data?.suggestions || [];
    toast.info('پیشنهادهای AI آماده‌اند.');
  } catch (e) {
    message.value = apiErrorMessage(e, 'پیشنهاد AI در دسترس نیست.');
  }
}

async function downloadPdf() {
  try {
    const { data } = await api.get(`/resumes/${route.params.id}/pdf`, { responseType: 'blob' });
    const url = URL.createObjectURL(data);
    const a = document.createElement('a');
    a.href = url;
    a.download = `resume-${route.params.id}.pdf`;
    a.click();
    URL.revokeObjectURL(url);
  } catch (e) {
    toast.error(apiErrorMessage(e, 'دانلود PDF ممکن نشد.'));
  }
}
</script>
