<template>
  <div class="min-h-screen bg-surface-page dark:bg-slate-950">
    <div
      class="bg-white/92 sticky top-[calc(3.65rem+env(safe-area-inset-top))] z-20 border-b border-surface-line backdrop-blur-md lg:top-[4.5rem]"
    >
      <div class="mx-auto max-w-7xl px-4 py-3">
        <div class="mb-2 flex items-center justify-between gap-2">
          <h1 class="page-title">آگهی‌های شغلی</h1>
        </div>

        <div
          class="scrollbar-hide flex items-center gap-2 overflow-x-auto pb-1"
        >
          <div class="relative w-56 flex-shrink-0 sm:w-64">
            <MagnifyingGlassIcon
              class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-desk-muted"
            />
            <input
              v-model="filters.search"
              type="search"
              placeholder="عنوان شغل، سازمان..."
              class="w-full rounded-xl border-0 bg-slate-100 py-2 pl-3 pr-9 text-sm outline-none ring-brand focus:ring-2 dark:bg-slate-800 dark:text-white"
              @input="debouncedSearch"
            />
          </div>

          <button
            type="button"
            class="page-chip"
            :class="!filters.job_classification_id ? 'page-chip-on' : ''"
            @click="filters.job_classification_id = ''"
          >
            همه
          </button>

          <button
            v-for="cat in classifications"
            :key="cat.id"
            type="button"
            class="page-chip"
            :class="
              String(filters.job_classification_id) === String(cat.id)
                ? 'page-chip-on'
                : ''
            "
            @click="toggleCategory(cat.id)"
          >
            {{ cat.name }}
          </button>

          <button
            type="button"
            class="flex flex-shrink-0 items-center gap-1 rounded-xl bg-slate-100 p-2 dark:bg-slate-800 lg:px-3"
            :class="showFilters ? 'ring-2 ring-brand' : ''"
            @click="showFilters = !showFilters"
          >
            <FunnelIcon class="h-5 w-5 text-desk-muted" />
            <span class="hidden text-xs font-medium text-desk-muted lg:inline"
              >فیلتر</span
            >
          </button>
        </div>

        <div
          v-show="showFilters"
          class="mt-3 grid grid-cols-2 gap-2 border-t border-surface-line pt-3 dark:border-slate-800 md:grid-cols-3"
        >
          <select
            v-model="filters.province"
            class="rounded-xl border-0 bg-slate-100 px-3 py-2 text-sm dark:bg-slate-800 dark:text-white"
          >
            <option value="">همه استان‌ها</option>
            <option v-for="p in provinceOptions" :key="p" :value="p">
              {{ p }}
            </option>
          </select>
          <select
            v-model="filters.employment_type"
            class="rounded-xl border-0 bg-slate-100 px-3 py-2 text-sm dark:bg-slate-800 dark:text-white"
          >
            <option value="">همه انواع</option>
            <option value="full_time">تمام‌وقت</option>
            <option value="part_time">پاره‌وقت</option>
            <option value="remote">دورکاری</option>
            <option value="contract">قراردادی</option>
            <option value="internship">کارآموزی</option>
            <option value="military">امریه</option>
          </select>
          <select
            v-model="filters.sort"
            class="col-span-2 rounded-xl border-0 bg-slate-100 px-3 py-2 text-sm dark:bg-slate-800 dark:text-white md:col-span-1"
          >
            <option value="newest">جدیدترین</option>
            <option value="deadline">نزدیک به مهلت</option>
          </select>
        </div>

        <div class="mt-2 text-xs text-desk-muted">
          {{ toFaDigits(pagination.total) }} آگهی یافت شد
          <span v-if="filters.search" class="mr-1"
            >برای «{{ filters.search }}»</span
          >
        </div>
      </div>
    </div>

    <div class="mx-auto max-w-7xl space-y-2.5 px-4 py-5">
      <div v-if="loading" class="space-y-2.5">
        <JobCardSkeleton v-for="i in 5" :key="i" />
      </div>

      <EmptyState
        v-else-if="error"
        title="خطا در بارگذاری آگهی‌ها"
        :description="error"
        icon="⚠️"
      >
        <button
          type="button"
          class="mt-2 rounded-xl bg-brand px-4 py-2 text-xs font-bold text-white"
          @click="fetchJobs(true)"
        >
          تلاش مجدد
        </button>
      </EmptyState>

      <div v-else class="space-y-2.5">
        <JobCardCompact
          v-for="job in jobs"
          :key="job.id"
          :job="job"
          @bookmark="toggleBookmark"
          @click="openDetail(job)"
        />
      </div>

      <div
        v-if="!loading && !error && jobs.length === 0"
        class="py-16 text-center"
      >
        <div
          class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800"
        >
          <MagnifyingGlassIcon class="h-7 w-7 text-desk-muted" />
        </div>
        <p class="text-sm text-desk-muted">آگهی‌ای یافت نشد</p>
        <button
          type="button"
          class="mt-2 text-sm font-bold text-brand"
          @click="resetFilters"
        >
          پاک کردن فیلترها
        </button>
      </div>

      <div
        v-if="loadMoreError"
        class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-center text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-200"
      >
        <p>{{ loadMoreError }}</p>
        <button
          type="button"
          class="mt-2 text-xs font-bold text-brand"
          @click="loadMore"
        >
          تلاش مجدد
        </button>
      </div>

      <div v-if="hasMore && !loading && !error" class="pt-3 text-center">
        <button
          type="button"
          class="rounded-xl border border-surface-line bg-surface px-6 py-2.5 text-sm font-medium transition hover:bg-surface-page disabled:opacity-60"
          :disabled="loadingMore"
          @click="loadMore"
        >
          {{ loadingMore ? 'در حال بارگذاری...' : 'بارگذاری بیشتر' }}
        </button>
      </div>
    </div>

    <JobDetailDrawer v-model="selectedJob" @bookmark="onDrawerBookmark" />
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { FunnelIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'
import JobCardCompact from '../../components/jobs/JobCardCompact.vue'
import JobCardSkeleton from '../../components/jobs/JobCardSkeleton.vue'
import JobDetailDrawer from '../../components/jobs/JobDetailDrawer.vue'
import EmptyState from '../../components/EmptyState.vue'
import { useJobList } from '../../composables/useJobList'
import { IRAN_PROVINCES } from '../../utils/provinces'
import { toFaDigits } from '../../utils/format'

const showFilters = ref(false)
const selectedJob = ref(null)

const {
  jobs,
  loading,
  loadingMore,
  hasMore,
  error,
  loadMoreError,
  filters,
  pagination,
  classifications,
  provinces,
  fetchJobs,
  loadMore,
  debouncedSearch,
  toggleCategory,
  resetFilters,
  toggleBookmark,
} = useJobList()

const provinceOptions = computed(() => {
  const fromApi = provinces.value || []
  if (fromApi.length) return fromApi
  return IRAN_PROVINCES
})

function openDetail(job) {
  selectedJob.value = job
}

function onDrawerBookmark(id) {
  toggleBookmark(id)
  if (selectedJob.value?.id === id) {
    selectedJob.value = {
      ...selectedJob.value,
      is_bookmarked: !selectedJob.value.is_bookmarked,
    }
  }
}
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}
.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
