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

useScrollAnimations('.home-2026')

const { layout, plansVariant, ensureLoaded } = useSiteTheme()

const CACHE_KEY = 'ja_home_feed_v3'

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

function applyFeed(data: any) {
  jobs.value = data.jobs || []
  classifications.value = data.classifications || []
  posts.value = data.blog_posts || []
  articles.value = data.articles || []
  exams.value = data.exams || []
  files.value = data.files || []
  plans.value = data.plans || []
  examsError.value = ''
  loadingJobs.value = false
  loadingExams.value = false
  loadingFiles.value = false
  loadingPlans.value = false
}

function hydrateFromCache() {
  try {
    const raw = sessionStorage.getItem(CACHE_KEY)
    if (!raw) return false
    const cached = JSON.parse(raw)
    if (!cached?.data || Date.now() - (cached.at || 0) > 90_000) return false
    applyFeed(cached.data)
    return true
  } catch {
    return false
  }
}

function persistCache(data: any) {
  try {
    sessionStorage.setItem(CACHE_KEY, JSON.stringify({ at: Date.now(), data }))
  } catch {
    /* ignore */
  }
}

onMounted(() => {
  setPageMeta({
    title: 'جاب‌آزمون | آمادگی استخدام',
    description:
      'آزمون‌های استخدامی، آگهی‌های شغلی، فروشگاه PDF و رزومه‌ساز جاب‌آزمون',
    url: typeof window !== 'undefined' ? window.location.href : undefined,
    type: 'website',
  } as any)

  hydrateFromCache()
  void ensureLoaded()

  void api
    .get('/home-feed')
    .then(({ data }) => {
      const feed = data?.data || {}
      applyFeed(feed)
      persistCache(feed)
    })
    .catch(() => {
      // fallback: parallel calls if aggregate fails
      void Promise.all([
        api.get('/job-posts', { params: { per_page: 12 } }).catch(() => null),
        api.get('/job-posts/filters').catch(() => null),
        api.get('/blog-posts', { params: { per_page: 8 } }).catch(() => null),
        api.get('/articles', { params: { per_page: 8 } }).catch(() => null),
        api.get('/pdf-products', { params: { per_page: 12 } }).catch(() => null),
        api.get('/exams', { params: { per_page: 12 } }).catch((e) => ({ __error: e })),
        api.get('/subscription-plans').catch(() => null),
      ]).then(([jobsRes, filtersRes, blogRes, articlesRes, filesRes, examsRes, plansRes]) => {
        const unwrap = (payload: any) =>
          payload?.data?.data ?? payload?.data ?? []
        const list = (payload: any) => {
          const v = unwrap(payload)
          return Array.isArray(v) ? v : v?.data || []
        }
        jobs.value = list(jobsRes)
        classifications.value =
          filtersRes?.data?.data?.home_classifications ||
          filtersRes?.data?.data?.classifications ||
          []
        posts.value = list(blogRes)
        articles.value = list(articlesRes)
        files.value = list(filesRes)
        plans.value = list(plansRes)
        if ((examsRes as any)?.__error) {
          examsError.value = 'بارگذاری آزمون‌ها ناموفق بود.'
          exams.value = []
        } else {
          exams.value = list(examsRes)
        }
        loadingJobs.value = false
        loadingExams.value = false
        loadingFiles.value = false
        loadingPlans.value = false
      })
    })
})
</script>
