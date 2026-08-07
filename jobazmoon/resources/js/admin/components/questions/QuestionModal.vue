<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" >
    <div class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
      <div class="mb-4 flex items-center justify-between">
        <h3 class="text-lg font-bold">{{ question?.id ? 'ویرایش سوال' : 'سوال جدید' }}</h3>
        <button @click="$emit('close')">✕</button>
      </div>

      <form class="space-y-4" @submit.prevent="submit">
        <div>
          <label class="label">آزمون *</label>
          <select v-model="form.exam_id" required class="field">
            <option disabled value="">انتخاب آزمون</option>
            <option v-for="e in exams" :key="e.id" :value="e.id">{{ e.title }}</option>
          </select>
        </div>

        <div>
          <div class="mb-1 flex items-center justify-between">
            <label class="label">متن سوال *</label>
            <button type="button" class="text-xs font-bold text-orange-500" @click="showPreview = !showPreview">
              {{ showPreview ? 'ویرایش' : 'پیش‌نمایش' }}
            </button>
          </div>
          <textarea v-if="!showPreview" v-model="form.question_text" required rows="4" class="field" @input="autosave" />
          <KaTeXPreview v-else :latex="form.question_text" />
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="label">نوع سوال</label>
            <select v-model="form.question_type" class="field">
              <option value="multiple_choice">چهارگزینه‌ای</option>
              <option value="formula">فرمول</option>
            </select>
          </div>
          <div>
            <label class="label">درس</label>
            <select v-model="form.subject" class="field">
              <option v-for="s in subjects" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
          <div v-for="opt in ['a', 'b', 'c', 'd']" :key="opt">
            <label class="label">گزینه {{ opt.toUpperCase() }}</label>
            <input v-model="form[`option_${opt}`]" required class="field" @input="autosave" />
            <KaTeXPreview class="mt-1" :latex="form[`option_${opt}`]" />
          </div>
        </div>

        <div>
          <label class="label">پاسخ صحیح</label>
          <div class="flex gap-3">
            <label v-for="opt in ['a', 'b', 'c', 'd']" :key="opt" class="flex items-center gap-1 text-sm">
              <input v-model="form.correct_answer" type="radio" :value="opt" />
              {{ opt.toUpperCase() }}
            </label>
          </div>
        </div>

        <div>
          <label class="label">سطح سختی</label>
          <div class="flex gap-3 text-sm">
            <label v-for="d in difficulties" :key="d.value" class="flex items-center gap-1">
              <input v-model="form.difficulty" type="radio" :value="d.value" />
              {{ d.label }}
            </label>
          </div>
        </div>

        <div>
          <label class="label">توضیحات جواب</label>
          <textarea v-model="form.explanation" rows="3" class="field" @input="autosave" />
        </div>

        <p v-if="error" class="text-sm text-red-500">{{ error }}</p>

        <div class="flex justify-end gap-2">
          <button type="button" class="rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold" @click="$emit('close')">انصراف</button>
          <button type="submit" class="rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-bold text-white" :disabled="saving">
            {{ saving ? '...' : 'ذخیره' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';
import KaTeXPreview from '../ui/KaTeXPreview.vue';

const DRAFT_KEY = 'admin_question_draft';

const props = defineProps({
  open: Boolean,
  question: { type: Object, default: null },
  exams: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const showPreview = ref(false);
const saving = ref(false);
const error = ref('');
const form = reactive(emptyForm());

const subjects = [
  { value: 'islamic', label: 'معارف' },
  { value: 'literature', label: 'ادبیات' },
  { value: 'math', label: 'ریاضی' },
  { value: 'chemistry', label: 'شیمی' },
  { value: 'physics', label: 'فیزیک' },
  { value: 'iq', label: 'هوش' },
  { value: 'english', label: 'انگلیسی' },
  { value: 'general', label: 'عمومی' },
];

const difficulties = [
  { value: 'easy', label: 'آسان' },
  { value: 'medium', label: 'متوسط' },
  { value: 'hard', label: 'سخت' },
];

watch(
  () => [props.open, props.question],
  () => {
    if (!props.open) return;
    if (props.question?.id) {
      Object.assign(form, mapQuestion(props.question));
    } else {
      const draft = localStorage.getItem(DRAFT_KEY);
      Object.assign(form, draft ? JSON.parse(draft) : emptyForm());
    }
    error.value = '';
    showPreview.value = false;
  },
  { immediate: true }
);

function emptyForm() {
  return {
    exam_id: '',
    question_text: '',
    question_type: 'multiple_choice',
    option_a: '',
    option_b: '',
    option_c: '',
    option_d: '',
    correct_answer: 'a',
    explanation: '',
    difficulty: 'medium',
    subject: 'general',
  };
}

function mapQuestion(q) {
  return {
    exam_id: q.exam_id,
    question_text: q.question_text || '',
    question_type: q.question_type || 'multiple_choice',
    option_a: q.option_a || '',
    option_b: q.option_b || '',
    option_c: q.option_c || '',
    option_d: q.option_d || '',
    correct_answer: q.correct_answer || 'a',
    explanation: q.explanation || '',
    difficulty: q.difficulty || 'medium',
    subject: q.subject || 'general',
  };
}

function autosave() {
  if (props.question?.id) return;
  localStorage.setItem(DRAFT_KEY, JSON.stringify({ ...form }));
}

async function submit() {
  saving.value = true;
  error.value = '';
  try {
    const payload = {
      ...form,
      exam_id: Number(form.exam_id),
    };
    emit('saved', { id: props.question?.id || null, payload });
    if (!props.question?.id) localStorage.removeItem(DRAFT_KEY);
  } catch (e) {
    error.value = e.response?.data?.message || 'ذخیره ناموفق بود.';
  } finally {
    saving.value = false;
  }
}
</script>

<style scoped>
.label {
  @apply mb-1 block text-xs font-medium text-slate-600;
}
.field {
  @apply w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-orange-400;
}
</style>
