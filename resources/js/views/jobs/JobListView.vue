<template>
  <div class="px-4 py-4">
    <div class="mb-3 flex items-center justify-between">
      <h1 class="section-title">آگهی‌های شغلی</h1>
      <RouterLink to="/jobs/submit" class="text-xs font-bold text-brand">+ ثبت آگهی</RouterLink>
    </div>
    <input v-model="search" class="input-field mb-3" placeholder="جستجو عنوان یا سازمان..." @keyup.enter="load" />
    <LoadingSpinner v-if="loading" />
    <div v-else class="space-y-2">
      <ContentCard
        v-for="job in jobs"
        :key="job.id"
        :title="job.title"
        :subtitle="job.company_name"
        :meta="[job.city, job.job_category].filter(Boolean).join(' · ')"
        :badge="job.is_featured ? 'ویژه' : ''"
        @click="$router.push(`/jobs/${job.id}`)"
      />
      <p v-if="!jobs.length" class="py-10 text-center text-sm text-ink-muted">موردی یافت نشد.</p>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import api from '../../api/client';
import ContentCard from '../../components/ContentCard.vue';
import LoadingSpinner from '../../components/LoadingSpinner.vue';

const jobs = ref([]);
const loading = ref(true);
const search = ref('');

async function load() {
  loading.value = true;
  try {
    const { data } = await api.get('/job-posts', { params: { search: search.value || undefined } });
    jobs.value = data.data?.data || data.data || [];
  } catch (_) {
    jobs.value = [];
  } finally {
    loading.value = false;
  }
}

let t;
watch(search, () => {
  clearTimeout(t);
  t = setTimeout(load, 350);
});

onMounted(load);
</script>
