<template>
  <PageShell>
    <LoadingSpinner v-if="loading" />
    <template v-else-if="job">
      <h1 class="page-title mb-1 leading-8 sm:text-2xl">{{ job.title }}</h1>
      <div class="mb-2 flex items-center justify-between gap-2">
        <p class="text-sm text-desk-muted">
          {{ job.classification_name || job.company_name }}
          <span v-if="locationLine"> · {{ locationLine }}</span>
        </p>
        <button
          class="text-xs font-bold text-desk-orange"
          @click="shareOpen = true"
        >
          اشتراک‌گذاری
        </button>
      </div>
      <div
        class="card-soft mb-4 space-y-2 border border-surface-line p-4 text-sm"
      >
        <div>مهلت ثبت‌نام: {{ formatDate(job.registration_deadline) }}</div>
        <div>تاریخ آزمون: {{ formatDate(job.exam_date) }}</div>
        <div>
          طبقه‌بندی: {{ job.classification_name || job.company_name || '—' }}
        </div>
        <div v-if="job.seo_tag" class="text-xs text-desk-muted" dir="ltr">
          SEO: {{ job.seo_tag }}
        </div>
      </div>
      <div
        class="prose prose-sm mb-4 max-w-none text-sm leading-7 text-desk-text [&_table]:w-full [&_td]:border [&_td]:border-slate-300 [&_td]:p-2 [&_th]:border [&_th]:border-slate-300 [&_th]:bg-slate-50 [&_th]:p-2"
        v-html="job.description"
      />

      <div v-if="attachments.length" class="mb-4 space-y-2">
        <h2 class="text-sm font-black text-desk-text">فایل‌های مربوطه</h2>
        <a
          v-for="file in attachments"
          :key="file.id || file.url"
          :href="file.url"
          target="_blank"
          class="flex flex-col rounded-lg border border-desk-orange/40 px-3 py-2.5 text-sm"
        >
          <span class="font-bold text-desk-orange">{{
            file.title || 'دانلود فایل'
          }}</span>
          <span
            v-if="file.description"
            class="mt-0.5 text-xs text-desk-muted"
            >{{ file.description }}</span
          >
        </a>
      </div>
      <a
        v-else-if="job.attachment_url"
        :href="job.attachment_url"
        target="_blank"
        class="mb-3 inline-flex h-11 w-full items-center justify-center rounded-lg border border-desk-orange text-sm font-bold text-desk-orange"
      >
        دانلود فایل مربوطه
      </a>

      <a
        v-if="job.registration_link"
        :href="job.registration_link"
        target="_blank"
        class="btn-primary mb-5"
      >
        ثبت‌نام در سایت منبع
      </a>

      <section v-if="catalogExams.length" class="mb-5">
        <h2 class="mb-2 text-sm font-black text-desk-text">
          آزمون‌های مرتبط برای فروش
        </h2>
        <div class="space-y-2">
          <RouterLink
            v-for="exam in catalogExams"
            :key="exam.id"
            :to="`/exams/${exam.slug || exam.id}`"
            class="card-soft flex items-center justify-between border border-surface-line p-3 text-sm"
          >
            <span class="font-bold text-desk-text">{{ exam.title }}</span>
            <span class="text-xs text-desk-muted">{{
              exam.is_free ? 'رایگان' : formatPrice(exam.price)
            }}</span>
          </RouterLink>
        </div>
      </section>

      <section v-if="catalogPdfs.length" class="mb-5">
        <h2 class="mb-2 text-sm font-black text-desk-text">
          فایل‌های PDF مرتبط
        </h2>
        <div class="space-y-2">
          <RouterLink
            v-for="pdf in catalogPdfs"
            :key="pdf.id"
            :to="`/pdfs/${pdf.id}`"
            class="card-soft flex items-center justify-between border border-surface-line p-3 text-sm"
          >
            <span class="font-bold text-desk-text">{{ pdf.title }}</span>
            <span class="text-xs text-desk-muted">{{
              formatPrice(pdf.price)
            }}</span>
          </RouterLink>
        </div>
      </section>

      <ShareModal
        :open="shareOpen"
        :title="job.title"
        :description="job.classification_name || job.company_name || ''"
        :url="shareUrl"
        @close="shareOpen = false"
      />
    </template>
  </PageShell>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import api from '../../api/client'
import LoadingSpinner from '../../components/LoadingSpinner.vue'
import PageShell from '../../components/layout/PageShell.vue'
import ShareModal from '../../components/ShareModal.vue'
import { setJobPostMeta } from '../../services/meta'
import { formatDate, formatPrice } from '../../utils/format'

const route = useRoute()
const job = ref(null)
const loading = ref(true)
const shareOpen = ref(false)
const shareUrl = computed(
  () => `${window.location.origin}/jobs/${job.value?.id || ''}`
)

const locationLine = computed(() => {
  const j = job.value
  if (!j) return ''
  const provinces =
    Array.isArray(j.provinces) && j.provinces.length
      ? j.provinces.join('، ')
      : j.province
  return [j.city, provinces].filter(Boolean).join(' / ')
})

const attachments = computed(() => {
  const list = job.value?.attachments
  return Array.isArray(list) ? list.filter((a) => a?.url) : []
})

const catalogExams = computed(() => {
  const list = job.value?.catalog_exams
  return Array.isArray(list) ? list : []
})

const catalogPdfs = computed(() => {
  const list = job.value?.catalog_pdfs
  return Array.isArray(list) ? list : []
})

onMounted(async () => {
  try {
    const { data } = await api.get(`/job-posts/${route.params.id}`)
    job.value = data.data
    setJobPostMeta(job.value)
  } finally {
    loading.value = false
  }
})
</script>
