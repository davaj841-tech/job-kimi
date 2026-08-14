<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <h2 class="text-xl font-bold text-ink dark:text-white">تاریخچه آزمون</h2>
        <p class="text-sm text-ink-muted dark:text-slate-400">
          فیلتر، مرتب‌سازی و مقایسه نتایج
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <button
          type="button"
          class="rounded-xl border border-surface-line px-3 py-2 text-sm dark:border-slate-600 dark:text-slate-200"
          :disabled="compareIds.length !== 2"
          @click="compareOpen = true"
        >
          مقایسه ({{ compareIds.length }}/۲)
        </button>
        <button
          type="button"
          class="rounded-xl bg-brand px-3 py-2 text-sm font-bold text-white"
          @click="exportCsv"
        >
          خروجی CSV
        </button>
      </div>
    </div>

    <Card class="grid gap-3 p-4 sm:grid-cols-3">
      <div>
        <label class="mb-1 block text-xs text-ink-muted">جستجو</label>
        <input
          v-model="filters.q"
          class="input-field"
          placeholder="عنوان آزمون"
        />
      </div>
      <div>
        <label class="mb-1 block text-xs text-ink-muted">حداقل درصد</label>
        <input
          v-model.number="filters.minScore"
          type="number"
          min="0"
          max="100"
          class="input-field"
        />
      </div>
      <div>
        <label class="mb-1 block text-xs text-ink-muted">مرتب‌سازی</label>
        <select v-model="filters.sort" class="input-field">
          <option value="newest">جدیدترین</option>
          <option value="oldest">قدیمی‌ترین</option>
          <option value="score_desc">بالاترین نمره</option>
          <option value="score_asc">پایین‌ترین نمره</option>
        </select>
      </div>
    </Card>

    <div v-if="loading" class="space-y-2">
      <Skeleton v-for="i in 6" :key="i" class="h-14 rounded-xl" />
    </div>

    <Card v-else class="overflow-hidden">
      <EmptyState
        v-if="!paged.length"
        title="نتیجه‌ای یافت نشد"
        description="فیلترها را تغییر دهید یا آزمونی بدهید."
      />
      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[640px] text-sm">
          <thead>
            <tr
              class="border-b border-surface-line bg-slate-50 text-right text-ink-muted dark:border-slate-700 dark:bg-slate-800/80"
            >
              <th class="p-3 font-medium">مقایسه</th>
              <th class="p-3 font-medium">آزمون</th>
              <th class="p-3 font-medium">درصد</th>
              <th class="p-3 font-medium">نمره</th>
              <th class="p-3 font-medium">غلط</th>
              <th class="p-3 font-medium">بدون پاسخ</th>
              <th class="p-3 font-medium">تاریخ</th>
              <th class="p-3 font-medium"></th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in paged"
              :key="row.id"
              class="border-b border-surface-line/60 dark:border-slate-700/60"
            >
              <td class="p-3">
                <input
                  type="checkbox"
                  :checked="compareIds.includes(row.id)"
                  @change="toggleCompare(row.id)"
                />
              </td>
              <td class="p-3 font-medium dark:text-white">
                {{ row.exam_title }}
              </td>
              <td class="p-3">
                <Badge :variant="scoreVariant(row.percentage)">
                  {{ toFaDigits(Math.round(row.percentage || 0)) }}٪
                </Badge>
              </td>
              <td class="p-3 dark:text-slate-200">
                {{ toFaDigits(row.score ?? '—') }}
              </td>
              <td class="p-3">
                <RouterLink
                  v-if="row.total_wrong"
                  :to="`/exams/${row.exam_id}/result/${row.id}?filter=wrong`"
                  class="inline-flex rounded-lg bg-brand/10 px-2 py-1 text-xs font-bold text-brand"
                >
                  {{ toFaDigits(row.total_wrong) }} غلط
                </RouterLink>
                <span v-else class="text-ink-muted">۰</span>
              </td>
              <td class="p-3">
                <RouterLink
                  v-if="row.total_unanswered"
                  :to="`/exams/${row.exam_id}/result/${row.id}?filter=blank`"
                  class="inline-flex rounded-lg bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                >
                  {{ toFaDigits(row.total_unanswered) }} بدون پاسخ
                </RouterLink>
                <span v-else class="text-ink-muted">۰</span>
              </td>
              <td class="p-3 text-ink-muted">
                {{ formatDate(row.finished_at || row.created_at) }}
              </td>
              <td class="p-3">
                <RouterLink
                  :to="`/exams/${row.exam_id}/result/${row.id}`"
                  class="text-xs font-bold text-brand"
                >
                  جزئیات
                </RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div
        v-if="totalPages > 1"
        class="flex items-center justify-between border-t border-surface-line p-3 dark:border-slate-700"
      >
        <button
          type="button"
          class="rounded-lg px-3 py-1.5 text-sm disabled:opacity-40"
          :disabled="page <= 1"
          @click="page--"
        >
          قبلی
        </button>
        <span class="text-xs text-ink-muted"
          >صفحه {{ toFaDigits(page) }} از {{ toFaDigits(totalPages) }}</span
        >
        <button
          type="button"
          class="rounded-lg px-3 py-1.5 text-sm disabled:opacity-40"
          :disabled="page >= totalPages"
          @click="page++"
        >
          بعدی
        </button>
      </div>
    </Card>

    <div
      v-if="compareOpen"
      class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4"
      @click.self="compareOpen = false"
    >
      <Card class="max-h-[80vh] w-full max-w-lg overflow-y-auto p-5">
        <h3 class="mb-4 text-lg font-bold dark:text-white">مقایسه دو آزمون</h3>
        <div class="grid gap-4 sm:grid-cols-2">
          <div
            v-for="row in compared"
            :key="row.id"
            class="rounded-xl bg-slate-50 p-3 dark:bg-slate-700/40"
          >
            <p class="font-bold dark:text-white">{{ row.exam_title }}</p>
            <p class="mt-2 text-sm">
              درصد:
              <strong>{{ toFaDigits(Math.round(row.percentage || 0)) }}٪</strong>
            </p>
            <p class="text-sm">
              نمره: <strong>{{ toFaDigits(row.score ?? '—') }}</strong>
            </p>
            <p class="text-xs text-ink-muted">
              {{ formatDate(row.finished_at || row.created_at) }}
            </p>
          </div>
        </div>
        <button
          type="button"
          class="btn-primary mt-4"
          @click="compareOpen = false"
        >
          بستن
        </button>
      </Card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import api from '../../api/client'
