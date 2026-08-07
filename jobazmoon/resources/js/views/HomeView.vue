<template>
  <div>
    <!-- Mobile home -->
    <div class="lg:hidden">
      <section class="relative h-[200px] overflow-hidden bg-gradient-to-l from-desk-dark to-desk-blue px-4 pb-5 pt-5 text-white">
        <div class="pointer-events-none absolute -left-8 top-0 h-36 w-36 rounded-full bg-white/5" />
        <div class="pointer-events-none absolute -bottom-10 -right-6 h-32 w-32 rounded-full bg-desk-orange/15" />
        <div class="relative flex h-full flex-col justify-between text-right">
          <div>
            <h1 class="text-[22px] font-bold leading-snug text-white">آمادگی امروز</h1>
            <p class="text-[22px] font-bold leading-snug text-desk-orange">استخدام فردا</p>
            <p class="mt-2 max-w-[17rem] text-sm text-white/70">
              آزمون‌های استخدامی، رزومه‌ساز، آگهی
            </p>
          </div>
          <div class="flex gap-2">
            <RouterLink
              to="/exams"
              class="inline-flex flex-1 items-center justify-center rounded-lg bg-desk-orange px-3 py-2.5 text-sm font-bold text-white"
            >
              شروع آزمون
            </RouterLink>
            <RouterLink
              to="/jobs"
              class="inline-flex flex-1 items-center justify-center rounded-lg border border-white px-3 py-2.5 text-sm font-bold text-white"
            >
              آگهی‌ها
            </RouterLink>
          </div>
        </div>
      </section>

      <section class="relative z-10 -mt-5 px-4">
        <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
          <div
            v-for="item in mobileStats"
            :key="item.label"
            class="w-[100px] shrink-0 rounded-xl bg-white p-3 text-center shadow-sm"
          >
            <span
              class="mx-auto mb-2 flex h-9 w-9 items-center justify-center rounded-full"
              :class="item.bg"
            >
              <DesktopIcon :name="item.icon" :size="18" :class="item.color" />
            </span>
            <p class="text-lg font-bold text-desk-text">{{ item.value }}</p>
            <p class="mt-0.5 text-[11px] text-desk-muted">{{ item.label }}</p>
          </div>
        </div>
      </section>

      <section class="mt-4 px-4">
        <BannerSlider position="home_top" />
      </section>

      <section class="mt-6 px-4">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="section-title">آخرین استخدام‌ها</h2>
          <RouterLink to="/jobs" class="mobile-section-link">مشاهده همه</RouterLink>
        </div>
        <div class="mb-3">
          <JobClassificationFilter v-model="selectedClassification" :items="classifications" />
        </div>
        <LoadingSpinner v-if="loadingJobs" />
        <div v-else class="space-y-2">
          <ContentCard
            v-for="job in filteredJobs"
            :key="job.id"
            :title="job.title"
            :subtitle="job.classification_name || job.company_name"
            :meta="jobLocation(job)"
            :badge="job.is_featured ? 'ویژه' : ''"
            @click="$router.push(`/jobs/${job.id}`)"
          />
          <p v-if="!filteredJobs.length" class="py-8 text-center text-sm text-desk-muted">آگهی‌ای یافت نشد.</p>
        </div>
      </section>

      <section class="mt-6 px-4">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="section-title">آزمون‌های پربازدید</h2>
          <RouterLink to="/exams" class="mobile-section-link">مشاهده همه</RouterLink>
        </div>
        <LoadingSpinner v-if="loadingExams" />
        <div v-else class="space-y-2">
          <ContentCard
            v-for="exam in exams"
            :key="exam.id"
            :title="exam.title"
            :subtitle="exam.is_free ? 'رایگان' : null"
            :meta="exam.duration_minutes ? `${exam.duration_minutes} دقیقه` : ''"
            :badge="exam.is_free ? 'رایگان' : ''"
            @click="goExam(exam)"
          />
          <p v-if="!exams.length" class="py-6 text-center text-sm text-desk-muted">
            📭 هنوز آزمونی منتشر نشده است. به‌زودی آزمون‌های جدید اضافه می‌شود.
          </p>
        </div>
      </section>

      <section class="mt-6 px-4 pb-6">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="section-title">از بلاگ</h2>
          <RouterLink to="/blog" class="mobile-section-link">مشاهده همه</RouterLink>
        </div>
        <div class="space-y-2">
          <RouterLink
            v-for="post in posts"
            :key="post.id"
            :to="`/blog/${post.slug}`"
            class="card-soft block border border-surface-line p-3"
          >
            <p class="mobile-card-title line-clamp-2">{{ post.title }}</p>
            <p class="mobile-caption mt-1 line-clamp-2">{{ post.excerpt }}</p>
          </RouterLink>
          <p v-if="!posts.length" class="py-6 text-center text-sm text-desk-muted">مقاله‌ای یافت نشد.</p>
        </div>
      </section>
    </div>

    <!-- Desktop home -->
    <div class="hidden lg:block">
      <HeroSection />
      <div class="desk-container py-4">
        <BannerSlider position="home_top" />
      </div>
      <StatsBar
        :jobs-count="jobs.length"
        :exams-count="exams.length"
        :files-count="files.length"
        :posts-count="posts.length"
      />
      <JobsSection :jobs="jobs" :classifications="classifications" :loading="loadingJobs" />
      <div class="desk-container py-4">
        <BannerSlider position="home_middle" />
      </div>
      <PlansSection :plans="plans" :loading="loadingPlans" />
      <ExamsSection :exams="exams" :loading="loadingExams" />
      <FilesSection :files="files" :loading="loadingFiles" />
      <ResumeBanner />
      <TestimonialsSection />
      <BlogSection :posts="posts" />
      <FAQSection />
      <NewsletterSection />
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { setPageMeta } from '../services/meta';
import api from '../api/client';
import BlogSection from '../components/home/BlogSection.vue';
import ExamsSection from '../components/home/ExamsSection.vue';
import FAQSection from '../components/home/FAQSection.vue';
import FilesSection from '../components/home/FilesSection.vue';
import HeroSection from '../components/home/HeroSection.vue';
import JobsSection from '../components/home/JobsSection.vue';
import NewsletterSection from '../components/home/NewsletterSection.vue';
import PlansSection from '../components/home/PlansSection.vue';
import ResumeBanner from '../components/home/ResumeBanner.vue';
import StatsBar from '../components/home/StatsBar.vue';
import TestimonialsSection from '../components/home/TestimonialsSection.vue';
import ContentCard from '../components/ContentCard.vue';
import BannerSlider from '../components/BannerSlider.vue';
import DesktopIcon from '../components/DesktopIcon.vue';
import JobClassificationFilter from '../components/JobClassificationFilter.vue';
import LoadingSpinner from '../components/LoadingSpinner.vue';
import { toFaDigits, unwrapList } from '../utils/format';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const router = useRouter();

