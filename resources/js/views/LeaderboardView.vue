<template>
  <div class="mx-auto max-w-2xl px-4 py-6">
    <h1 class="mb-4 text-xl font-black">جدول رتبه‌بندی</h1>
    <div class="mb-4 flex gap-2">
      <button
        v-for="t in tabs"
        :key="t.value"
        class="tab"
        :class="period === t.value ? 'active' : ''"
        @click="switchTab(t.value)"
      >
        {{ t.label }}
      </button>
    </div>
    <SkeletonCard v-if="loading" :count="5" />
    <div v-else class="space-y-2">
      <div
        v-for="row in rows"
        :key="row.user_id"
        class="card-soft flex items-center gap-3 p-3"
      >
        <span
          class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-soft text-sm font-black text-brand"
          >{{ toFaDigits(row.rank) }}</span
        >
        <div class="flex-1">
          <p class="text-sm font-bold">{{ row.name }}</p>
          <p class="text-xs text-ink-muted">
            {{ toFaDigits(row.attempts) }} آزمون
          </p>
        </div>
        <p class="text-sm font-black text-brand">
          {{ toFaDigits(Math.round(row.total_score)) }}
        </p>
      </div>
      <p v-if="!rows.length" class="py-10 text-center text-sm text-ink-muted">
        هنوز رتبه‌ای ثبت نشده
      </p>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import api from '../api/client'
import SkeletonCard from '../components/ui/SkeletonCard.vue'
import { toFaDigits, unwrapList } from '../utils/format'

const period = ref('all')
const rows = ref([])
const loading = ref(true)
const tabs = [
  { value: 'all', label: 'کل' },
  { value: 'week', label: 'این هفته' },
]

onMounted(() => load())
async function switchTab(v) {
  period.value = v
  await load()
}
async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/leaderboard', {
      params: { period: period.value },
    })
    rows.value = unwrapList(data)
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.tab {
  @apply rounded-xl bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600;
}
.tab.active {
  @apply bg-brand text-white;
}
</style>
