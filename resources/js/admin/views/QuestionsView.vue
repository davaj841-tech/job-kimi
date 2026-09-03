<template>
  <div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-3">
        <h1 class="text-2xl font-bold text-gray-800">بانک سوالات</h1>
        <span
          class="rounded-full bg-orange-100 px-3 py-1 text-xs font-bold text-orange-700"
        >
          {{ fa(totalQuestions) }}
        </span>
      </div>
      <div class="flex flex-wrap gap-2">
        <button class="btn-dark" @click="openCreate">سوال جدید</button>
        <button class="btn-muted" @click="subjectManagerOpen = true">
          📚 مدیریت دروس
        </button>
        <button class="btn-muted" @click="importOpen = true">ورود Excel</button>
        <button class="btn-muted" @click="fullExamImportOpen = true">
          ورود آزمون کامل
        </button>
        <button class="btn-muted" @click="onExport">خروجی Excel</button>
        <button class="btn-muted" @click="toggleDuplicates">
          {{ showDuplicates ? 'بازگشت به بانک' : 'سوالات تکراری بین آزمون‌ها' }}
        </button>
        <button class="btn-orange" @click="aiOpen = true">تولید با AI</button>
      </div>
    </div>

    <div class="rounded-xl bg-white p-4 shadow-sm">
      <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-5">
        <input
          v-model="store.filters.search"
          class="field lg:col-span-2"
          placeholder="جستجو متن سوال"
          @keyup.enter="apply"
        />
        <select v-model="store.filters.exam_id" class="field">
          <option value="">همه آزمون‌ها</option>
          <option v-for="e in examsStore.exams" :key="e.id" :value="e.id">
            {{ e.title }}
          </option>
        </select>
        <select v-model="store.filters.subject" class="field">
          <option value="">همه دروس</option>
          <option
            v-for="s in subjectsStore.subjects"
            :key="s.slug"
            :value="s.slug"
          >
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

    <div v-if="showDuplicates" class="rounded-xl bg-white p-4 shadow-sm">
      <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <div>
          <h2 class="text-lg font-bold text-slate-800">
            سوالات تکراری بین آزمون‌های مختلف
          </h2>
          <p class="mt-1 text-xs text-slate-500">
            {{ fa(duplicateMeta.total_groups || 0) }} گروه تکراری —
            {{ fa(duplicateMeta.total_questions || 0) }} سوال
          </p>
        </div>
        <button
          class="btn-muted"
          :disabled="duplicatesLoading"
          @click="loadDuplicates"
        >
          {{ duplicatesLoading ? '...' : 'بروزرسانی' }}
        </button>
      </div>

      <div
        v-if="duplicatesLoading"
        class="py-8 text-center text-sm text-slate-500"
      >
        در حال بررسی تکراری‌ها...
      </div>
      <div
        v-else-if="!duplicateGroups.length"
        class="py-8 text-center text-sm text-slate-500"
      >
        سوال تکراری بین آزمون‌های مختلف یافت نشد.
      </div>
      <div v-else class="space-y-3">
        <div
          class="flex flex-wrap items-end gap-3 rounded-xl border border-orange-100 bg-orange-50/50 p-3"
        >
          <div class="min-w-[12rem] flex-1">
            <label class="mb-1 block text-xs font-bold text-slate-600"
              >اختصاص گروه‌های انتخاب‌شده به آزمون</label
            >
            <select v-model="bulkAssignExamId" class="field">
              <option value="">انتخاب آزمون مقصد</option>
              <option v-for="e in examsStore.exams" :key="e.id" :value="e.id">
                {{ e.title }}
              </option>
            </select>
          </div>
          <button
            class="btn-orange"
            :disabled="
              !bulkAssignExamId || !selectedFingerprints.length || assigning
            "
            @click="assignSelectedGroups"
          >
            {{ assigning ? 'در حال اختصاص...' : 'اختصاص انتخاب‌شده‌ها' }}
          </button>
        </div>

        <div
          v-for="group in duplicateGroups"
          :key="group.fingerprint"
          class="overflow-hidden rounded-xl border border-orange-100"
        >
          <div
            class="flex flex-wrap items-center gap-2 bg-orange-50 px-4 py-2 text-sm font-bold text-orange-800"
          >
            <input
              v-model="selectedFingerprints"
              type="checkbox"
              class="h-4 w-4 accent-orange-500"
              :value="group.fingerprint"
            />
            <span>
              {{ fa(group.count) }} بار در {{ fa(group.exam_count) }} آزمون
            </span>
          </div>
          <p
            class="border-b border-orange-100 px-4 py-3 text-sm text-slate-700"
          >
            {{ group.preview }}
          </p>
          <div class="divide-y divide-slate-100">
            <div
              v-for="item in group.questions"
              :key="item.id"
              class="flex flex-wrap items-center justify-between gap-2 px-4 py-2 text-sm"
            >
              <label
                class="flex min-w-0 flex-1 cursor-pointer items-center gap-2"
              >
                <input
                  v-model="assignSources[group.fingerprint]"
                  type="radio"
                  class="h-4 w-4 accent-orange-500"
                  :name="`src-${group.fingerprint}`"
                  :value="item.id"
                />
                <div>
                  <p class="font-bold text-slate-800">{{ item.exam_title }}</p>
                  <p class="text-xs text-slate-500">
                    سوال #{{ fa(item.id) }} — پاسخ
                    {{ answerLabel(item.correct_answer) }}
                  </p>
                </div>
              </label>
              <div class="flex gap-2">
                <button class="act" @click="openEditById(item.id)">
                  ویرایش
                </button>
                <button class="act" @click="goExamQuestions(item.exam_id)">
                  آزمون
                </button>
              </div>
            </div>
          </div>
          <div
            class="flex flex-wrap items-center gap-2 border-t border-orange-100 bg-slate-50 px-4 py-2"
          >
            <select
              v-model="assignTargets[group.fingerprint]"
              class="field max-w-xs flex-1"
            >
              <option value="">آزمون مقصد</option>
              <option v-for="e in examsStore.exams" :key="e.id" :value="e.id">
                {{ e.title }}
              </option>
            </select>
            <button
              class="btn-orange text-xs"
              :disabled="!assignTargets[group.fingerprint] || assigning"
              @click="assignGroup(group)"
            >
              اختصاص این سوال
            </button>
          </div>
        </div>
      </div>
    </div>

    <div
      v-if="loadingExams"
      class="rounded-xl bg-white p-8 text-center text-sm text-slate-500 shadow-sm"
    >
      در حال بارگذاری آزمون‌ها...
    </div>

    <div v-else-if="!showDuplicates" class="space-y-3">
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
          <span
            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-lg font-bold text-slate-600"
          >
            {{ isExpanded(exam.id) ? '−' : '+' }}
          </span>
        </button>

        <div v-if="isExpanded(exam.id)" class="border-t border-slate-100">
          <div
            v-if="bucket(exam.id).loading"
            class="p-6 text-center text-sm text-slate-500"
          >
            در حال بارگذاری سوالات...
          </div>
          <div
            v-else-if="!bucket(exam.id).questions.length"
            class="p-6 text-center text-sm text-slate-500"
          >
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
                  <td class="px-3 py-2 text-slate-500">
                    {{ fa(index + 1) }}
                  </td>
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
                    <span
                      class="rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-700"
                    >
                      {{ answerLabel(row.correct_answer) }}
                    </span>
                  </td>
                  <td class="px-3 py-2">
                    <div class="flex justify-end gap-1">
                      <button type="button" class="act" @click="openEdit(row)">
                        ویرایش
                      </button>
                      <button
                        type="button"
                        class="act text-red-600"
                        @click="askDelete(row)"
                      >
                        حذف
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <p
        v-if="!visibleExams.length"
        class="rounded-xl bg-white py-8 text-center text-slate-500 shadow-sm"
      >
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
  <FullExamImportModal
    ref="fullExamImportRef"
    :open="fullExamImportOpen"
    @close="fullExamImportOpen = false"
    @imported="onFullExamImport"
  />
  <AIGenerateModal
    ref="aiRef"
    :open="aiOpen"
    :exams="examsStore.exams"
    @close="aiOpen = false"
    @generate="onGenerate"
  />
  <SubjectManagerModal
    :open="subjectManagerOpen"
    @close="subjectManagerOpen = false"
  />
  <ConfirmDialog
    :open="confirm.open"
    :title="confirm.title"
    :message="confirm.message"
    @cancel="confirm.open = false"
    @confirm="runConfirm"
  />
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import adminApi from '../api/client'
import ConfirmDialog from '../components/ui/ConfirmDialog.vue'
import AIGenerateModal from '../components/questions/AIGenerateModal.vue'
import BulkImportModal from '../components/questions/BulkImportModal.vue'
import FullExamImportModal from '../components/questions/FullExamImportModal.vue'
import QuestionModal from '../components/questions/QuestionModal.vue'
import SubjectManagerModal from '../components/questions/SubjectManagerModal.vue'
import { useToast } from '../../composables/useToast'
import { useExamsStore } from '../stores/exams'
import { useExamSubjectsStore } from '../stores/examSubjects'
import { useQuestionsStore } from '../stores/questions'