const jobs = ref([]);
const classifications = ref([]);
const allClassifications = ref([]);
const selectedClassification = ref(null);
const posts = ref([]);
const plans = ref([]);
const exams = ref([]);
const files = ref([]);
const loadingJobs = ref(true);
const loadingPlans = ref(true);
const loadingExams = ref(true);
const loadingFiles = ref(true);

const filteredJobs = computed(() => {
  const list = jobs.value || [];
  if (!selectedClassification.value) return list.slice(0, 6);
  const parent = classifications.value.find((c) => Number(c.id) === Number(selectedClassification.value));
  const ids = new Set([
    Number(selectedClassification.value),
    ...((parent?.child_ids || []).map(Number)),
  ]);
  return list.filter((j) => ids.has(Number(j.job_classification_id))).slice(0, 6);
});

function jobLocation(job) {
  const provinces = Array.isArray(job.provinces) && job.provinces.length
    ? job.provinces.join('، ')
    : job.province;
  return [job.city, provinces].filter(Boolean).join('، ');
}

function goExam(exam) {
  const slug = exam.slug || exam.id;
  const path = `/exams/${slug}`;
  if (!auth.isAuthenticated) {
    router.push({ path: '/login', query: { redirect: path } });
    return;
  }
  router.push(path);
}

const mobileStats = computed(() => [
  {
    label: 'آگهی',
    value: toFaDigits(jobs.value.length || '—'),
    icon: 'briefcase',
    bg: 'bg-blue-50',
    color: 'text-desk-blue',
  },
  {
    label: 'آزمون',
    value: toFaDigits(exams.value.length || '—'),
    icon: 'users',
    bg: 'bg-orange-50',
    color: 'text-desk-orange',
  },
  {
    label: 'فایل',
    value: toFaDigits(files.value.length || '—'),
    icon: 'file',
    bg: 'bg-emerald-50',
    color: 'text-desk-green',
  },
  {
    label: 'مقاله',
    value: toFaDigits(posts.value.length || '—'),
    icon: 'clipboard',
    bg: 'bg-violet-50',
    color: 'text-violet-600',
  },
]);

onMounted(async () => {
  setPageMeta({
    title: 'جاب‌آزمون | آمادگی استخدام',
    description: 'آزمون‌های استخدامی، آگهی‌های شغلی، فروشگاه PDF و رزومه‌ساز هوشمند جاب‌آزمون',
    url: typeof window !== 'undefined' ? window.location.href : undefined,
    type: 'website',
  });
  const [jobsRes, filtersRes, blogRes, plansRes, filesRes, examsRes] = await Promise.all([
    api.get('/job-posts', { params: { per_page: 12 } }).catch(() => null),
    api.get('/job-posts/filters').catch(() => null),
    api.get('/blog-posts', { params: { per_page: 4 } }).catch(() => null),
    api.get('/subscription-plans').catch(() => null),
    api.get('/pdf-products', { params: { per_page: 4 } }).catch(() => null),
    api.get('/exams', { params: { per_page: 4 } }).catch(() => null),
  ]);

  jobs.value = unwrapList(jobsRes?.data);
  const filtersPayload = filtersRes?.data?.data || filtersRes?.data || {};
  classifications.value = filtersPayload.home_classifications || [];
  allClassifications.value = filtersPayload.classifications || [];
  posts.value = unwrapList(blogRes?.data);
  plans.value = unwrapList(plansRes?.data);
  files.value = unwrapList(filesRes?.data);
  exams.value = unwrapList(examsRes?.data);
  if (!exams.value.length && Array.isArray(examsRes?.data?.data?.data)) {
    exams.value = examsRes.data.data.data;
  }

  loadingJobs.value = false;
  loadingPlans.value = false;
  loadingFiles.value = false;
  loadingExams.value = false;
});
</script>
