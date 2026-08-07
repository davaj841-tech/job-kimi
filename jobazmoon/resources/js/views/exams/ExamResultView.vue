<template>
  <div class="px-4 py-6">
    <LoadingSpinner v-if="loading" />
    <template v-else-if="result">
      <div class="card-soft mb-4 p-5 text-center">
        <p class="mb-2 text-sm text-ink-muted">نتیجه آزمون</p>
        <p class="text-4xl font-black text-brand">{{ toFaDigits(Math.round(result.percentage || 0)) }}٪</p>
        <p class="mt-2 text-sm">نمره: {{ toFaDigits(result.score) }}</p>
        <p v-if="analysis?.rank" class="mt-1 text-xs text-ink-muted">رتبه: {{ toFaDigits(analysis.rank) }}</p>
        <p
          class="mt-3 inline-block rounded-lg px-3 py-1 text-xs font-bold"
          :class="passed ? 'bg-emerald-50 text-emerald-700' : 'bg-brand-soft text-brand'"
        >
          {{ passed ? '🎉 قبول' : '📌 مردود' }}
        </p>
      </div>

      <div class="mb-4 grid grid-cols-3 gap-2 text-center text-sm">
        <div class="card-soft p-3">
          <p class="text-[11px] text-ink-muted">✅ صحیح</p>
          <p class="mt-1 font-black text-emerald-600">{{ toFaDigits(result.total_correct) }}</p>
        </div>
        <div class="card-soft p-3">
          <p class="text-[11px] text-ink-muted">❌ غلط</p>
          <p class="mt-1 font-black text-brand">{{ toFaDigits(result.total_wrong) }}</p>
        </div>
        <div class="card-soft p-3">
          <p class="text-[11px] text-ink-muted">➖ بی‌پاسخ</p>
          <p class="mt-1 font-black text-ink-muted">{{ toFaDigits(blankCount) }}</p>
        </div>
      </div>

      <!-- Overall summary paragraph -->
      <div class="card-soft mb-4 p-4 text-sm leading-7 text-ink-soft">
        شما در این آزمون از مجموع {{ toFaDigits(analysis?.total_questions || totalCount) }} سوال،
        <b class="text-emerald-600">{{ toFaDigits(result.total_correct) }}</b> سوال را صحیح،
        <b class="text-brand">{{ toFaDigits(result.total_wrong) }}</b> سوال را غلط پاسخ دادید
        <template v-if="blankCount">و <b>{{ toFaDigits(blankCount) }}</b> سوال بی‌پاسخ ماند</template>.
        درصد قبولی شما <b>{{ toFaDigits(Math.round(result.percentage || 0)) }}٪</b> بود و
        <b :class="passed ? 'text-emerald-600' : 'text-brand'">{{ passed ? 'در این آزمون قبول شدید' : 'متأسفانه قبول نشدید' }}</b>.
      </div>

      <!-- Per-subject analysis -->
      <div v-if="subjects.length" class="card-soft mb-4 p-4">
        <h2 class="mb-3 text-sm font-bold">📊 تحلیل موضوعی</h2>
        <div class="space-y-3">
          <div v-for="row in subjects" :key="row.subject" class="text-xs">
            <div class="mb-1 flex items-center justify-between">
              <span class="font-bold">{{ row.subject_label }}</span>
              <span class="text-ink-muted">
                ✅ {{ toFaDigits(row.correct) }} · ❌ {{ toFaDigits(row.wrong) }} · ➖ {{ toFaDigits(row.blank) }} از {{ toFaDigits(row.total) }}
              </span>
            </div>
            <div class="h-1.5 overflow-hidden rounded bg-surface-page">
              <div
                class="h-full rounded bg-brand"
                :style="{ width: `${row.percentage ?? (row.total ? Math.round((row.correct / row.total) * 100) : 0)}%` }"
              />
            </div>
            <p class="mt-1 text-left text-[11px] font-bold text-brand" dir="ltr">
              {{ toFaDigits(Math.round(row.percentage ?? 0)) }}٪
            </p>
          </div>
        </div>
      </div>

      <div class="mb-4 rounded-2xl border border-amber-100 bg-amber-50 p-4 text-center">
        <p class="mb-2 text-sm font-bold text-amber-900">این آزمون را چقدر ارزیابی می‌کنید؟</p>
        <StarRating v-model="rating" />
        <button class="mt-3 text-xs font-bold text-brand" :disabled="!rating || ratingSaving" @click="submitRating">
          {{ ratingSaving ? '...' : 'ثبت امتیاز' }}
        </button>
        <p v-if="ratingMsg" class="mt-2 text-xs text-emerald-700">{{ ratingMsg }}</p>
      </div>

      <div class="mb-4 flex flex-col gap-2">
        <button class="btn-primary" :disabled="downloading" @click="downloadReportCard">
          {{ downloading ? 'در حال آماده‌سازی...' : '📄 دانلود کارنامه PDF' }}
        </button>
        <button class="btn-ghost border border-surface-line" @click="showSheet = !showSheet">
          {{ showSheet ? 'بستن پاسخبرگ' : '📋 مشاهده پاسخبرگ' }}
        </button>
      </div>

      <div v-if="showSheet" class="mb-4 space-y-2">
        <div
          v-for="item in sheet"
          :key="item.id"
          class="rounded-xl border px-3 py-3 text-sm"
          :class="item.is_blank ? 'border-surface-line bg-surface-page' : item.is_correct ? 'border-emerald-200 bg-emerald-50' : 'border-brand/30 bg-brand-soft'"
        >
          <div class="mb-1 flex items-center justify-between text-xs text-ink-muted">
            <span>سوال {{ toFaDigits(item.number) }}</span>
            <span>
              {{ item.is_blank ? 'بدون پاسخ' : item.is_correct ? 'صحیح' : 'غلط' }}
            </span>
          </div>
          <p class="mb-2 leading-6">{{ item.question_text }}</p>
          <p class="text-xs">
            پاسخ شما:
            <b>{{ item.user_answer ? String(item.user_answer).toUpperCase() : '—' }}</b>
            — صحیح:
            <b>{{ String(item.correct_answer).toUpperCase() }}</b>
          </p>
          <p v-if="item.explanation" class="mt-2 text-xs leading-5 text-ink-soft">{{ item.explanation }}</p>
        </div>
      </div>

      <div class="flex gap-2">
        <RouterLink to="/exams" class="btn-primary flex-1">بازگشت به آزمون‌ها</RouterLink>
        <RouterLink to="/dashboard" class="btn-ghost flex-1 border border-surface-line">📊 داشبورد</RouterLink>
      </div>
      <p v-if="error" class="mt-3 text-center text-sm text-brand">{{ error }}</p>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import api from '../../api/client';
