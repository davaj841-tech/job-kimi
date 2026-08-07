<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
    <div class="flex max-h-[92vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-xl">
      <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
        <div>
          <h3 class="text-lg font-bold">مشاهده آزمون</h3>
          <p v-if="exam" class="mt-0.5 text-xs text-slate-500">{{ exam.title }}</p>
        </div>
        <button type="button" class="rounded-lg px-2 py-1 text-slate-500 hover:bg-slate-100" @click="$emit('close')">
          ✕
        </button>
      </div>

      <div class="flex-1 overflow-y-auto p-5">
        <div v-if="loading" class="py-12 text-center text-sm text-slate-500">در حال بارگذاری...</div>
        <div v-else-if="error" class="py-8 text-center text-sm text-red-500">{{ error }}</div>
        <div v-else-if="!questions.length" class="py-8 text-center text-sm text-slate-500">سوالی برای این آزمون ثبت نشده است.</div>
        <div v-else class="space-y-4">
          <div class="flex items-center justify-between text-sm text-slate-500">
            <span>سوال {{ fa(index + 1) }} از {{ fa(questions.length) }}</span>
            <span v-if="revealed" class="font-bold text-emerald-600">پاسخ: {{ answerLabel(current.correct_answer) }}</span>
          </div>

          <div class="rounded-xl bg-slate-50 p-4 text-sm leading-7" v-html="current.question_text" />

          <div class="space-y-2">
            <button
              v-for="opt in optionDefs"
              :key="opt.key"
              type="button"
              class="flex w-full items-start gap-3 rounded-xl border px-3 py-3 text-right text-sm transition"
              :class="optionClass(opt.key)"
              @click="selectAnswer(opt.key)"
            >
              <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold">
                {{ opt.short }}
              </span>
              <span class="flex-1" v-html="current[`option_${opt.key}`]" />
            </button>
          </div>

          <div v-if="revealed && current.explanation" class="rounded-xl border border-amber-100 bg-amber-50 p-3 text-sm text-amber-900">
            <p class="mb-1 text-xs font-bold">توضیح</p>
            <div v-html="current.explanation" />
          </div>
        </div>
      </div>

      <div v-if="questions.length" class="flex items-center justify-between gap-2 border-t border-slate-100 px-5 py-4">
        <button
          type="button"
          class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-bold disabled:opacity-40"
          :disabled="index <= 0"
          @click="prev"
        >
          قبلی
        </button>
        <button type="button" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-bold" @click="$emit('close')">
          بستن
        </button>
        <button
          type="button"
          class="rounded-xl bg-orange-500 px-4 py-2 text-sm font-bold text-white disabled:opacity-40"
          :disabled="index >= questions.length - 1"
          @click="next"
        >
          بعدی
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import adminApi from '../../api/client';

const props = defineProps({
  open: Boolean,
  examId: { type: [Number, String], default: null },
});
defineEmits(['close']);

const loading = ref(false);
const error = ref('');
const exam = ref(null);
const questions = ref([]);
const index = ref(0);
const selected = ref('');
const revealed = ref(false);

const optionDefs = [
  { key: 'a', short: 'الف' },
  { key: 'b', short: 'ب' },
  { key: 'c', short: 'ج' },
  { key: 'd', short: 'د' },
];

const answerMap = { a: 'الف', b: 'ب', c: 'ج', d: 'د' };

const current = computed(() => questions.value[index.value] || {});

watch(
  () => [props.open, props.examId],
  async ([open, id]) => {
    if (!open || !id) return;
    await load(id);
  }
);

async function load(id) {
  loading.value = true;
  error.value = '';
  exam.value = null;
  questions.value = [];
  index.value = 0;
  selected.value = '';
  revealed.value = false;
  try {
    const { data } = await adminApi.get(`/admin/exams/${id}/preview`);
    const payload = data.data || {};
    exam.value = payload.exam || null;
    questions.value = payload.questions || [];
  } catch (e) {
    error.value = e.response?.data?.message || 'بارگذاری پیش‌نمایش ناموفق بود.';
  } finally {
    loading.value = false;
  }
}

function fa(n) {
  return new Intl.NumberFormat('fa-IR').format(Number(n || 0));
}
function answerLabel(v) {
  return answerMap[String(v || '').toLowerCase()] || String(v || '—').toUpperCase();
}
function selectAnswer(key) {
  selected.value = key;
  revealed.value = true;
}
function optionClass(key) {
  if (!revealed.value) {
    return selected.value === key
      ? 'border-orange-400 bg-orange-50'
      : 'border-slate-200 hover:border-orange-300';
  }
  const correct = String(current.value.correct_answer || '').toLowerCase();
  if (key === correct) return 'border-emerald-400 bg-emerald-50';
  if (key === selected.value && key !== correct) return 'border-red-300 bg-red-50';
  return 'border-slate-200 opacity-70';
}
function prev() {
  if (index.value <= 0) return;
  index.value -= 1;
  selected.value = '';
  revealed.value = false;
}
function next() {
  if (index.value >= questions.value.length - 1) return;
  index.value += 1;
  selected.value = '';
  revealed.value = false;
}
</script>
