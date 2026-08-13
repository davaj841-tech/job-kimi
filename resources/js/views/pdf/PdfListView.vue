<template>
  <div class="min-h-screen bg-surface-page dark:bg-slate-950">
    <div class="page-banner py-6 sm:py-8">
      <div class="mx-auto max-w-7xl px-4 text-right sm:text-center">
        <h1 class="page-title text-white sm:text-2xl">📄 فروشگاه منابع آموزشی</h1>
        <p class="mx-auto mt-1.5 max-w-2xl text-xs text-white/70 sm:text-sm">
          هر PDF جداگانه خریداری می‌شود — بدون اشتراک.
        </p>
        <div class="relative mx-auto mt-5 max-w-xl">
          <MagnifyingGlassIcon
            class="pointer-events-none absolute right-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400"
          />
          <input
            v-model="filters.search"
            type="search"
            placeholder="جستجو در PDFها…"
            class="w-full rounded-xl border border-white/20 bg-white/10 py-2.5 pl-4 pr-12 text-sm text-white placeholder-slate-400 backdrop-blur-sm outline-none ring-brand focus:ring-2"
            @input="debouncedSearch"
          />
        </div>
      </div>
    </div>

    <div
      class="sticky top-0 z-30 border-b border-surface-line bg-surface/95 lg:top-16"
    >
      <div class="mx-auto max-w-7xl px-4 py-3">
        <div class="scrollbar-hide flex items-center gap-2 overflow-x-auto">
          <button
            type="button"
            class="page-chip"
            :class="!filters.category ? 'page-chip-on' : ''"
            @click="filters.category = ''"
          >
            همه
          </button>
          <button
            v-for="cat in categories"
            :key="cat"
            type="button"
            class="page-chip"
            :class="filters.category === cat ? 'page-chip-on' : ''"
            @click="filters.category = cat"
          >
            {{ cat }}
          </button>
        </div>
      </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 py-5 sm:py-6">
      <div class="mb-4 flex items-center justify-between gap-3">
        <p class="text-sm text-desk-muted">
          {{ toFaDigits(pagination.total) }} منبع آموزشی
        </p>
        <select
          v-model="filters.sort"
          class="rounded-xl border border-surface-line bg-surface px-3 py-2 text-sm"
        >
          <option value="newest">جدیدترین</option>
          <option value="popular">پرفروش‌ترین</option>
          <option value="price_asc">ارزان‌ترین</option>
          <option value="price_desc">گران‌ترین</option>
        </select>
      </div>

      <div
        v-if="loading"
        class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-3"
      >
        <PdfCardSkeleton
          v-for="i in 8"
          :key="i"
        />
      </div>

      <div
        v-else
        class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-3"
      >
        <PdfCard
          v-for="pdf in pdfs"
          :key="pdf.id"
          :pdf="pdf"
          @click="$router.push(`/pdfs/${pdf.id}`)"
        />
      </div>

      <div
        v-if="!loading && !pdfs.length"
        class="py-16 text-center"
      >
        <DocumentIcon class="mx-auto mb-4 h-12 w-12 text-slate-300" />
        <p class="text-desk-muted">موردی یافت نشد</p>
      </div>

      <div
        v-if="hasMore && !loading"
        class="mt-6 text-center"
      >
        <button
          type="button"
          class="rounded-xl border border-surface-line bg-surface px-6 py-2.5 text-sm font-medium"
          :disabled="loadingMore"
          @click="loadMore"
        >
          {{ loadingMore ? '…' : 'بارگذاری بیشتر' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import { DocumentIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'
import api from '../../api/client'
import PdfCard from '../../components/pdf/PdfCard.vue'
import PdfCardSkeleton from '../../components/pdf/PdfCardSkeleton.vue'
import { toFaDigits, unwrapList, unwrapMeta } from '../../utils/format'

const pdfs = ref([])
const categories = ref([])
const loading = ref(true)
const loadingMore = ref(false)
const hasMore = ref(false)
const page = ref(1)
const pagination = reactive({ total: 0 })

const filters = reactive({
  search: '',
  category: '',
  sort: 'newest',
})

async function fetchPdfs(reset = false) {
  if (reset) {
    page.value = 1
    hasMore.value = true
  }
  const more = !reset && pdfs.value.length > 0
  if (more) loadingMore.value = true
  else loading.value = true

  try {
    const params = {
      page: page.value,
      per_page: 12,
      sort: filters.sort,
    }
    if (filters.search) params.search = filters.search
    if (filters.category) params.category = filters.category

    const { data } = await api.get('/pdf-products', { params })
    const list = unwrapList(data)
    const meta = unwrapMeta(data) || data?.data?.meta || {}
    const cats = data?.data?.categories || data?.categories || []
    if (Array.isArray(cats) && cats.length) categories.value = cats

    pagination.total = Number(meta.total ?? list.length)
    pdfs.value = reset ? list : [...pdfs.value, ...list]
    hasMore.value = Number(meta.current_page || page.value) < Number(meta.last_page || 1)
  } catch {
    if (reset) pdfs.value = []
    hasMore.value = false
  } finally {
    loading.value = false
    loadingMore.value = false
  }
}

function loadMore() {
  if (!hasMore.value || loadingMore.value) return
  page.value += 1
  fetchPdfs(false)
}

const debouncedSearch = useDebounceFn(() => fetchPdfs(true), 300)

watch(
  () => [filters.category, filters.sort],
  () => fetchPdfs(true),
)

onMounted(() => fetchPdfs(true))
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