import LoadingSpinner from '../../components/LoadingSpinner.vue';
import StarRating from '../../components/StarRating.vue';
import { toFaDigits } from '../../utils/format';

const route = useRoute();
const loading = ref(true);
const result = ref(null);
const analysis = ref(null);
const sheet = ref([]);
const showSheet = ref(false);
const downloading = ref(false);
const error = ref('');
const rating = ref(0);
const ratingSaving = ref(false);
const ratingMsg = ref('');

const passed = computed(() => Boolean(analysis.value?.passed));
const subjects = computed(() => analysis.value?.by_subject || []);
const totalCount = computed(() => (result.value?.total_correct || 0) + (result.value?.total_wrong || 0));
const blankCount = computed(() => {
  const total = analysis.value?.total_questions ?? totalCount.value;
  return Math.max(0, total - (result.value?.total_correct || 0) - (result.value?.total_wrong || 0));
});

onMounted(async () => {
  try {
    const examId = route.params.id;
    const attemptId = route.params.attemptId;
    const [{ data: resultRes }, { data: sheetRes }] = await Promise.all([
      api.get(`/exams/${examId}/result/${attemptId}`),
      api.get(`/exams/${examId}/answer-sheet/${attemptId}`),
    ]);
    result.value = resultRes.data?.attempt || resultRes.data;
    analysis.value = sheetRes.data?.analysis || null;
    sheet.value = sheetRes.data?.sheet || [];
  } catch (_) {
    error.value = 'بارگذاری نتیجه ممکن نشد.';
  } finally {
    loading.value = false;
  }
});

async function downloadReportCard() {
  downloading.value = true;
  error.value = '';
  try {
    const examId = route.params.id;
    const attemptId = route.params.attemptId;
    const response = await api.get(`/exams/${examId}/report-card/${attemptId}`, {
      responseType: 'blob',
    });
    const blob = new Blob([response.data], { type: 'application/pdf' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `report-card-${examId}-${attemptId}.pdf`;
    a.click();
    URL.revokeObjectURL(url);
  } catch (_) {
    error.value = 'دانلود کارنامه ممکن نشد.';
  } finally {
    downloading.value = false;
  }
}

async function submitRating() {
  if (!rating.value) return;
  ratingSaving.value = true;
  ratingMsg.value = '';
  try {
    await api.post(`/exams/${route.params.id}/rate`, { rating: rating.value });
    ratingMsg.value = 'امتیاز ثبت شد. متشکریم!';
  } catch (e) {
    ratingMsg.value = e.response?.data?.message || 'ثبت امتیاز ممکن نشد.';
  } finally {
    ratingSaving.value = false;
  }
}
</script>
