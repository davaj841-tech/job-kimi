import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useDebounceFn, useInfiniteScroll } from '@vueuse/core'
import api from '../api/client'
import { unwrapList, unwrapMeta } from '../utils/format'

const BOOKMARK_KEY = 'ja_job_bookmarks'

type JobRow = Record<string, unknown> & { id: number | string }

function readBookmarks(): number[] {
  try {
    const raw = localStorage.getItem(BOOKMARK_KEY)
    const parsed = raw ? JSON.parse(raw) : []
    return Array.isArray(parsed) ? parsed.map(Number) : []
  } catch {
    return []
  }
}

function writeBookmarks(ids: number[]) {
  localStorage.setItem(BOOKMARK_KEY, JSON.stringify(ids))
}

/**
 * Compact job listings — maps to GET /api/v1/job-posts
 */
export function useJobList() {
  const jobs = ref<JobRow[]>([])
  const page = ref(1)
  const loading = ref(false)
  const loadingMore = ref(false)
  const hasMore = ref(true)
  const pagination = reactive({
    total: 0,
    current_page: 1,
    last_page: 1,
    per_page: 15,
  })

  const filters = reactive({
    search: '',
    job_classification_id: '',
    province: '',
    employment_type: '',
    sort: 'newest',
  })

  const classifications = ref<Array<{ id: number | string; name: string }>>([])
  const provinces = ref<string[]>([])
  const bookmarkedIds = ref<number[]>(readBookmarks())

  function withBookmarkFlag(list: JobRow[]) {
    const set = new Set(bookmarkedIds.value)
    return list.map((j) => ({
      ...j,
      is_bookmarked: set.has(Number(j.id)),
    }))
  }

  async function loadFilterOptions() {
    try {
      const { data } = await api.get('/job-posts/filters')
      const payload = data?.data ?? data ?? {}
      classifications.value = payload.classifications || payload.home_classifications || []
      provinces.value = payload.provinces || []
    } catch {
      classifications.value = []
      provinces.value = []
    }
  }

  async function fetchJobs(reset = false) {
    if (reset) {
      page.value = 1
      hasMore.value = true
    }

    const isMore = !reset && jobs.value.length > 0
    if (isMore) loadingMore.value = true
    else loading.value = true

    try {
      const params: Record<string, string | number> = {
        page: page.value,
        per_page: pagination.per_page,
        sort: filters.sort || 'newest',
      }
      if (filters.search) params.search = filters.search
      if (filters.job_classification_id) {
        params.job_classification_id = filters.job_classification_id
      }
      if (filters.province) params.province = filters.province
      if (filters.employment_type) params.employment_type = filters.employment_type

      const { data } = await api.get('/job-posts', { params })
      const list = unwrapList(data) as JobRow[]
      const meta = unwrapMeta(data) || {}

      pagination.total = Number(meta.total ?? list.length)
      pagination.current_page = Number(meta.current_page ?? page.value)
      pagination.last_page = Number(meta.last_page ?? 1)
      pagination.per_page = Number(meta.per_page ?? 15)

      const flagged = withBookmarkFlag(list)
      jobs.value = reset ? flagged : [...jobs.value, ...flagged]
      hasMore.value = pagination.current_page < pagination.last_page
    } catch {
      if (reset) jobs.value = []
      hasMore.value = false
    } finally {
      loading.value = false
      loadingMore.value = false
    }
  }

  function loadMore() {
    if (!hasMore.value || loadingMore.value || loading.value) return
    page.value += 1
    fetchJobs(false)
  }

  const debouncedSearch = useDebounceFn(() => fetchJobs(true), 300)

  function toggleCategory(id: number | string) {
    const next = String(id)
    filters.job_classification_id =
      String(filters.job_classification_id) === next ? '' : next
  }

  function resetFilters() {
    filters.search = ''
    filters.job_classification_id = ''
    filters.province = ''
    filters.employment_type = ''
    filters.sort = 'newest'
    fetchJobs(true)
  }

  function toggleBookmark(id: number | string) {
    const n = Number(id)
    const set = new Set(bookmarkedIds.value)
    if (set.has(n)) set.delete(n)
    else set.add(n)
    bookmarkedIds.value = [...set]
    writeBookmarks(bookmarkedIds.value)
    jobs.value = withBookmarkFlag(jobs.value)
  }

  watch(
    () => [
      filters.job_classification_id,
      filters.province,
      filters.employment_type,
      filters.sort,
    ],
    () => fetchJobs(true),
  )

  onMounted(async () => {
    await loadFilterOptions()
    await fetchJobs(true)

    if (typeof window !== 'undefined') {
      useInfiniteScroll(
        window,
        () => loadMore(),
        {
          distance: 320,
          canLoadMore: () => hasMore.value && !loadingMore.value && !loading.value,
        },
      )
    }
  })

  return {
    jobs,
    loading,
    loadingMore,
    hasMore,
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
    totalLabel: computed(() => pagination.total),
  }
}
