<template>
  <div class="min-h-dvh bg-white pb-24">
    <div class="sticky top-0 z-20 border-b border-surface-line bg-white px-4 py-3">
      <div class="flex items-center justify-between">
        <span class="text-sm font-bold">سوال {{ index + 1 }} / {{ questions.length }}</span>
        <span class="rounded-md bg-brand-soft px-2 py-1 text-xs font-bold text-brand">{{ timerText }}</span>
      </div>
      <div class="mt-2 flex items-center justify-between text-[11px] text-ink-muted">
        <span v-if="examStore.offline" class="rounded bg-amber-50 px-2 py-0.5 text-amber-700">آفلاین — ذخیره محلی</span>
        <span v-else-if="syncing" class="text-ink-soft">در حال ذخیره‌سازی...</span>
        <span v-else-if="examStore.lastSyncedAt" class="text-emerald-600">ذخیره شد</span>
        <span v-else>آماده</span>
        <span>{{ answeredCount }} پاسخ‌داده‌شده</span>
      </div>
    </div>

    <div v-if="current" class="px-4 py-5">
      <p class="mb-5 text-sm leading-7 font-medium">{{ current.question_text }}</p>
      <div class="space-y-2">
        <button
          v-for="opt in shuffledOptions"
          :key="opt.key"
          class="flex w-full items-start gap-3 rounded-xl border px-3 py-3 text-right text-sm transition"
          :class="selected === opt.key ? 'border-brand bg-brand-soft' : 'border-surface-line bg-white'"
          @click="selectAnswer(opt.key)"
        >
          <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-surface-page text-xs font-bold">
            {{ opt.key.toUpperCase() }}
          </span>
          <span>{{ opt.label }}</span>
        </button>
      </div>
    </div>

    <div class="fixed inset-x-0 bottom-0 mx-auto flex max-w-app gap-2 border-t border-surface-line bg-white p-3">
      <button class="btn-ghost flex-1 border border-surface-line" :disabled="index === 0" @click="index--">قبلی</button>
      <button v-if="index < questions.length - 1" class="btn-primary flex-1" @click="index++">بعدی</button>
      <button v-else class="btn-primary flex-1" :disabled="submitting" @click="submit">ثبت آزمون</button>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../../api/client';
import { useExamStore } from '../../stores/exam';

const route = useRoute();
const router = useRouter();
const examStore = useExamStore();

const index = ref(0);
const submitting = ref(false);
const syncing = ref(false);
const now = ref(Date.now());
let timer;
let debounceTimer;
let intervalTimer;
let pendingSync = false;

const questions = computed(() => examStore.current?.questions || []);
const current = computed(() => questions.value[index.value]);
const selected = computed(() => examStore.answers[current.value?.id]);
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

const shuffledOptions = computed(() => {
  const q = current.value;
  if (!q) return [];
  const o = q.options || {};
  const base = [
    { key: 'a', label: o.a || q.option_a },
    { key: 'b', label: o.b || q.option_b },
    { key: 'c', label: o.c || q.option_c },
    { key: 'd', label: o.d || q.option_d },
  ].filter((opt) => opt.label);
  const seed = `${examStore.current?.attemptId || 0}-${q.id}`;
  return seededShuffle(base, seed);
});

const timerText = computed(() => {
  const left = Math.max(0, (examStore.endsAt || 0) - now.value);
  const m = Math.floor(left / 60000);
  const s = Math.floor((left % 60000) / 1000);
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
});

function selectAnswer(key) {
  if (!current.value) return;
  examStore.setAnswer(current.value.id, key);
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
  try {
    await syncAnswers();
    const { examId, attemptId } = examStore.current;
    await api.post(`/exams/${examId}/submit/${attemptId}`, {
      answers: examStore.answers,
    });
    examStore.clearCache();
    router.replace(`/exams/${examId}/result/${attemptId}`);
  } catch (_) {
    // Offline fallback: keep local cache; user can retry submit
    examStore.saveCache();
    submitting.value = false;
  }
}
</script>
