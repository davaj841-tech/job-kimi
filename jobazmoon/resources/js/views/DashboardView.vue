<template>
  <div class="px-4 py-4">
    <PageHeader title="داشبورد" subtitle="وضعیت آمادگی شما" />
    <SkeletonCard v-if="loading" :count="3" />
    <template v-else>
      <div class="mb-4 grid grid-cols-2 gap-2">
        <div class="card-soft p-3">
          <p class="text-[11px] text-ink-muted">تلاش‌های اخیر</p>
          <p class="mt-1 text-xl font-black">{{ toFaDigits(stats.attempts) }}</p>
        </div>
        <div class="card-soft p-3">
          <p class="text-[11px] text-ink-muted">موجودی</p>
          <p class="mt-1 text-sm font-black text-brand">{{ formatPrice(stats.balance) }}</p>
        </div>
      </div>

      <h2 class="mb-2 text-sm font-bold">آخرین نتایج</h2>
      <EmptyState v-if="!recent.length" title="هنوز آزمونی نداده‌اید" />
      <div v-else class="space-y-2">
        <div v-for="item in recent" :key="item.id" class="card-soft p-3 text-sm">
          <p class="font-bold">{{ item.exam?.title || 'آزمون' }}</p>
          <p class="mt-1 text-xs text-ink-muted">
            نمره {{ toFaDigits(item.score ?? '—') }} · {{ formatDate(item.finished_at || item.created_at) }}
          </p>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import api from '../api/client';
import EmptyState from '../components/EmptyState.vue';
import PageHeader from '../components/PageHeader.vue';
import SkeletonCard from '../components/ui/SkeletonCard.vue';
import { formatDate, formatPrice, toFaDigits, unwrapItem, unwrapList } from '../utils/format';

const loading = ref(true);
const recent = ref([]);
const stats = reactive({ attempts: 0, balance: 0 });

onMounted(async () => {
  try {
    const [dash, wallet] = await Promise.all([
      api.get('/dashboard').catch(() => null),
      api.get('/wallet').catch(() => null),
    ]);

    const dashData = unwrapItem(dash?.data) || {};
    recent.value = unwrapList({ data: dashData.recent_attempts || dashData.attempts || [] });
    stats.attempts = recent.value.length || dashData.attempts_count || 0;
    stats.balance = unwrapItem(wallet?.data)?.balance || 0;
  } finally {
    loading.value = false;
  }
});
</script>
