<template>
  <div class="px-4 py-4">
    <PageHeader title="خریدهای من" subtitle="فایل‌های PDF خریداری‌شده" />
    <LoadingSpinner v-if="loading" />
    <EmptyState v-else-if="!items.length" title="هنوز خریدی ندارید" description="از فروشگاه PDF فایل مورد نیاز را بخرید.">
      <RouterLink to="/pdfs" class="btn-primary max-w-xs">رفتن به فروشگاه</RouterLink>
    </EmptyState>
    <div v-else class="space-y-2">
      <div v-for="item in items" :key="item.id" class="card-soft p-3">
        <p class="text-sm font-bold">{{ item.title }}</p>
        <p class="mt-1 text-xs text-ink-muted">تاریخ خرید: {{ formatDate(item.purchase_date) }}</p>
        <a
          v-if="item.download_url"
          :href="item.download_url"
          class="mt-3 inline-flex text-xs font-bold text-brand"
          @click.prevent="download(item)"
        >
          دانلود فایل
        </a>
      </div>
    </div>
    <ErrorBanner :message="error" />
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import api from '../../api/client';
import EmptyState from '../../components/EmptyState.vue';
import ErrorBanner from '../../components/ErrorBanner.vue';
import LoadingSpinner from '../../components/LoadingSpinner.vue';
import PageHeader from '../../components/PageHeader.vue';
import { apiErrorMessage, formatDate, unwrapList } from '../../utils/format';
import { useToast } from '../../composables/useToast';

const toast = useToast();
const items = ref([]);
const loading = ref(true);
const error = ref('');

onMounted(async () => {
  try {
    const { data } = await api.get('/my-purchases');
    items.value = unwrapList(data);
  } catch (e) {
    error.value = apiErrorMessage(e);
  } finally {
    loading.value = false;
  }
});

async function download(item) {
  try {
    const { data } = await api.get(`/pdf-products/${item.id}/download`, { responseType: 'blob' });
    const url = URL.createObjectURL(data);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${item.title || 'file'}.pdf`;
    a.click();
    URL.revokeObjectURL(url);
  } catch (e) {
    toast.error(apiErrorMessage(e, 'دانلود ممکن نشد.'));
  }
}
</script>
