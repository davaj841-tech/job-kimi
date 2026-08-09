<template>
  <AdminLayout>
    <div class="space-y-5">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <h1 class="text-2xl font-bold text-gray-800">بانک سوالات</h1>
          <span class="rounded-full bg-orange-100 px-3 py-1 text-xs font-bold text-orange-700">
            {{ fa(totalQuestions) }}
          </span>
        </div>
        <div class="flex flex-wrap gap-2">
          <button class="btn-dark" @click="openCreate">سوال جدید</button>
          <button class="btn-muted" @click="subjectManagerOpen = true">📚 مدیریت دروس</button>
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
            <option v-for="s in subjectsStore.subjects" :key="s.slug" :value="s.slug">
              {{ s.icon || '📘' }} {{ s.name }}
            </option>
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

      <div v-if="loadingExams" class="rounded-xl bg-white p-8 text-center text-sm text-slate-500 shadow-sm">
        در حال بارگذاری آزمون‌ها...
      </div>

      <div v-else class="space-y-3">
        <div
          v-for="exam in visibleExams"
          :key="exam.id"
          class="overflow-hidden rounded-xl bg-white shadow-sm"
        >
          <button
            type="button"
            class="flex w-full items-center justify-between gap-3 px-4 py-3 text-right hover:bg-slate-50"
            @click="toggleExam(exam.id)"
          >
            <div class="min-w-0 flex-1">
              <p class="truncate font-bold text-slate-800">{{ exam.title }}</p>
              <p class="mt-0.5 text-xs text-slate-500">
                {{ fa(exam.question_count ?? exam.total_questions ?? 0) }} سوال
              </p>
            </div>
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-lg font-bold text-slate-600">
              {{ isExpanded(exam.id) ? '−' : '+' }}
            </span>
          </button>

          <div v-if="isExpanded(exam.id)" class="border-t border-slate-100">
            <div v-if="bucket(exam.id).loading" class="p-6 text-center text-sm text-slate-500">
              در حال بارگذاری سوالات...
            </div>
            <div v-else-if="!bucket(exam.id).questions.length" class="p-6 text-center text-sm text-slate-500">
              سوالی یافت نشد
            </div>
            <div v-else class="overflow-x-auto">
              <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500">
                  <tr>
                    <th class="px-3 py-2 text-right font-medium">#</th>
                    <th class="px-3 py-2 text-right font-medium">متن سوال</th>
                    <th class="px-3 py-2 text-right font-medium">درس</th>
                    <th class="px-3 py-2 text-right font-medium">سطح</th>
                    <th class="px-3 py-2 text-right font-medium">پاسخ صحیح</th>
                    <th class="px-3 py-2 text-right font-medium">عملیات</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(row, index) in bucket(exam.id).questions"
                    :key="row.id"
                    class="border-t border-slate-100"
                  >
                    <td class="px-3 py-2 text-slate-500">{{ fa(index + 1) }}</td>
                    <td class="px-3 py-2">
                      <button
                        type="button"
                        class="max-w-md text-right hover:text-orange-600"
                        :title="stripHtml(row.question_text)"
                        @click="openEdit(row)"
                      >
                        {{ truncate(stripHtml(row.question_text)) }}
                      </button>
                    </td>
                    <td class="px-3 py-2">{{ subjectLabel(row.subject) }}</td>
                    <td class="px-3 py-2">{{ diffLabel(row.difficulty) }}</td>
                    <td class="px-3 py-2">
                      <span class="rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-700">
                        {{ answerLabel(row.correct_answer) }}
                      </span>
                    </td>
                    <td class="px-3 py-2">
                      <div class="flex justify-end gap-1">
                        <button type="button" class="act" @click="openEdit(row)">ویرایش</button>
                        <button type="button" class="act text-red-600" @click="askDelete(row)">حذف</button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <p v-if="!visibleExams.length" class="rounded-xl bg-white py-8 text-center text-slate-500 shadow-sm">
          آزمونی یافت نشد
        </p>
      </div>
    </div>

    <QuestionModal
      :open="modalOpen"
      :question="editing"
      :exams="examsStore.exams"
      @close="modalOpen = false"
      @saved="onSaved"
    />
    <BulkImportModal
      ref="importRef"
      :open="importOpen"
      :exams="examsStore.exams"
      @close="importOpen = false"
      @imported="onImport"
    />
    <AIGenerateModal ref="aiRef" :open="aiOpen" :exams="examsStore.exams" @close="aiOpen = false" @generate="onGenerate" />
    <SubjectManagerModal :open="subjectManagerOpen" @close="subjectManagerOpen = false" />
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
import adminApi from '../api/client';
import AdminLayout from '../components/layout/AdminLayout.vue';
import ConfirmDialog from '../components/ui/ConfirmDialog.vue';
import AIGenerateModal from '../components/questions/AIGenerateModal.vue';
import BulkImportModal from '../components/questions/BulkImportModal.vue';
import QuestionModal from '../components/questions/QuestionModal.vue';
import SubjectManagerModal from '../components/questions/SubjectManagerModal.vue';
import { useToast } from '../../composables/useToast';
import { useExamsStore } from '../stores/exams';
import { useExamSubjectsStore } from '../stores/examSubjects';
import { useQuestionsStore } from '../stores/questions';

