<template>
  <div class="min-h-screen bg-surface-page py-6 dark:bg-slate-950 sm:py-8">
    <div class="mx-auto max-w-7xl px-4">
      <div class="mb-6 flex items-center justify-between gap-3">
        <div>
          <h1 class="text-2xl font-black text-desk-text dark:text-white">
            کتابخانه من
          </h1>
          <p class="mt-1 text-sm text-desk-muted">
            PDFهای خریداری‌شده · دسترسی دائمی
          </p>
        </div>
        <RouterLink
          to="/pdfs"
          class="text-sm font-bold text-brand hover:underline"
          >فروشگاه</RouterLink
        >
      </div>

      <LoadingSpinner v-if="loading" />

      <EmptyState
        v-else-if="!items.length"
        title="هنوز PDFی خریداری نکرده‌اید"
        description="هر فایل جداگانه خریداری می‌شود و برای همیشه مال شماست."
      >
        <RouterLink to="/pdfs" class="btn-primary max-w-xs"
          >رفتن به فروشگاه</RouterLink
        >
      </EmptyState>

      <div
        v-else
        class="grid grid-cols-2 gap-4 md:grid-cols-3 md:gap-6 lg:grid-cols-4"
      >
        <div
          v-for="item in items"
          :key="item.id"
          class="group overflow-hidden rounded-2xl border border-surface-line bg-white dark:border-slate-800 dark:bg-slate-900"
        >
          <div
            class="relative aspect-[3/4] bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-800 dark:to-slate-700"
          >
            <img
              v-if="item.cover || item.thumbnail_url"
              :src="item.cover || item.thumbnail_url"
              :alt="item.title"
              class="h-full w-full object-cover"
            />
            <div
              class="absolute inset-0 flex items-center justify-center gap-2 bg-black/0 opacity-0 transition group-hover:bg-black/45 group-hover:opacity-100"
            >
              <button
                type="button"
                class="rounded-xl bg-white px-3 py-2 text-xs font-bold text-slate-900"
                @click="openViewer(item)"
              >
                مشاهده
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-1 rounded-xl bg-brand px-3 py-2 text-xs font-bold text-white"
                @click="download(item)"
              >
                <ArrowDownTrayIcon class="h-4 w-4" />
                دانلود
              </button>
            </div>
          </div>
          <div class="p-3">
            <h3 class="truncate text-sm font-bold">{{ item.title }}</h3>
            <p class="mt-1 text-xs text-desk-muted">
              خرید: {{ formatDate(item.purchase_date || item.purchased_at) }}
            </p>
          </div>
        </div>
      </div>

      <ErrorBanner :message="error" />
    </div>

    <PdfViewerModal
      v-model="showViewer"
      :source="viewerSource"
      :title="viewerTitle"
    />
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { ArrowDownTrayIcon } from '@heroicons/vue/24/outline'
import api from '../../api/client'
import EmptyState from '../../components/EmptyState.vue'
import ErrorBanner from '../../components/ErrorBanner.vue'
import LoadingSpinner from '../../components/LoadingSpinner.vue'
import PdfViewerModal from '../../components/pdf/PdfViewerModal.vue'
import { useToast } from '../../composables/useToast'
import { apiErrorMessage, formatDate, unwrapList } from '../../utils/format'

const toast = useToast()
const items = ref([])
const loading = ref(true)
const error = ref('')
const showViewer = ref(false)
const viewerSource = ref(null)
const viewerTitle = ref('')
let blobUrl = null

onMounted(async () => {
  try {
    const { data } = await api.get('/my-purchases')
    items.value = unwrapList(data)
  } catch (e) {
    error.value = apiErrorMessage(e)
  } finally {
    loading.value = false
  }
})

async function fetchBlob(id) {
  const { data } = await api.get(`/pdf-products/${id}/download`, {
    responseType: 'blob',
  })
  if (blobUrl) URL.revokeObjectURL(blobUrl)
  blobUrl = URL.createObjectURL(data)
  return blobUrl
}

async function openViewer(item) {
  try {
    viewerTitle.value = item.title || 'PDF'
    viewerSource.value = await fetchBlob(item.id)
    showViewer.value = true
  } catch (e) {
    toast.error(apiErrorMessage(e, 'مشاهده ممکن نشد.'))
  }
}

async function download(item) {
  try {
    const url = await fetchBlob(item.id)
    const a = document.createElement('a')
    a.href = url
    a.download = `${item.title || 'file'}.pdf`
    a.click()
  } catch (e) {
    toast.error(apiErrorMessage(e, 'دانلود ممکن نشد.'))
  }
}
</script>
