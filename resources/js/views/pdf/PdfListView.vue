<template>
  <div class="px-4 py-4">
    <h1 class="mb-4 section-title">فروشگاه PDF</h1>
    <LoadingSpinner v-if="loading" />
    <div v-else class="grid grid-cols-2 gap-2">
      <RouterLink
        v-for="item in items"
        :key="item.id"
        :to="`/pdfs/${item.id}`"
        class="card-soft overflow-hidden"
      >
        <div class="flex h-28 items-center justify-center bg-surface-page text-ink-muted">PDF</div>
        <div class="p-2.5">
          <p class="line-clamp-2 min-h-[2.5rem] text-xs font-bold">{{ item.title }}</p>
          <p class="price mt-1 text-xs">{{ formatPrice(item.price) }}</p>
        </div>
      </RouterLink>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import api from '../../api/client';
import LoadingSpinner from '../../components/LoadingSpinner.vue';

const items = ref([]);
const loading = ref(true);

function formatPrice(v) {
  return new Intl.NumberFormat('fa-IR').format(Number(v)) + ' ریال';
}

onMounted(async () => {
  try {
    const { data } = await api.get('/pdf-products');
    items.value = data.data?.data || data.data || [];
  } finally {
    loading.value = false;
  }
});
</script>