const route = useRoute()
const store = useQuestionsStore()
const examsStore = useExamsStore()
const subjectsStore = useExamSubjectsStore()
const toast = useToast()

const modalOpen = ref(false)
const importOpen = ref(false)
const fullExamImportOpen = ref(false)
const aiOpen = ref(false)
const subjectManagerOpen = ref(false)
const editing = ref(null)
const importRef = ref(null)
const fullExamImportRef = ref(null)
const aiRef = ref(null)
const loadingExams = ref(true)
const showDuplicates = ref(false)
const duplicatesLoading = ref(false)
const duplicateGroups = ref([])
const duplicateMeta = reactive({ total_groups: 0, total_questions: 0 })
const assignTargets = reactive({})
const assignSources = reactive({})
const selectedFingerprints = ref([])
const bulkAssignExamId = ref('')
const assigning = ref(false)
const expanded = ref(new Set())
const examBuckets = reactive({})
const confirm = reactive({ open: false, title: '', message: '', action: null })

const answerMap = { a: 'الف', b: 'ب', c: 'ج', d: 'د' }

const visibleExams = computed(() => {
  const list = examsStore.exams || []
  if (!store.filters.exam_id) return list
  return list.filter((e) => String(e.id) === String(store.filters.exam_id))
})

const totalQuestions = computed(() =>
  visibleExams.value.reduce(
    (sum, e) => sum + Number(e.question_count ?? e.total_questions ?? 0),
    0
  )
)