const route = useRoute();
const store = useQuestionsStore();
const examsStore = useExamsStore();
const subjectsStore = useExamSubjectsStore();
const toast = useToast();

const modalOpen = ref(false);
const importOpen = ref(false);
const aiOpen = ref(false);
const subjectManagerOpen = ref(false);
const editing = ref(null);
const importRef = ref(null);
const aiRef = ref(null);
const loadingExams = ref(true);
const expanded = ref(new Set());
const examBuckets = reactive({});
const confirm = reactive({ open: false, title: '', message: '', action: null });

const answerMap = { a: 'الف', b: 'ب', c: 'ج', d: 'د' };

const visibleExams = computed(() => {
  const list = examsStore.exams || [];
  if (!store.filters.exam_id) return list;
  return list.filter((e) => String(e.id) === String(store.filters.exam_id));
});

const totalQuestions = computed(() =>
  visibleExams.value.reduce((sum, e) => sum + Number(e.question_count ?? e.total_questions ?? 0), 0)
);

function fa(n) {
  return new Intl.NumberFormat('fa-IR').format(Number(n || 0));
}
function stripHtml(t) {
  return String(t || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
}
function truncate(t) {
  const s = String(t || '');
  return s.length > 100 ? `${s.slice(0, 100)}…` : s;
}
function subjectLabel(v) {
  return subjectsStore.subjects.find((s) => s.slug === v)?.name || v || '—';
}
function diffLabel(v) {
  return { easy: 'آسان', medium: 'متوسط', hard: 'سخت' }[v] || v;
}
function answerLabel(v) {
  const key = String(v || '').toLowerCase();
  return answerMap[key] || String(v || '—').toUpperCase();
}
function isExpanded(id) {
  return expanded.value.has(Number(id));
}
function bucket(id) {
  const key = Number(id);
  if (!examBuckets[key]) {
    examBuckets[key] = { loading: false, questions: [] };
  }
  return examBuckets[key];
}

async function loadExamQuestions(examId) {
  const b = bucket(examId);
  b.loading = true;
  try {
    const { data } = await adminApi.get('/admin/questions', {
      params: {
        search: store.filters.search || undefined,
        subject: store.filters.subject || undefined,
        difficulty: store.filters.difficulty || undefined,
        question_type: store.filters.question_type || undefined,
        exam_id: examId,
        per_page: 200,
        page: 1,
      },
    });
    b.questions = data.data || [];
  } catch (e) {
    toast.error(e.response?.data?.message || 'بارگذاری سوالات ناموفق بود.');
    b.questions = [];
  } finally {
    b.loading = false;
  }
}

async function toggleExam(id) {
  const key = Number(id);
  const next = new Set(expanded.value);
  if (next.has(key)) {
    next.delete(key);
    expanded.value = next;
    return;
  }
  next.add(key);
  expanded.value = next;
  await loadExamQuestions(key);
}

async function refreshExpanded() {
  const ids = [...expanded.value];
  await Promise.all(ids.map((id) => loadExamQuestions(id)));
}

async function apply() {
  await refreshExpanded();
}
async function clear() {
  store.resetFilters();
  expanded.value = new Set();
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
      await refreshExpanded();
      await examsStore.fetchExamOptions().catch(() => {});
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
    await examsStore.fetchExamOptions().catch(() => {});
    await refreshExpanded();
  } catch (e) {
    toast.error(e.response?.data?.message || 'ذخیره ناموفق بود.');
  }
}
async function onImport({ file, exam_id }) {
  try {
    const result = await store.importQuestions({ file, exam_id });
    importRef.value?.setResult(result);
    toast.success('ورود اکسل انجام شد.');
    await examsStore.fetchExamOptions().catch(() => {});
    if (exam_id) {
      const next = new Set(expanded.value);
      next.add(Number(exam_id));
      expanded.value = next;
      await loadExamQuestions(exam_id);
    }
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
  loadingExams.value = true;
  try {
    await Promise.all([examsStore.fetchExamOptions(), subjectsStore.fetchSubjects()]);
    if (route.query.exam_id) {
      await toggleExam(Number(route.query.exam_id));
    }
  } finally {
    loadingExams.value = false;
  }
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
