<template>
  <div
    class="home-2026 bg-surface-page"
    :class="`home-${layout}`"
  >
    <HomeHero :variant="layout" />
    <LatestEmployments
      :jobs="jobs"
      :classifications="classifications"
      :loading="loadingJobs"
    />
    <PlansSection
      :variant="plansVariant"
      :plans="plans"
      :loading="loadingPlans"
    />
    <ExamsSection
      :exams="exams"
      :loading="loadingExams"
      :error="examsError"
    />
    <FileStoreStrip
      :files="files"
      :loading="loadingFiles"
    />
    <HomeArticles
      :articles="articles"
      :posts="posts"
    />
    <ResumeFaqRow />
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { setPageMeta } from '../services/meta'
import api from '../api/client'
import ExamsSection from '../components/home/ExamsSection.vue'
import FileStoreStrip from '../components/home/FileStoreStrip.vue'
import HomeArticles from '../components/home/HomeArticles.vue'
import HomeHero from '../components/home/HomeHero.vue'
import LatestEmployments from '../components/home/LatestEmployments.vue'
import PlansSection from '../components/home/PlansSection.vue'
import ResumeFaqRow from '../components/home/ResumeFaqRow.vue'
import { useSiteTheme } from '../composables/useSiteTheme'
import { useScrollAnimations } from '../composables/useScrollAnimations'
import { unwrapList } from '../utils/format'

useScrollAnimations('.home-2026')

const { layout, plansVariant, ensureLoaded } = useSiteTheme()

const jobs = ref<any[]>([])
const classifications = ref<any[]>([])
const posts = ref<any[]>([])
const articles = ref<any[]>([])
const exams = ref<any[]>([])
const examsError = ref('')
const files = ref<any[]>([])
const plans = ref<any[]>([])
const loadingJobs = ref(true)
const loadingExams = ref(true)
const loadingFiles = ref(true)
const loadingPlans = ref(true)

onMounted(async () => {
  setPageMeta({
    title: 'جاب‌آزمون | آمادگی استخدام',
    description:
      'آزمون‌های استخدامی، آگهی‌های شغلی، فروشگاه PDF و رزومه‌ساز جاب‌آزمون',
    url: typeof window !== 'undefined' ? window.location.href : undefined,
    type: 'website',
  } as any)

  await ensureLoaded()

  const [jobsRes, filtersRes, blogRes, articlesRes, filesRes, examsRes, plansRes] =
    await Promise.all([
      api.get('/job-posts', { params: { per_page: 12 } }).catch(() => null),
      api.get('/job-posts/filters').catch(() => null),
      api.get('/blog-posts', { params: { per_page: 8 } }).catch(() => null),
      api.get('/articles', { params: { per_page: 8 } }).catch(() => null),
      api.get('/pdf-products', { params: { per_page: 12 } }).catch(() => null),
      api.get('/exams', { params: { per_page: 12 } }).catch((e) => ({ __error: e })),
      api.get('/subscription-plans').catch(() => null),
    ])

  jobs.value = unwrapList(jobsRes?.data)
  const filtersPayload = filtersRes?.data?.data || filtersRes?.data || {}
  classifications.value = filtersPayload.home_classifications || []
  posts.value = unwrapList(blogRes?.data)
  articles.value = unwrapList(articlesRes?.data)
  files.value = unwrapList(filesRes?.data)
  plans.value = unwrapList(plansRes?.data)

  if ((examsRes as any)?.__error) {
    examsError.value =
      (examsRes as any).__error?.response?.data?.message ||
      'بارگذاری آزمون‌ها ناموفق بود.'
    exams.value = []
  } else {
    examsError.value = ''
    exams.value = unwrapList((examsRes as any)?.data)
  }

  loadingJobs.value = false
  loadingFiles.value = false
  loadingExams.value = false
  loadingPlans.value = false
})
</script>