function fa(n) {
  return new Intl.NumberFormat('fa-IR').format(Number(n || 0))
}
function stripHtml(t) {
  return String(t || '')
    .replace(/<[^>]+>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
}
function truncate(t) {
  const s = String(t || '')
  return s.length > 100 ? `${s.slice(0, 100)}…` : s
}
function subjectLabel(v) {
  return subjectsStore.subjects.find((s) => s.slug === v)?.name || v || '—'
}
function diffLabel(v) {
  return { easy: 'آسان', medium: 'متوسط', hard: 'سخت' }[v] || v
}
function answerLabel(v) {
  const key = String(v || '').toLowerCase()
  return answerMap[key] || String(v || '—').toUpperCase()
}
function isExpanded(id) {
  return expanded.value.has(Number(id))
}
function bucket(id) {
  const key = Number(id)
  if (!examBuckets[key]) {
    examBuckets[key] = { loading: false, questions: [] }
  }
  return examBuckets[key]
}

async function loadExamQuestions(examId) {
  const b = bucket(examId)
  b.loading = true
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
    })
    b.questions = data.data || []
  } catch (e) {
    toast.error(e.response?.data?.message || 'بارگذاری سوالات ناموفق بود.')
    b.questions = []
  } finally {
    b.loading = false
  }
}

async function toggleExam(id) {
  const key = Number(id)
  const next = new Set(expanded.value)
  if (next.has(key)) {
    next.delete(key)
    expanded.value = next
    return
  }
  next.add(key)
  expanded.value = next
  await loadExamQuestions(key)
}

async function refreshExpanded() {
  const ids = [...expanded.value]
  await Promise.all(ids.map((id) => loadExamQuestions(id)))
}

async function apply() {
  await refreshExpanded()
}
async function clear() {
  store.resetFilters()
  expanded.value = new Set()
}

