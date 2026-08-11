<template>
  <PageShell title="آزمون‌ها" subtitle="تمرین و سنجش آمادگی استخدام">

    <!-- Search + sort -->
    <div class="mb-3 flex gap-2">
      <input
        v-model="filters.search"
        class="input-field flex-1"
        placeholder="جستجوی آزمون..."
        @keyup.enter="load"
      />
      <select v-model="filters.sort" class="field w-32" @change="load">
        <option value="latest">جدیدترین</option>
        <option value="popular">محبوب‌ترین</option>
        <option value="participants">پرشرکت‌کننده</option>
        <option value="rating">بالاترین امتیاز</option>
      </select>
    </div>

    <!-- Classification chips -->
    <div
      v-if="classifications.length"
      class="-mx-4 mb-3 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
    >
      <button
        type="button"
        class="chip"
        :class="!filters.job_classification_id ? 'chip-active' : ''"
        @click="setClassification(null)"
      >
        همه
      </button>
      <button
        v-for="c in classifications"
        :key="c.id"
        type="button"
        class="chip"
        :class="
          String(filters.job_classification_id) === String(c.id)
            ? 'chip-active'
            : ''
        "
        @click="setClassification(c.id)"
      >
        <span v-if="c.icon">{{ c.icon }}</span>
        {{ c.name }}
      </button>
    </div>

    <!-- Access chips -->
    <div class="mb-4 flex gap-2">
      <button
        v-for="opt in accessOptions"
        :key="opt.value"
        type="button"
        class="chip"
        :class="filters.access === opt.value ? 'chip-active' : ''"
        @click="setAccess(opt.value)"
      >
        {{ opt.label }}
      </button>
    </div>

    <SkeletonCard v-if="loading" :count="5" />
    <template v-else>
      <div v-if="exams.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <article
          v-for="exam in exams"
          :key="exam.id"
          class="page-card cursor-pointer p-4 transition hover:bg-surface-page"
          @click="goExam(exam)"
        >
          <div class="mb-1.5 flex items-start justify-between gap-2">
            <h3 class="mobile-card-title line-clamp-2">{{ exam.title }}</h3>
            <span
              class="shrink-0 rounded-md px-1.5 py-0.5 text-[10px] font-bold"
              :class="badgeClass(exam)"
            >
              {{ badgeLabel(exam) }}
            </span>
          </div>
          <p
            v-if="exam.classification?.name"
            class="mb-2 line-clamp-1 text-xs text-ink-muted"
          >
            {{ exam.classification.name }}
          </p>
          <div class="flex items-center justify-between text-xs text-ink-muted">
            <span>{{ metaOf(exam) }}</span>
            <span
              v-if="!exam.is_free"
              class="font-bold tabular-nums text-brand"
            >
              {{
                exam.subscription_required === 'paid'
                  ? 'با اشتراک'
                  : formatPrice(exam.price)
              }}
            </span>
          </div>
        </article>
      </div>
      <EmptyState
        v-else
        title="آزمونی موجود نیست"
        description="با فیلترهای دیگر جستجو کنید یا بعداً دوباره سر بزنید."
        icon="📭"
      />
    </template>
  </PageShell>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../../api/client'
import EmptyState from '../../components/EmptyState.vue'
import PageShell from '../../components/layout/PageShell.vue'
import SkeletonCard from '../../components/ui/SkeletonCard.vue'
import { useAuthStore } from '../../stores/auth'
import { formatPrice, unwrapList } from '../../utils/format'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const exams = ref([])
const classifications = ref([])
const loading = ref(true)

const accessOptions = [
  { value: '', label: 'همه' },
  { value: 'free', label: 'رایگان' },
      { value: 'subscription', label: 'اشتراک' },
      { value: 'paid', label: 'فروشی' },
]

const filters = reactive({
  search: route.query.search || '',
  sort: 'latest',
  job_classification_id: null,
  access: '',
})

function setClassification(id) {
  filters.job_classification_id = id
  load()
}
function setAccess(value) {
  filters.access = value
  load()
}

function metaOf(exam) {
  const rating = exam.avg_rating
    ? `★ ${Number(exam.avg_rating).toFixed(1)} · `
    : ''
  return `${rating}${exam.duration_minutes || '-'} دقیقه · ${exam.total_questions || 0} سوال`
}

function badgeLabel(exam) {
  if (exam.is_free) return 'رایگان'
  if (exam.subscription_required === 'paid') return 'اشتراکی'
  return 'فروشی'
}
function badgeClass(exam) {
  if (exam.is_free) return 'bg-emerald-50 text-emerald-700'
  if (exam.subscription_required === 'paid') return 'bg-amber-50 text-amber-700'
  return 'bg-brand-soft text-brand'
}

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/exams', {
      params: {
        search: filters.search || undefined,
        sort: filters.sort,
        job_classification_id: filters.job_classification_id || undefined,
        access: filters.access || undefined,
        per_page: 24,
      },
    })
    exams.value = unwrapList(data)
  } catch {
    exams.value = []
  } finally {
    loading.value = false
  }
}

async function loadClassifications() {
  try {
    const { data } = await api.get('/job-posts/filters')
    const payload = data?.data || data || {}
    classifications.value = payload.home_classifications || []
  } catch {
    classifications.value = []
  }
}

function goExam(exam) {
  const path = `/exams/${exam.slug || exam.id}`
  if (!auth.isAuthenticated) {
    router.push({ path: '/login', query: { redirect: path } })
    return
  }
  router.push(path)
}

let t
watch(
  () => filters.search,
  () => {
    clearTimeout(t)
    t = setTimeout(load, 350)
  }
)

onMounted(() => {
  load()
  loadClassifications()
})
</script>

