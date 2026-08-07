<template>
  <div class="px-4 py-4 lg:desk-container lg:grid lg:grid-cols-[260px_1fr] lg:gap-6 lg:py-8">
    <aside class="mb-4 hidden rounded-2xl border border-surface-line bg-white p-4 lg:block">
      <h2 class="mb-3 text-sm font-bold">فیلتر پیشرفته</h2>
      <label class="label">مرتب‌سازی</label>
      <select v-model="filters.sort" class="field mb-3" @change="load">
        <option value="latest">جدیدترین</option>
        <option value="popular">محبوب‌ترین</option>
        <option value="participants">بیشترین شرکت‌کننده</option>
        <option value="rating">بالاترین امتیاز</option>
      </select>
      <label class="label">قیمت تا {{ fa(filters.price_max) }}</label>
      <input v-model.number="filters.price_max" type="range" min="0" max="5000000" step="50000" class="mb-3 w-full" @change="load" />
      <label class="label">مدت (دقیقه) {{ filters.duration_min }}–{{ filters.duration_max }}</label>
      <div class="mb-3 flex gap-2">
        <input v-model.number="filters.duration_min" type="number" min="15" max="180" class="field" @change="load" />
        <input v-model.number="filters.duration_max" type="number" min="15" max="180" class="field" @change="load" />
      </div>
      <label class="label">تعداد سوال</label>
      <div class="mb-3 flex gap-2">
        <input v-model.number="filters.questions_min" type="number" min="0" class="field" placeholder="از" @change="load" />
        <input v-model.number="filters.questions_max" type="number" min="0" class="field" placeholder="تا" @change="load" />
      </div>
      <p class="label mb-2">موضوعات</p>
      <label v-for="s in subjects" :key="s" class="mb-1 flex items-center gap-2 text-xs">
        <input v-model="filters.subjects" type="checkbox" :value="s" @change="load" />
        {{ subjectLabel(s) }}
      </label>
      <div class="mt-4 lg:sticky lg:top-24">
        <BannerSlider position="exam_sidebar" />
      </div>
    </aside>

    <div>
      <h1 class="mb-4 section-title">آزمون‌ها</h1>
      <input v-model="filters.search" class="input-field mb-3" placeholder="جستجوی آزمون..." @keyup.enter="load" />
      <select v-model="filters.sort" class="field mb-3 lg:hidden" @change="load">
        <option value="latest">جدیدترین</option>
        <option value="popular">محبوب‌ترین</option>
        <option value="participants">بیشترین شرکت‌کننده</option>
        <option value="rating">بالاترین امتیاز</option>
      </select>
      <SkeletonCard v-if="loading" :count="4" />
      <div v-else class="space-y-2">
        <ContentCard
          v-for="exam in exams"
          :key="exam.id"
          :title="exam.title"
          :subtitle="exam.category?.name || ''"
          :meta="metaOf(exam)"
          :price="exam.is_free ? 0 : exam.price"
          :badge="exam.is_free ? 'رایگان' : ''"
          @click="$router.push(`/exams/${exam.slug}`)"
        />
        <p v-if="!exams.length" class="py-10 text-center text-sm text-ink-muted">آزمونی موجود نیست.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import api from '../../api/client';
import BannerSlider from '../../components/BannerSlider.vue';
import ContentCard from '../../components/ContentCard.vue';
import SkeletonCard from '../../components/ui/SkeletonCard.vue';
import { toFaDigits } from '../../utils/format';

const route = useRoute();
const exams = ref([]);
const loading = ref(true);
const subjects = ['math', 'literature', 'islamic', 'english', 'chemistry', 'physics', 'iq', 'general'];
const filters = reactive({
  search: route.query.search || '',
  sort: 'latest',
  price_max: 5000000,
  duration_min: 15,
  duration_max: 180,
  questions_min: null,
  questions_max: null,
  subjects: [],
});

function fa(n) { return toFaDigits(n); }
function subjectLabel(s) {
  return { math: 'ریاضی', literature: 'ادبیات', islamic: 'معارف', english: 'زبان', chemistry: 'شیمی', physics: 'فیزیک', iq: 'هوش', general: 'عمومی' }[s] || s;
}
function metaOf(exam) {
  const rating = exam.avg_rating ? `★ ${toFaDigits(Number(exam.avg_rating).toFixed(1))} · ` : '';
  return `${rating}${exam.duration_minutes || '-'} دقیقه · ${exam.total_questions || 0} سوال`;
}

async function load() {
  loading.value = true;
  try {
    const { data } = await api.get('/exams', {
      params: {
        search: filters.search || undefined,
        sort: filters.sort,
        price_max: filters.price_max,
        duration_min: filters.duration_min,
        duration_max: filters.duration_max,
        questions_min: filters.questions_min || undefined,
        questions_max: filters.questions_max || undefined,
        subjects: filters.subjects.length ? filters.subjects.join(',') : undefined,
      },
    });
    exams.value = data.data?.data || data.data || [];
  } catch {
    exams.value = [];
  } finally {
    loading.value = false;
  }
}

let t;
watch(() => filters.search, () => { clearTimeout(t); t = setTimeout(load, 350); });
onMounted(load);
</script>

<style scoped>
.label { @apply mb-1 block text-xs font-bold text-slate-500; }
.field { @apply h-10 w-full rounded-xl border border-surface-line px-3 text-sm outline-none focus:border-brand; }
</style>
