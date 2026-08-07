<template>
  <div class="px-4 py-4">
    <div class="mb-4 flex items-center justify-between">
      <h1 class="section-title">رزومه‌های من</h1>
      <button class="rounded-lg bg-brand px-3 py-2 text-xs font-bold text-white" @click="create">+ جدید</button>
    </div>
    <LoadingSpinner v-if="loading" />
    <div v-else class="space-y-2">
      <RouterLink
        v-for="item in items"
        :key="item.id"
        :to="`/resumes/${item.id}`"
        class="card-soft block p-3"
      >
        <p class="text-sm font-bold">{{ item.title }}</p>
        <p class="mt-1 text-xs text-ink-muted">قالب {{ item.template_id }}</p>
      </RouterLink>
      <p v-if="!items.length" class="py-10 text-center text-sm text-ink-muted">هنوز رزومه‌ای ندارید.</p>
    </div>
    <p v-if="message" class="mt-3 text-center text-sm text-brand">{{ message }}</p>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../../api/client';
import LoadingSpinner from '../../components/LoadingSpinner.vue';

const router = useRouter();
const items = ref([]);
const loading = ref(true);
const message = ref('');

onMounted(async () => {
  try {
    const { data } = await api.get('/resumes');
    items.value = data.data || [];
  } finally {
    loading.value = false;
  }
});

async function create() {
  message.value = '';
  try {
    const { data } = await api.post('/resumes', {
      template_id: 1,
      data: {
        personal: {
          full_name: 'نام کاربر',
          birth_date: '1370-01-01',
          national_code: '0000000000',
          mobile: '09120000000',
          email: 'user@example.com',
          address: '',
          photo: null,
        },
        education: [
          {
            degree: 'کارشناسی',
            field: 'مهندسی',
            university: 'دانشگاه تهران',
            start_year: 1395,
            end_year: 1399,
            gpa: null,
          },
        ],
        experience: [],
        skills: [{ name: 'کار تیمی', level: 'متوسط' }],
        languages: [],
        summary: '',
        target_job: 'کارشناس',
      },
    });
    router.push(`/resumes/${data.data.id}`);
  } catch (e) {
    message.value = e.response?.data?.message || 'ایجاد رزومه ناموفق بود.';
  }
}
</script>
