<template>
  <AdminLayout>
    <div class="space-y-5">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <h1 class="text-2xl font-bold text-gray-800">بانک سوالات</h1>
          <span class="rounded-full bg-orange-100 px-3 py-1 text-xs font-bold text-orange-700">
            {{ fa(store.meta.total || 0) }}
          </span>
        </div>
        <div class="flex flex-wrap gap-2">
          <button class="btn-dark" @click="openCreate">سوال جدید</button>
          <button class="btn-muted" @click="importOpen = true">ورود Excel</button>
          <button class="btn-muted" @click="onExport">خروجی Excel</button>
          <button class="btn-orange" @click="aiOpen = true">تولید با AI</button>
        </div>
      </div>

      <div class="rounded-xl bg-white p-4 shadow-sm">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-5">
          <input v-model="store.filters.search" class="field lg:col-span-2" placeholder="جستجو متن سوال" @keyup.enter="apply" />
          <select v-model="store.filters.exam_id" class="field">
            <option value="">همه آزمون‌ها</option>
            <option v-for="e in examsStore.exams" :key="e.id" :value="e.id">{{ e.title }}</option>
          </select>
          <select v-model="store.filters.subject" class="field">
            <option value="">همه دروس</option>
            <option v-for="s in subjects" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
          <select v-model="store.filters.difficulty" class="field">
            <option value="">همه سطوح</option>
            <option value="easy">آسان</option>
            <option value="medium">متوسط</option>
            <option value="hard">سخت</option>
          </select>
        </div>
        <div class="mt-3 flex flex-wrap gap-2">
          <select v-model="store.filters.question_type" class="field max-w-xs">
            <option value="">همه انواع</option>
            <option value="multiple_choice">چهارگزینه‌ای</option>
            <option value="formula">فرمول</option>
          </select>
          <button class="btn-orange" @click="apply">اعمال فیلتر</button>
          <button class="btn-muted" @click="clear">پاک کردن</button>
        </div>
      </div>

      <DataTable :columns="columns" :rows="store.questions" :loading="store.loading" actions>
        <template #cell-index="{ index }">{{ fa(rowNum(index)) }}</template>
        <template #cell-question_text="{ row }">
          <button class="max-w-md text-right hover:text-orange-600" @click="openEdit(row)" :title="row.question_text">
            {{ truncate(row.question_text) }}
          </button>
        </template>
        <template #cell-exam_title="{ row }">{{ row.exam_title || '—' }}</template>
        <template #cell-subject="{ row }">{{ subjectLabel(row.subject) }}</template>
        <template #cell-difficulty="{ row }">{{ diffLabel(row.difficulty) }}</template>
        <template #cell-correct_answer="{ row }">
          <span class="rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-700">
            {{ String(row.correct_answer || '').toUpperCase() }}
          </span>
        </template>
        <template #actions="{ row }">
          <div class="flex justify-end gap-1">
            <button class="act" @click="openEdit(row)">ویرایش</button>
            <button class="act text-red-600" @click="askDelete(row)">حذف</button>
          </div>
        </template>
        <template #empty>
          <p class="py-6 text-slate-500">سوالی یافت نشد</p>
        </template>
      </DataTable>

      <div v-if="store.meta.last_page" class="flex items-center justify-between rounded-xl bg-white px-4 py-3 text-sm shadow-sm">
        <p class="text-slate-500">
          نمایش {{ fa(store.meta.from || 0) }} تا {{ fa(store.meta.to || 0) }} از {{ fa(store.meta.total || 0) }}
        </p>
        <div class="flex gap-1">
          <button :disabled="(store.meta.current_page || 1) <= 1" @click="go((store.meta.current_page || 1) - 1)">قبلی</button>
          <button
            v-for="p in pages"
            :key="p"
            class="min-w-8 rounded-lg px-2 py-1 text-xs font-bold"
            :class="p === store.meta.current_page ? 'bg-orange-500 text-white' : 'bg-slate-100'"
            @click="go(p)"
          >
            {{ fa(p) }}
          </button>
          <button
            :disabled="(store.meta.current_page || 1) >= (store.meta.last_page || 1)"
            @click="go((store.meta.current_page || 1) + 1)"
          >
            بعدی
          </button>
        </div>
      </div>
    </div>

    <QuestionModal
      :open="modalOpen"
      :question="editing"
      :exams="examsStore.exams"
      @close="modalOpen = false"
      @saved="onSaved"
    />
    <BulkImportModal ref="importRef" :open="importOpen" @close="importOpen = false" @imported="onImport" />
    <AIGenerateModal ref="aiRef" :open="aiOpen" :exams="examsStore.exams" @close="aiOpen = false" @generate="onGenerate" />
    <ConfirmDialog
      :open="confirm.open"
      :title="confirm.title"
      :message="confirm.message"
      @cancel="confirm.open = false"
      @confirm="runConfirm"
    />
  </AdminLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import AdminLayout from '../components/layout/AdminLayout.vue';