import EmptyState from '../../components/EmptyState.vue'
import Badge from '../../components/ui/Badge.vue'
import Card from '../../components/ui/Card.vue'
import Skeleton from '../../components/ui/Skeleton.vue'
import { useToast } from '../../composables/useToast'
import { formatDate, toFaDigits, unwrapItem } from '../../utils/format'

const toast = useToast()
const loading = ref(true)
const rows = ref<any[]>([])
const page = ref(1)
const perPage = 10
const compareIds = ref<number[]>([])
const compareOpen = ref(false)

const filters = reactive({
  q: '',
  minScore: 0,
  sort: 'newest',
})

const filtered = computed(() => {
  let list = [...rows.value]
  const q = filters.q.trim()
  if (q) {
    list = list.filter((r) =>
      String(r.exam_title || '').includes(q)
    )
  }
  if (filters.minScore > 0) {
    list = list.filter((r) => Number(r.percentage || 0) >= filters.minScore)
  }
  list.sort((a, b) => {
    const da = new Date(a.finished_at || a.created_at || 0).getTime()
    const db = new Date(b.finished_at || b.created_at || 0).getTime()
    const sa = Number(a.percentage || 0)
    const sb = Number(b.percentage || 0)
    if (filters.sort === 'oldest') return da - db
    if (filters.sort === 'score_desc') return sb - sa
    if (filters.sort === 'score_asc') return sa - sb
    return db - da
  })
  return list
})

const totalPages = computed(() =>
  Math.max(1, Math.ceil(filtered.value.length / perPage))
)

const paged = computed(() => {
  const start = (page.value - 1) * perPage
  return filtered.value.slice(start, start + perPage)
})

const compared = computed(() =>
  rows.value.filter((r) => compareIds.value.includes(r.id))
)

watch(filters, () => {
  page.value = 1
})

function scoreVariant(pct: number) {
  if (pct >= 70) return 'success'
  if (pct >= 50) return 'warning'
  return 'danger'
}

function toggleCompare(id: number) {
  if (compareIds.value.includes(id)) {
    compareIds.value = compareIds.value.filter((x) => x !== id)
    return
  }
  if (compareIds.value.length >= 2) {
    toast.info('فقط دو آزمون قابل مقایسه است')
    return
  }
  compareIds.value = [...compareIds.value, id]
}

function exportCsv() {
  const header = 'title,percentage,score,date\n'
  const body = filtered.value
    .map((r) =>
      [
        JSON.stringify(r.exam_title || ''),
        r.percentage,
        r.score,
        r.finished_at || r.created_at || '',
      ].join(',')
    )
    .join('\n')
  const blob = new Blob([header + body], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'exam-history.csv'
  a.click()
  URL.revokeObjectURL(url)
}

onMounted(async () => {
  try {
    const { data } = await api.get('/user/exam-history', {
      params: { limit: 100 },
    })
    const payload = unwrapItem(data) as any
    rows.value = payload?.items || payload?.recent_attempts || []
    if (!rows.value.length) {
      const dash = await api.get('/dashboard')
      rows.value = (unwrapItem(dash.data) as any)?.recent_attempts || []
    }
  } catch {
    try {
      const dash = await api.get('/dashboard')
      rows.value = (unwrapItem(dash.data) as any)?.recent_attempts || []
    } catch {
      rows.value = []
    }
  } finally {
    loading.value = false
  }
})
</script>