function openCreate() {
  editing.value = null
  modalOpen.value = true
}
async function openEdit(row) {
  try {
    editing.value = await store.fetchQuestion(row.id)
    modalOpen.value = true
  } catch (e) {
    toast.error(e.response?.data?.message || 'بارگذاری سوال ناموفق بود.')
  }
}
function askDelete(row) {
  confirm.open = true
  confirm.title = 'حذف سوال'
  confirm.message = `این سوال از آزمون «${row.exam_title || 'نامشخص'}» حذف شود؟`
  confirm.action = async () => {
    try {
      await store.deleteQuestion(row.id)
      toast.success('سوال حذف شد.')
      await refreshExpanded()
      await examsStore.fetchExamOptions().catch(() => {})
    } catch (e) {
      toast.error(e.response?.data?.message || 'حذف ناموفق بود.')
    }
  }
}
async function runConfirm() {
  const fn = confirm.action
  confirm.open = false
  if (fn) await fn()
}
async function onSaved({ id, payload }) {
  try {
    if (id) await store.updateQuestion(id, payload)
    else await store.createQuestion(payload)
    modalOpen.value = false
    toast.success('سوال ذخیره شد.')
    await examsStore.fetchExamOptions().catch(() => {})
    await refreshExpanded()
  } catch (e) {
    toast.error(e.response?.data?.message || 'ذخیره ناموفق بود.')
  }
}
async function onImport({ file, exam_id }) {
  try {
    const result = await store.importQuestions({ file, exam_id })
    importRef.value?.setResult(result)
    const created = result?.created ?? 0
    const skipped = result?.skipped ?? 0
    const duplicates = result?.duplicates ?? 0
    if (created > 0) {
      toast.success(
        `${created} سوال وارد شد${skipped ? ` (${skipped} رد شد${duplicates ? `، ${duplicates} تکراری` : ''})` : ''}.`
      )
    } else if (duplicates > 0) {
      const msg =
        result?.errors?.[0] || `${duplicates} سوال تکراری بود و وارد نشد.`
      toast.error(msg)
      importRef.value?.setError(msg)
    } else {
      toast.error(result?.errors?.[0] || 'هیچ سوالی وارد نشد.')
      importRef.value?.setError(
        result?.errors?.[0] ||
          'هیچ سوالی وارد نشد. ستون‌های فارسی نمونه را بررسی کنید.'
      )
    }
    await examsStore.fetchExamOptions().catch(() => {})
    if (exam_id) {
      const next = new Set(expanded.value)
      next.add(Number(exam_id))
      expanded.value = next
      await loadExamQuestions(exam_id)
    }
  } catch (e) {
    const data = e.response?.data
    const msg =
      data?.errors?.file?.[0] ||
      data?.errors?.exam_id?.[0] ||
      data?.message ||
      'ورود ناموفق بود.'
    importRef.value?.setError(msg)
    toast.error(msg)
  }
}

async function onFullExamImport({ file }) {
  fullExamImportRef.value?.setLoading(true)
  try {
    const result = await store.importFullExam({ file })
    fullExamImportRef.value?.setResult(result)
    const created = result?.created ?? 0
    const examId = result?.exam?.id
    if (created > 0 && examId) {
      toast.success(`آزمون «${result.exam.title}» با ${created} سوال ایجاد شد.`)
    } else if (examId) {
      toast.error(
        result?.errors?.[0] ||
          'آزمون ساخته شد ولی هیچ سوالی وارد نشد. شیت سوالات را بررسی کنید.'
      )
      fullExamImportRef.value?.setError(
        result?.errors?.[0] || 'هیچ سوالی وارد نشد.'
      )
    } else {
      toast.error(result?.errors?.[0] || 'ورود آزمون کامل ناموفق بود.')
      fullExamImportRef.value?.setError(
        result?.errors?.[0] || 'ورود آزمون کامل ناموفق بود.'
      )
    }
    await examsStore.fetchExamOptions().catch(() => {})
    if (examId) {
      const next = new Set(expanded.value)
      next.add(Number(examId))
      expanded.value = next
      await loadExamQuestions(examId)
    }
  } catch (e) {
    const data = e.response?.data
    const msg =
      data?.errors?.file?.[0] || data?.message || 'ورود آزمون کامل ناموفق بود.'
    fullExamImportRef.value?.setError(msg)
    toast.error(msg)
  }
}

async function onExport() {
  try {
    await store.exportQuestions()
    toast.success('فایل خروجی آماده شد.')
  } catch (e) {
    toast.error(e.response?.data?.message || 'خروجی ناموفق بود.')
  }
}

function toggleDuplicates() {
  showDuplicates.value = !showDuplicates.value
  if (showDuplicates.value && !duplicateGroups.value.length) {
    loadDuplicates()
  }
}