import ConfirmDialog from '../components/ui/ConfirmDialog.vue';
import DataTable from '../components/ui/DataTable.vue';
import AIGenerateModal from '../components/questions/AIGenerateModal.vue';
import BulkImportModal from '../components/questions/BulkImportModal.vue';
import QuestionModal from '../components/questions/QuestionModal.vue';
import { useToast } from '../../composables/useToast';
import { useExamsStore } from '../stores/exams';
import { useQuestionsStore } from '../stores/questions';

const route = useRoute();
const store = useQuestionsStore();
const examsStore = useExamsStore();
const toast = useToast();

const modalOpen = ref(false);
const importOpen = ref(false);
const aiOpen = ref(false);
const editing = ref(null);
const importRef = ref(null);
const aiRef = ref(null);
const confirm = reactive({ open: false, title: '', message: '', action: null });

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

const columns = [
  { key: 'index', label: '#' },
  { key: 'question_text', label: 'متن سوال' },
  { key: 'exam_title', label: 'آزمون' },
  { key: 'subject', label: 'درس' },
  { key: 'difficulty', label: 'سطح' },
  { key: 'correct_answer', label: 'پاسخ صحیح' },
];

const pages = computed(() => {
  const cur = store.meta.current_page || 1;
  const last = store.meta.last_page || 1;
  const out = [];
  for (let i = Math.max(1, cur - 2); i <= Math.min(last, Math.max(1, cur - 2) + 4); i++) out.push(i);
  return out;
});

function fa(n) {
  return new Intl.NumberFormat('fa-IR').format(Number(n || 0));
}
function rowNum(i) {
  return (store.meta.from || 1) + i;
}
function truncate(t) {
  const s = String(t || '');
  return s.length > 100 ? `${s.slice(0, 100)}…` : s;
}
function subjectLabel(v) {
  return subjects.find((s) => s.value === v)?.label || v || '—';
}
function diffLabel(v) {
  return { easy: 'آسان', medium: 'متوسط', hard: 'سخت' }[v] || v;
}

async function apply() {
  await store.fetchQuestions(1);
}
async function clear() {
  store.resetFilters();
  await store.fetchQuestions(1);
}
async function go(p) {
  await store.fetchQuestions(p);
}

function openCreate() {
  editing.value = null;
  modalOpen.value = true;
}
async function openEdit(row) {
  try {
    editing.value = await store.fetchQuestion(row.id);
    modalOpen.value = true;
  } catch (e) {
    toast.error(e.response?.data?.message || 'بارگذاری سوال ناموفق بود.');
  }
}
function askDelete(row) {
  confirm.open = true;
  confirm.title = 'حذف سوال';
  confirm.message = `این سوال از آزمون «${row.exam_title || 'نامشخص'}» حذف شود؟`;
  confirm.action = async () => {
    try {
      await store.deleteQuestion(row.id);
      toast.success('سوال حذف شد.');
    } catch (e) {
      toast.error(e.response?.data?.message || 'حذف ناموفق بود.');
    }
  };
}
async function runConfirm() {
  const fn = confirm.action;
  confirm.open = false;
  if (fn) await fn();
}
async function onSaved({ id, payload }) {
  try {
    if (id) await store.updateQuestion(id, payload);
    else await store.createQuestion(payload);
    modalOpen.value = false;
    toast.success('سوال ذخیره شد.');
  } catch (e) {
    toast.error(e.response?.data?.message || 'ذخیره ناموفق بود.');
  }
}
async function onImport(file) {
  try {
    const result = await store.importQuestions(file);
    importRef.value?.setResult(result);
    toast.success('ورود اکسل انجام شد.');
  } catch (e) {
    importRef.value?.setError(e.response?.data?.message || 'ورود ناموفق بود.');
  }
}
async function onExport() {
  try {
    await store.exportQuestions();
    toast.success('فایل خروجی آماده شد.');
  } catch (e) {
    toast.error(e.response?.data?.message || 'خروجی ناموفق بود.');
  }
}
async function onGenerate(params) {
  aiRef.value?.setLoading(true);
  try {
    const res = await store.generateWithAI(params);
    aiRef.value?.setMessage(res.message || 'تولید در صف قرار گرفت. پس از تایید ادمین اضافه می‌شود.');
    toast.success('درخواست تولید ثبت شد.');
  } catch (e) {
    aiRef.value?.setError(e.response?.data?.message || 'تولید ناموفق بود.');
  } finally {
    aiRef.value?.setLoading(false);
  }
}

onMounted(async () => {
  if (route.query.exam_id) {
    store.filters.exam_id = String(route.query.exam_id);
  }
  await Promise.all([
    examsStore.fetchExamOptions().catch(() => {}),
    store.fetchQuestions(1),
  ]);
});
</script>

<style scoped>
.field {
  @apply h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-orange-400;
}
.btn-dark {
  @apply rounded-xl bg-[#0f2744] px-4 py-2.5 text-sm font-bold text-white;
}
.btn-orange {
  @apply rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-bold text-white;
}
.btn-muted {
  @apply rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold text-slate-700;
}
.act {
  @apply rounded-lg bg-slate-100 px-2 py-1 text-[11px] font-bold text-slate-700;
}
</style>
