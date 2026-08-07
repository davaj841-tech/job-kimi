<template>
  <div class="px-4 py-4">
    <LoadingSpinner v-if="loading" />
    <template v-else-if="exam">
      <h1 class="mb-2 text-xl font-black">{{ exam.title }}</h1>
      <div class="mb-3 flex items-center gap-3">
        <StarRating :avg="exam.avg_rating || 0" readonly show-value />
        <button class="text-xs font-bold text-brand" @click="shareOpen = true">اشتراک‌گذاری</button>
      </div>
      <p class="mb-4 text-sm leading-6 text-ink-soft">{{ exam.description }}</p>
      <div class="card-soft mb-4 grid grid-cols-2 gap-3 p-4 text-sm">
        <div>مدت: <b>{{ exam.duration_minutes }} دقیقه</b></div>
        <div>سوالات: <b>{{ exam.total_questions }}</b></div>
        <div>نمره قبولی: <b>{{ exam.passing_score }}</b></div>
        <div>
          هزینه:
          <b class="text-brand">{{ exam.is_free ? 'رایگان' : formatPrice(exam.price) }}</b>
        </div>
      </div>

      <div
        v-if="exam.has_negative_marking"
        class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm leading-6 text-amber-900"
      >
        <p class="font-bold">هشدار نمره منفی</p>
        <p class="mt-1">
          این آزمون نمره منفی دارد. به ازای هر پاسخ غلط،
          {{ ratioText }} نمره همان سوال از مجموع کسر می‌شود.
          سوالات بدون پاسخ نمره منفی نمی‌گیرند.
        </p>
      </div>

      <p v-if="!exam.is_eligible" class="mb-3 rounded-lg bg-brand-soft p-3 text-sm text-brand">
        برای این آزمون نیاز به اشتراک دارید.
        <RouterLink to="/subscription" class="font-bold underline">خرید اشتراک</RouterLink>
      </p>
      <button class="btn-primary" :disabled="starting || !(exam.is_eligible || exam.is_free)" @click="start">
        شروع آزمون
      </button>
      <p v-if="error" class="mt-3 text-center text-sm text-brand">{{ error }}</p>
      <ShareModal
        :open="shareOpen"
        :title="exam.title"
        :description="exam.description"
        :url="shareUrl"
        @close="shareOpen = false"
      />
    </template>
  </div>
</template>

<script setup>
import { setExamMeta } from '../../services/meta';
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../../api/client';
import LoadingSpinner from '../../components/LoadingSpinner.vue';
import ShareModal from '../../components/ShareModal.vue';
import StarRating from '../../components/StarRating.vue';
import { useExamStore } from '../../stores/exam';

const route = useRoute();
const router = useRouter();
const examStore = useExamStore();

const exam = ref(null);
const loading = ref(true);
const starting = ref(false);
const error = ref('');
const shareOpen = ref(false);
const shareUrl = computed(() => `${window.location.origin}/exams/${exam.value?.slug || ''}`);

const ratioText = computed(() => {
  const ratio = Number(exam.value?.negative_mark_ratio ?? 0.3333);
  if (Math.abs(ratio - 1 / 3) < 0.01) return 'یک‌سوم';
  return `${Math.round(ratio * 100)}٪`;
});

function formatPrice(v) {
  return new Intl.NumberFormat('fa-IR').format(Number(v)) + ' ریال';
}

onMounted(async () => {
  try {
    const { data } = await api.get(`/exams/${route.params.slug}`);
    exam.value = data.data;
    setExamMeta(exam.value);
  } catch (_) {
    error.value = 'آزمون یافت نشد.';
  } finally {
    loading.value = false;
  }
});

async function start() {
  if (!exam.value) return;
  starting.value = true;
  error.value = '';
  try {
    const { data } = await api.post(`/exams/${exam.value.id}/start`);
    const payload = data.data;
    examStore.current = {
      examId: exam.value.id,
      attemptId: payload.attempt_id || payload.attempt?.id,
      questions: payload.questions || [],
      duration: exam.value.duration_minutes,
      hasNegativeMarking: Boolean(exam.value.has_negative_marking),
    };
    examStore.answers = {};
    examStore.dirty = false;
    examStore.endsAt = (payload.end_time ? payload.end_time * 1000 : Date.now() + exam.value.duration_minutes * 60 * 1000);
    examStore.saveCache();
    router.push(`/exams/${exam.value.id}/take`);
  } catch (e) {
    error.value = e.response?.data?.message || 'شروع آزمون ممکن نشد.';
  } finally {
    starting.value = false;
  }
}
</script>
