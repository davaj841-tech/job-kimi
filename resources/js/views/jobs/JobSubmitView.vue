<template>
  <div class="px-4 py-4">
    <PageHeader title="ثبت آگهی شغلی" subtitle="پس از بررسی اپراتور منتشر می‌شود" />
    <ErrorBanner :message="error" />

    <form class="space-y-3" @submit.prevent="submit">
      <input v-model="form.title" class="input-field" placeholder="عنوان آگهی *" required />

      <select v-model="form.job_classification_id" class="input-field" required>
        <option disabled value="">طبقه‌بندی آگهی *</option>
        <option v-for="c in classifications" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>

      <textarea v-model="form.description" class="input-field h-28 py-2" placeholder="شرح آگهی *" required />

      <div>
        <p class="mb-1 text-xs text-desk-muted">استان‌ها (اختیاری — چند انتخابی)</p>
        <div class="max-h-32 overflow-y-auto rounded-lg border border-surface-line bg-white p-2">
          <label v-for="p in provinces" :key="p" class="flex items-center gap-2 py-1 text-sm">
            <input v-model="form.provinces" type="checkbox" :value="p" />
            {{ p }}
          </label>
        </div>
      </div>

      <input v-model="form.city" class="input-field" placeholder="شهر (اختیاری)" />

      <div>
        <label class="mb-1 block text-xs text-desk-muted">مهلت ثبت‌نام * (سال / ماه / روز)</label>
        <JalaliDatepicker v-model="form.registration_deadline" />
      </div>
      <div>
        <label class="mb-1 block text-xs text-desk-muted">تاریخ آزمون (سال / ماه / روز)</label>
        <JalaliDatepicker v-model="form.exam_date" />
      </div>

      <input v-model="form.registration_link" class="input-field" placeholder="لینک ثبت‌نام (اختیاری)" dir="ltr" />
      <button class="btn-primary" :disabled="saving">ثبت آگهی</button>
    </form>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../../api/client';
import ErrorBanner from '../../components/ErrorBanner.vue';
import PageHeader from '../../components/PageHeader.vue';
import JalaliDatepicker from '../../admin/components/ui/JalaliDatepicker.vue';
import { apiErrorMessage } from '../../utils/format';
import { useToast } from '../../composables/useToast';

const router = useRouter();
const toast = useToast();
const saving = ref(false);
const error = ref('');
const classifications = ref([]);
const provinces = ref([
  'تهران', 'اصفهان', 'فارس', 'خراسان رضوی', 'آذربایجان شرقی', 'خوزستان', 'البرز', 'قم', 'کرمان', 'گیلان',
  'مازندران', 'آذربایجان غربی', 'کرمانشاه', 'همدان', 'یزد', 'سیستان و بلوچستان', 'گلستان', 'لرستان', 'مرکزی', 'قزوین',
]);

const form = reactive({
  title: '',
  job_classification_id: '',
  description: '',
  provinces: [],
  city: '',
  registration_deadline: '',
  exam_date: '',
  registration_link: '',
});

onMounted(async () => {
  try {
    const { data } = await api.get('/job-posts/filters');
    const payload = data.data || {};
    if (payload.classifications?.length) classifications.value = payload.classifications;
    if (payload.provinces?.length) {
      provinces.value = [...new Set([...provinces.value, ...payload.provinces])];
    }
  } catch (_) {
    /* keep defaults */
  }
});

async function submit() {
  saving.value = true;
  error.value = '';
  try {
    const payload = {
      title: form.title,
      job_classification_id: form.job_classification_id,
      description: form.description,
      provinces: form.provinces,
      city: form.city || null,
      registration_deadline: form.registration_deadline,
      exam_date: form.exam_date || null,
      registration_link: form.registration_link || null,
    };

    const { data } = await api.post('/job-posts/submit', payload);
    toast.success(data.message || 'آگهی ثبت شد.');
    router.replace('/jobs');
  } catch (e) {
    error.value = apiErrorMessage(e, 'ثبت آگهی ناموفق بود.');
  } finally {
    saving.value = false;
  }
}
</script>
