<template>
  <div class="mx-auto max-w-2xl px-4 py-6">
    <div class="mb-4 flex items-center justify-between">
      <h1 class="text-xl font-black">پشتیبانی</h1>
      <RouterLink to="/support/new" class="btn-primary max-w-[140px] text-sm">تیکت جدید</RouterLink>
    </div>
    <SkeletonCard v-if="loading" :count="3" />
    <div v-else class="space-y-2">
      <RouterLink
        v-for="t in tickets"
        :key="t.id"
        :to="`/support/${t.id}`"
        class="card-soft block p-3"
      >
        <div class="flex items-center justify-between gap-2">
          <p class="text-sm font-bold">{{ t.subject }}</p>
          <span class="rounded-full px-2 py-0.5 text-[10px] font-bold" :class="t.status === 'open' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'">
            {{ t.status === 'open' ? 'باز' : 'بسته' }}
          </span>
        </div>
        <p class="mt-1 line-clamp-1 text-xs text-ink-muted">{{ t.message }}</p>
      </RouterLink>
      <p v-if="!tickets.length" class="py-10 text-center text-sm text-ink-muted">تیکتی ندارید</p>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import api from '../../api/client';
import SkeletonCard from '../../components/ui/SkeletonCard.vue';
import { unwrapList } from '../../utils/format';

const loading = ref(true);
const tickets = ref([]);

onMounted(async () => {
  try {
    const { data } = await api.get('/tickets');
    tickets.value = unwrapList(data);
  } finally {
    loading.value = false;
  }
});
</script>