async function loadDuplicates() {
  duplicatesLoading.value = true
  try {
    const { data } = await adminApi.get('/admin/questions/duplicates', {
      params: { per_page: 50 },
    })
    const payload = data?.data || data || {}
    duplicateGroups.value = payload.groups || []
    duplicateMeta.total_groups = payload.total_groups || 0
    duplicateMeta.total_questions = payload.total_questions || 0
    for (const group of duplicateGroups.value) {
      const fp = group.fingerprint
      if (!assignSources[fp] && group.questions?.length) {
        assignSources[fp] = group.questions[0].id
      }
    }
  } catch (e) {
    toast.error(e.response?.data?.message || 'بارگذاری تکراری‌ها ناموفق بود.')
  } finally {
    duplicatesLoading.value = false
  }
}

async function openEditById(id) {
  try {
    const question = await store.fetchQuestion(id)
    editing.value = question
    modalOpen.value = true
  } catch {
    toast.error('سوال یافت نشد.')
  }
}

function goExamQuestions(examId) {
  showDuplicates.value = false
  store.filters.exam_id = String(examId)
  const next = new Set(expanded.value)
  next.add(Number(examId))
  expanded.value = next
  loadExamQuestions(examId)
}

function buildSourceMap(fingerprints) {
  const map = {}
  for (const fp of fingerprints) {
    if (assignSources[fp]) {
      map[fp] = assignSources[fp]
    }
  }
  return map
}

async function assignGroup(group) {
  const examId = assignTargets[group.fingerprint]
  if (!examId) {
    toast.error('آزمون مقصد را انتخاب کنید.')
    return
  }
  assigning.value = true
  try {
    const result = await store.copyToExam({
      exam_id: examId,
      fingerprints: [group.fingerprint],
      source_question_ids: buildSourceMap([group.fingerprint]),
    })
    toast.success(
      result.created > 0
        ? `${result.created} سوال به آزمون اختصاص یافت.`
        : 'سوالی اضافه نشد (احتمالاً قبلاً در آزمون وجود دارد).'
    )
    await examsStore.fetchExamOptions().catch(() => {})
    if (examId) {
      const next = new Set(expanded.value)
      next.add(Number(examId))
      expanded.value = next
      await loadExamQuestions(examId)
    }
  } catch (e) {
    toast.error(e.response?.data?.message || 'اختصاص سوال ناموفق بود.')
  } finally {
    assigning.value = false
  }
}

async function assignSelectedGroups() {
  if (!bulkAssignExamId.value || !selectedFingerprints.value.length) return
  assigning.value = true
  try {
    const result = await store.copyToExam({
      exam_id: bulkAssignExamId.value,
      fingerprints: selectedFingerprints.value,
      source_question_ids: buildSourceMap(selectedFingerprints.value),
    })
    toast.success(
      `${result.created} سوال اختصاص یافت${result.skipped ? ` (${result.skipped} رد شد)` : ''}.`
    )
    selectedFingerprints.value = []
    await examsStore.fetchExamOptions().catch(() => {})
    const next = new Set(expanded.value)
    next.add(Number(bulkAssignExamId.value))
    expanded.value = next
    await loadExamQuestions(bulkAssignExamId.value)
  } catch (e) {
    toast.error(e.response?.data?.message || 'اختصاص سوالات ناموفق بود.')
  } finally {
    assigning.value = false
  }
}
async function onGenerate(params) {
  aiRef.value?.setLoading(true)
  try {
    const res = await store.generateWithAI(params)
    aiRef.value?.setMessage(
      res.message || 'تولید در صف قرار گرفت. پس از تایید ادمین اضافه می‌شود.'
    )
    toast.success('درخواست تولید ثبت شد.')
  } catch (e) {
    aiRef.value?.setError(e.response?.data?.message || 'تولید ناموفق بود.')
  } finally {
    aiRef.value?.setLoading(false)
  }
}

onMounted(async () => {
  if (route.query.exam_id) {
    store.filters.exam_id = String(route.query.exam_id)
  }
  loadingExams.value = true
  try {
    await Promise.all([
      examsStore.fetchExamOptions(),
      subjectsStore.fetchSubjects(),
    ])
    if (route.query.exam_id) {
      await toggleExam(Number(route.query.exam_id))
    }
  } finally {
    loadingExams.value = false
  }
})
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
