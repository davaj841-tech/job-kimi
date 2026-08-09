<template>
  <div class="min-h-dvh bg-white pb-24">
    <div class="sticky top-0 z-20 border-b border-surface-line bg-white px-4 py-3">
      <div class="flex items-center justify-between">
        <span class="text-sm font-bold">صفحه {{ toFaDigits(pageIndex + 1) }} از {{ toFaDigits(totalPages) }}</span>
        <span class="rounded-md bg-brand-soft px-2 py-1 text-xs font-bold text-brand">⏱ {{ timerText }}</span>
      </div>
      <div class="mt-2 flex items-center justify-between text-[11px] text-ink-muted">
        <span v-if="examStore.offline" class="rounded bg-amber-50 px-2 py-0.5 text-amber-700">آفلاین — ذخیره محلی</span>
        <span v-else-if="syncing" class="text-ink-soft">در حال ذخیره‌سازی...</span>
        <span v-else-if="examStore.lastSyncedAt" class="text-emerald-600">ذخیره شد</span>
        <span v-else>آماده</span>
        <span>{{ toFaDigits(answeredCount) }} از {{ toFaDigits(questions.length) }} پاسخ‌داده‌شده</span>
      </div>
      <div class="mt-2 h-1 overflow-hidden rounded-full bg-surface-page">
        <div class="h-full rounded-full bg-brand transition-all" :style="{ width: `${((pageIndex + 1) / totalPages) * 100}%` }" />
      </div>
    </div>

    <div class="space-y-5 px-4 py-5">
      <div v-for="(q, localIdx) in pageQuestions" :key="q.id" class="rounded-2xl border border-surface-line p-3">
        <div class="mb-2 flex items-center gap-2">
          <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-soft text-[11px] font-bold text-brand">
            {{ toFaDigits(pageIndex * PER_PAGE + localIdx + 1) }}
          </span>
          <span v-if="q.subject" class="rounded-md bg-surface-page px-2 py-0.5 text-[10px] text-ink-muted">{{ q.subject }}</span>
        </div>
        <div class="mb-4 text-base font-medium leading-8" v-html="q.question_text" />
        <div class="space-y-2">
          <button
            v-for="(opt, optIdx) in optionsFor(q)"
            :key="opt.key"
            type="button"
            class="flex w-full items-start gap-3 rounded-xl border px-3 py-3 text-right text-sm transition"
            :class="examStore.answers[q.id] === opt.key ? 'border-brand bg-brand-soft' : 'border-surface-line bg-white'"
            @click="selectAnswer(q.id, opt.key)"
          >
            <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-surface-page text-xs font-bold">
              {{ PERSIAN_LETTERS[optIdx] }}
            </span>
            <span class="text-sm leading-7" v-html="opt.label" />
          </button>
        </div>
      </div>
    </div>

    <div class="fixed inset-x-0 bottom-0 mx-auto flex max-w-app gap-2 border-t border-surface-line bg-white p-3">
      <button class="btn-ghost flex-1 border border-surface-line" :disabled="pageIndex === 0" @click="goPage(pageIndex - 1)">
        ← قبلی
      </button>
      <button v-if="pageIndex < totalPages - 1" class="btn-primary flex-1" @click="goPage(pageIndex + 1)">
        بعدی →
      </button>
      <button v-else class="btn-primary flex-1" :disabled="submitting" @click="submit">
        {{ submitting ? '...' : '✅ ثبت آزمون' }}
      </button>
    </div>
    <p v-if="submitError" class="fixed inset-x-0 bottom-16 mx-auto max-w-app px-4 text-center text-xs text-brand">
      {{ submitError }}
    </p>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import api from '../../api/client';
import { useExamStore } from '../../stores/exam';
import { toFaDigits } from '../../utils/format';

const router = useRouter();
const examStore = useExamStore();

const PER_PAGE = 5;
const PERSIAN_LETTERS = ['الف', 'ب', 'ج', 'د'];

const pageIndex = ref(0);
const submitting = ref(false);
const submitError = ref('');
const syncing = ref(false);
const now = ref(Date.now());
let timer;
let debounceTimer;
let intervalTimer;
let pendingSync = false;

const questions = computed(() => examStore.current?.questions || []);
const totalPages = computed(() => Math.max(1, Math.ceil(questions.value.length / PER_PAGE)));
const pageQuestions = computed(() =>
  questions.value.slice(pageIndex.value * PER_PAGE, pageIndex.value * PER_PAGE + PER_PAGE)
);
const answeredCount = computed(() =>
  Object.values(examStore.answers || {}).filter((v) => v !== null && v !== undefined && v !== '').length
);

function seededShuffle(items, seed) {
  const arr = [...items];
  let s = Number(seed) || 1;
  for (let i = arr.length - 1; i > 0; i--) {
    s = (s * 1664525 + 1013904223) % 4294967296;
    const j = s % (i + 1);
    [arr[i], arr[j]] = [arr[j], arr[i]];
  }
  return arr;
}

const optionsCache = new Map();
function optionsFor(question) {
  if (optionsCache.has(question.id)) return optionsCache.get(question.id);
  const o = question.options || {};
  const base = [
    { key: 'a', label: o.a || question.option_a },
    { key: 'b', label: o.b || question.option_b },
    { key: 'c', label: o.c || question.option_c },
    { key: 'd', label: o.d || question.option_d },
  ].filter((opt) => opt.label);
  const seed = `${examStore.current?.attemptId || 0}-${question.id}`;
  const shuffled = seededShuffle(base, seed);
  optionsCache.set(question.id, shuffled);
  return shuffled;
}

const timerText = computed(() => {
  const left = Math.max(0, (examStore.endsAt || 0) - now.value);
  const m = Math.floor(left / 60000);
  const s = Math.floor((left % 60000) / 1000);
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
});

function goPage(index) {
  pageIndex.value = Math.max(0, Math.min(totalPages.value - 1, index));
  examStore.setPage(pageIndex.value);
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function selectAnswer(questionId, key) {
  examStore.setAnswer(questionId, key);
  queueAutosave();
}

function queueAutosave() {
  pendingSync = true;
  if (debounceTimer) clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    syncAnswers();
  }, 800);
}

async function syncAnswers() {
  if (!examStore.current || submitting.value) return;
  if (examStore.offline || !navigator.onLine) {
    examStore.setOffline(true);
    examStore.saveCache();
    return;
  }
  if (!pendingSync && !examStore.dirty) return;

  syncing.value = true;
  try {
    const { examId, attemptId } = examStore.current;
    await api.post(`/exams/${examId}/autosave/${attemptId}`, {
      answers: examStore.answers,
    });
    pendingSync = false;
    examStore.markSynced();
  } catch (_) {
    examStore.saveCache();
  } finally {
    syncing.value = false;
  }
}

function onOnline() {
  examStore.setOffline(false);
  syncAnswers();
}

function onOffline() {
  examStore.setOffline(true);
  examStore.saveCache();
}

onMounted(async () => {
  if (!examStore.current) {
    examStore.loadCache();
  }
  if (!examStore.current) {
    router.replace('/exams');
    return;
  }

  pageIndex.value = examStore.pageIndex || 0;

  examStore.setOffline(!navigator.onLine);
  window.addEventListener('online', onOnline);
  window.addEventListener('offline', onOffline);

  // Restore from redis if local answers empty
  if (Object.keys(examStore.answers || {}).length === 0) {
    try {
      const { examId, attemptId } = examStore.current;
      const { data } = await api.get(`/exams/${examId}/autosave/${attemptId}`);
      const remote = data.data?.answers || {};
      if (Object.keys(remote).length) {
        examStore.answers = remote;
        examStore.saveCache();
      }
    } catch (_) {
      /* ignore */
    }
  }

  timer = setInterval(async () => {
    now.value = Date.now();
    if (examStore.endsAt && now.value >= examStore.endsAt) {
      await submit();
    }
  }, 1000);

  intervalTimer = setInterval(() => {
    if (examStore.dirty) syncAnswers();
  }, 15000);
});

onUnmounted(() => {
  clearInterval(timer);
  clearTimeout(debounceTimer);
  clearInterval(intervalTimer);
  window.removeEventListener('online', onOnline);
  window.removeEventListener('offline', onOffline);
  if (examStore.dirty) {
    syncAnswers();
  }
});

watch(
  () => examStore.answers,
  () => {
    examStore.saveCache();
  },
  { deep: true }
);

async function submit() {
  if (submitting.value || !examStore.current) return;
  submitting.value = true;
  submitError.value = '';
  try {
    await syncAnswers();
    const { examId, attemptId } = examStore.current;
    await api.post(`/exams/${examId}/submit/${attemptId}`, {
      answers: examStore.answers,
    });
    examStore.clearCache();
    router.replace(`/exams/${examId}/result/${attemptId}`);
  } catch (e) {
    examStore.saveCache();
    submitError.value = e.response?.data?.message || 'ثبت آزمون ناموفق بود. لطفاً دوباره تلاش کنید.';
    submitting.value = false;
  }
}
</script>
