<template>
  <div class="min-h-dvh bg-slate-100 dark:bg-slate-950">
    <header
      class="sticky top-0 z-40 border-b border-surface-line bg-white/80 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-900/80"
    >
      <div class="mx-auto flex h-14 max-w-7xl items-center justify-between gap-2 px-3 sm:px-4">
        <div class="flex min-w-0 items-center gap-2">
          <button
            type="button"
            class="rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-slate-800"
            @click="$router.push('/resumes')"
          >
            <ArrowRightIcon class="h-5 w-5" />
          </button>
          <div class="min-w-0">
            <h1 class="truncate text-sm font-bold sm:text-base">رزومه‌ساز هوشمند</h1>
            <p
              v-if="title"
              class="truncate text-[11px] text-desk-muted"
            >
              {{ title }}
            </p>
          </div>
        </div>
        <div class="flex items-center gap-1.5 sm:gap-2">
          <div class="relative hidden md:block">
            <button
              type="button"
              class="cursor-pointer rounded-xl border border-surface-line px-3 py-2 text-xs font-bold"
              @click="themeOpen = !themeOpen"
            >
              قالب A4
            </button>
            <div
              v-if="themeOpen"
              class="absolute left-0 z-50 mt-2 w-[22rem] rounded-2xl border border-surface-line bg-white p-3 shadow-xl dark:border-slate-700 dark:bg-slate-900"
            >
              <ThemePicker
                :model-value="templateId"
                @update:model-value="pickTheme"
              />
            </div>
          </div>
          <button
            type="button"
            class="rounded-xl border border-surface-line px-3 py-2 text-xs font-medium lg:hidden dark:border-slate-700"
            @click="showPreview = true"
          >
            پیش‌نمایش
          </button>
          <button
            type="button"
            class="rounded-xl border border-brand/30 px-2 py-2 text-xs font-medium text-brand disabled:opacity-50 sm:px-3"
            :disabled="drafting"
            @click="draftWithAi"
          >
            {{ drafting ? '…' : 'پیش‌نویس AI' }}
          </button>
          <button
            type="button"
            class="rounded-xl border border-surface-line px-3 py-2 text-xs font-medium disabled:opacity-50 dark:border-slate-700"
            :disabled="saving"
            @click="save"
          >
            {{ saving ? '…' : 'ذخیره' }}
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-xl bg-brand px-3 py-2 text-xs font-medium text-white disabled:opacity-50"
            :disabled="exporting"
            @click="exportPDF"
          >
            <DocumentArrowDownIcon class="h-4 w-4" />
            <span class="hidden sm:inline">PDF</span>
          </button>
        </div>
      </div>
    </header>

    <LoadingSpinner v-if="loading" />

    <div
      v-else-if="resumeData"
      class="flex flex-col lg:h-[calc(100dvh-3.5rem)] lg:flex-row"
    >
      <div class="resume-pane min-w-0 flex-1 p-3 sm:p-5">
        <div class="space-y-4">
        <div class="scrollbar-hide flex items-center gap-2 overflow-x-auto pb-1">
          <button
            v-for="(step, idx) in steps"
            :key="step.id"
            type="button"
            class="flex shrink-0 items-center gap-2 rounded-xl px-3 py-2 text-xs whitespace-nowrap transition sm:text-sm"
            :class="
              currentStep === idx
                ? 'bg-brand text-white'
                : currentStep > idx
                  ? 'bg-brand-soft text-brand'
                  : 'bg-white text-desk-muted dark:bg-slate-800'
            "
            @click="currentStep = idx"
          >
            <span
              class="flex h-5 w-5 items-center justify-center rounded-full text-[10px]"
              :class="
                currentStep > idx
                  ? 'bg-brand text-white'
                  : currentStep === idx
                    ? 'bg-white/20'
                    : 'bg-slate-200 dark:bg-slate-700'
              "
            >
              <CheckIcon
                v-if="currentStep > idx"
                class="h-3 w-3"
              />
              <span v-else>{{ idx + 1 }}</span>
            </span>
            {{ step.label }}
          </button>
        </div>

        <div class="md:hidden">
          <ThemePicker v-model="templateId" />
        </div>

        <div class="rounded-2xl border border-surface-line bg-white p-4 sm:p-6 dark:border-slate-800 dark:bg-slate-900">
          <Transition
            name="fade"
            mode="out-in"
          >
            <component
              :is="steps[currentStep].component"
              :key="steps[currentStep].id"
              v-model="resumeData"
              @fill-profile="fillFromProfile"
              @ai-summary="onAiSummary"
              @ai-enhance="onAiEnhance"
              @ai-skills="onAiSkills"
            />
          </Transition>
        </div>

        <div class="flex justify-between gap-2 pb-6">
          <button
            type="button"
            class="rounded-xl border border-surface-line px-5 py-2.5 text-sm disabled:opacity-30 dark:border-slate-700"
            :disabled="currentStep === 0"
            @click="goPrev"
          >
            قبلی
          </button>
          <button
            v-if="currentStep < steps.length - 1"
            type="button"
            class="rounded-xl bg-brand px-5 py-2.5 text-sm text-white"
            @click="goNext"
          >
            بعدی
          </button>
          <button
            v-else
            type="button"
            class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm text-white"
            @click="finishAndPreview"
          >
            مشاهده نهایی
          </button>
        </div>
        </div>
      </div>

      <div
        class="resume-splitter hidden lg:block"
        title="کشیدن برای تغییر عرض پیش‌نمایش"
        @mousedown.prevent="startResize"
      />

      <aside
        class="resume-pane hidden shrink-0 bg-slate-200 p-4 dark:bg-slate-950 lg:block"
        :style="{ width: previewWidth + 'px' }"
      >
        <p class="mb-3 text-center text-xs text-desk-muted">پیش‌نمایش A4</p>
        <div
          class="preview-stage"
          :style="{ '--preview-zoom': previewZoom }"
        >
          <ResumePreview
            :data="resumeData"
            :template-id="templateId"
          />
        </div>
      </aside>
    </div>

    <PreviewModal
      v-model="showPreview"
      :data="resumeData || emptyData"
      :template-id="templateId"
    />
    <PageScrollFab target=".resume-pane" />
  </div>
</template>

<script setup>
import { computed, markRaw, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import {
  ArrowRightIcon,
  CheckIcon,
  DocumentArrowDownIcon,
} from '@heroicons/vue/24/outline'
import api from '../../api/client'
import LoadingSpinner from '../../components/LoadingSpinner.vue'
import PreviewModal from '../../components/resume/PreviewModal.vue'
import ResumePreview from '../../components/resume/ResumePreview.vue'
import EducationStep from '../../components/resume/steps/EducationStep.vue'
import ExperienceStep from '../../components/resume/steps/ExperienceStep.vue'
import LanguagesStep from '../../components/resume/steps/LanguagesStep.vue'
import PersonalInfoStep from '../../components/resume/steps/PersonalInfoStep.vue'
import SkillsStep from '../../components/resume/steps/SkillsStep.vue'
import SummaryStep from '../../components/resume/steps/SummaryStep.vue'
import { useAuthStore } from '../../stores/auth'
import { useFeatureStore } from '../../stores/feature'
import { apiErrorMessage, unwrapItem } from '../../utils/format'
import { useToast } from '../../composables/useToast'
import ThemePicker from '../../components/resume/ThemePicker.vue'
import PageScrollFab from '../../components/ui/PageScrollFab.vue'

const route = useRoute()
const auth = useAuthStore()
const features = useFeatureStore()
const toast = useToast()

const templates = [
  { id: 'modern', name: 'مدرن', templateId: 1 },
  { id: 'minimal', name: 'مینیمال', templateId: 2 },
  { id: 'classic', name: 'کلاسیک', templateId: 3 },
  { id: 'creative', name: 'خلاقانه', templateId: 1 },
]

const steps = [
  { id: 'personal', label: 'اطلاعات شخصی', component: markRaw(PersonalInfoStep) },
  { id: 'summary', label: 'معرفی', component: markRaw(SummaryStep) },
  { id: 'experience', label: 'سوابق', component: markRaw(ExperienceStep) },
  { id: 'education', label: 'تحصیلات', component: markRaw(EducationStep) },
  { id: 'skills', label: 'مهارت‌ها', component: markRaw(SkillsStep) },
  { id: 'languages', label: 'زبان‌ها', component: markRaw(LanguagesStep) },
]

const emptyData = {
  personal: {
    full_name: '',
    birth_date: '',
    national_code: '',
    mobile: '',
    email: '',
    address: '',
    photo: null,
    birth_province: '',
    birth_city: '',
    marital_status: '',
    field_of_study: '',
    military_status: '',
    home_phone: '',
    insurance_history: '',
    postal_code: '',
  },
  education: [],
  experience: [],
  skills: [],
  languages: [],
  summary: '',
  target_job: '',
}

const loading = ref(true)
const saving = ref(false)
const exporting = ref(false)
const drafting = ref(false)
const currentStep = ref(0)
const activeTemplate = ref('modern')
const templateId = ref(1)
const themeOpen = ref(false)
const showPreview = ref(false)
const title = ref('')
const resumeData = ref(null)
const previewWidth = ref(
  Math.min(720, Math.max(280, Number(localStorage.getItem('ja_resume_preview_w')) || 440))
)
const previewZoom = computed(() => {
  const inner = Math.max(220, previewWidth.value - 32)
  return Math.min(0.82, Math.max(0.28, inner / 794))
})

function pickTheme(id) {
  templateId.value = id
  themeOpen.value = false
}

let resizing = false
function startResize(e) {
  resizing = true
  const startX = e.clientX
  const startW = previewWidth.value
  const move = (ev) => {
    if (!resizing) return
    const next = startW + (ev.clientX - startX)
    previewWidth.value = Math.min(760, Math.max(260, next))
  }
  const stop = () => {
    resizing = false
    document.removeEventListener('mousemove', move)
    document.removeEventListener('mouseup', stop)
    localStorage.setItem('ja_resume_preview_w', String(previewWidth.value))
  }
  document.addEventListener('mousemove', move)
  document.addEventListener('mouseup', stop)
}

const resumeId = computed(() => route.params.id)
const draftReady = ref(false)
let draftTimer

function draftKey() {
  return `ja_resume_draft_${resumeId.value}`
}

function persistDraft() {
  if (!draftReady.value || !resumeData.value) return
  const payload = {
    title: title.value,
    templateId: templateId.value,
    currentStep: currentStep.value,
    data: clonePlain(resumeData.value),
    savedAt: Date.now(),
  }
  try {
    localStorage.setItem(draftKey(), JSON.stringify(payload))
  } catch {
    try {
      if (payload.data?.personal) payload.data.personal.photo = null
      localStorage.setItem(draftKey(), JSON.stringify(payload))
    } catch {
      /* quota */
    }
  }
}

function readDraft() {
  try {
    const raw = localStorage.getItem(draftKey())
    return raw ? JSON.parse(raw) : null
  } catch {
    return null
  }
}

watch(
  [resumeData, templateId, title, currentStep],
  () => {
    if (!draftReady.value) return
    clearTimeout(draftTimer)
    draftTimer = setTimeout(persistDraft, 400)
  },
  { deep: true }
)

onUnmounted(() => {
  clearTimeout(draftTimer)
  persistDraft()
})

function formatProfileNational(raw) {
  const d = String(raw || '').replace(/\D/g, '').slice(0, 10)
  return d.length === 10 ? d : ''
}

function setTemplate(t) {
  activeTemplate.value = t.id
  templateId.value = t.templateId
}

function fillFromProfile() {
  const u = auth.user
  if (!u || !resumeData.value) return
  const p = resumeData.value.personal
  p.full_name = u.name || p.full_name
  p.email = u.email || p.email
  p.mobile = u.mobile || p.mobile
  const nc = formatProfileNational(u.national_code)
  if (nc) p.national_code = nc
  if (u.home_phone) p.home_phone = u.home_phone
  if (u.military_status) p.military_status = u.military_status
  if (u.insurance_history) p.insurance_history = u.insurance_history
  if (u.birth_date) p.birth_date = u.birth_date
  if (u.birth_province || u.province) p.birth_province = u.birth_province || u.province
  if (u.birth_city) p.birth_city = u.birth_city
  if (u.marital_status) p.marital_status = u.marital_status
  if (u.field_of_study) p.field_of_study = u.field_of_study
  if (u.address) p.address = u.address
  if (u.postal_code) p.postal_code = u.postal_code
  if (u.avatar && !p.photo) p.photo = u.avatar
  toast.success('اطلاعات پروفایل اعمال شد.')
}

function clonePlain(value) {
  try {
    return JSON.parse(
      JSON.stringify(value ?? {}, (_key, v) => {
        if (typeof v === 'function') return undefined
        if (v && typeof v === 'object') {
          if (typeof File !== 'undefined' && v instanceof File) return null
          if (typeof Blob !== 'undefined' && v instanceof Blob) return null
        }
        return v
      })
    )
  } catch {
    return JSON.parse(JSON.stringify(emptyData))
  }
}

function sanitizeData(data) {
  const d = clonePlain(data)
  ;(d.experience || []).forEach((e) => {
    delete e._key
    if (e.is_current) e.end_date = null
  })
  d.experience = (d.experience || []).filter((e) => String(e.title || '').trim() && String(e.company || '').trim())
  ;(d.education || []).forEach((e) => {
    delete e._key
    if (!e.start_date && e.start_year) e.start_date = `${e.start_year}-01`
    if (!e.end_date && e.end_year) e.end_date = `${e.end_year}-01`
    const sy = String(e.start_date || '').match(/^(\d{4})/)
    const ey = String(e.end_date || '').match(/^(\d{4})/)
    e.start_year = sy ? Number(sy[1]) : null
    e.end_year = ey ? Number(ey[1]) : null
    if (e.gpa === '' || e.gpa == null) e.gpa = null
    else {
      const digits = String(e.gpa).replace(/\D/g, '').slice(0, 3)
      if (digits.length === 3) e.gpa = `${digits.slice(0, 2)}.${digits.slice(2)}`
      else if (digits.length > 0) {
        const n = Number(e.gpa)
        e.gpa = Number.isFinite(n) ? n.toFixed(1) : null
      } else e.gpa = null
      if (e.gpa !== null && (Number(e.gpa) < 0 || Number(e.gpa) > 20 || !/^\d{1,2}\.\d$/.test(String(e.gpa)))) {
        e.gpa = null
      }
    }
  })
  d.education = (d.education || []).filter(
    (e) => String(e.field || '').trim() && String(e.university || '').trim()
  )
  d.skills = (d.skills || [])
    .filter((s) => String(s.name || '').trim())
    .map((s) => ({ name: s.name, level: s.level || 'متوسط' }))
  d.languages = (d.languages || []).filter((l) => String(l.name || '').trim())
  if (!d.personal.photo) d.personal.photo = null
  d.personal.national_code = String(d.personal.national_code || '').replace(/\D/g, '').slice(0, 10)
  if (!/^\d{10}$/.test(d.personal.national_code)) {
    d.personal.national_code = ''
  }
  if (!/^\d{2}\/\d{2}\/\d{4}$/.test(String(d.personal.birth_date || ''))) {
    d.personal.birth_date = ''
  }
  d.personal.postal_code = String(d.personal.postal_code || '').replace(/\D/g, '').slice(0, 10)
  if (d.personal.postal_code && !/^\d{10}$/.test(d.personal.postal_code)) {
    d.personal.postal_code = ''
  }
  if (!d.target_job) d.target_job = ''
  return d
}

async function save() {
  if (!resumeData.value) return
  saving.value = true
  try {
    const { data } = await api.put(`/resumes/${resumeId.value}`, {
      title: title.value,
      template_id: templateId.value,
      data: sanitizeData(resumeData.value),
    })
    const item = unwrapItem(data)
    title.value = item.title || title.value
    persistDraft()
    toast.success('رزومه ذخیره شد.')
  } catch (e) {
    toast.error(apiErrorMessage(e, 'خطا در ذخیره.'))
  } finally {
    saving.value = false
  }
}

async function exportPDF() {
  exporting.value = true
  try {
    await save()
    const { data } = await api.get(`/resumes/${resumeId.value}/pdf`, {
      responseType: 'blob',
    })
    if (data instanceof Blob && data.type && data.type.includes('json')) {
      const parsed = JSON.parse(await data.text())
      throw new Error(parsed.message || 'دانلود PDF ممکن نشد.')
    }
    const url = URL.createObjectURL(data)
    const a = document.createElement('a')
    a.href = url
    a.download = `resume-${resumeId.value}.pdf`
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    let msg = 'دانلود PDF ممکن نشد.'
    const blob = e?.response?.data
    if (blob instanceof Blob) {
      try {
        const parsed = JSON.parse(await blob.text())
        if (parsed?.message) msg = parsed.message
      } catch {
        msg = apiErrorMessage(e, msg)
      }
    } else {
      msg = apiErrorMessage(e, e?.message || msg)
    }
    toast.error(msg)
  } finally {
    exporting.value = false
  }
}

function finishAndPreview() {
  showPreview.value = true
}

function scrollStepTop() {
  nextTick(() => {
    const pane = document.querySelector('.resume-pane')
    if (pane && pane.scrollHeight > pane.clientHeight + 8) {
      pane.scrollTo({ top: 0, behavior: 'smooth' })
    }
    window.scrollTo({ top: 0, behavior: 'smooth' })
  })
}

function goNext() {
  if (currentStep.value < steps.length - 1) currentStep.value += 1
}

function goPrev() {
  if (currentStep.value > 0) currentStep.value -= 1
}

watch(currentStep, () => {
  scrollStepTop()
})

function ensureAi() {
  if (!features.isEnabled('ai-resume')) {
    toast.error('قابلیت AI رزومه فعال نیست.')
    return false
  }
  return true
}

async function onAiSummary({ mode, resolve, reject }) {
  if (!ensureAi()) return reject(new Error('disabled'))
  try {
    await save()
    const { data } = await api.post(`/resumes/${resumeId.value}/ai/summary`, {
      title: resumeData.value.target_job,
      mode: mode || 'summary',
      experiences: resumeData.value.experience,
      skills: resumeData.value.skills,
    })
    const payload = unwrapItem(data)
    resolve(payload.suggestion || '')
  } catch (e) {
    toast.error(apiErrorMessage(e, 'پیشنهاد AI در دسترس نیست.'))
    reject(e)
  }
}

async function draftWithAi() {
  if (!ensureAi()) return
  if (!String(resumeData.value?.target_job || '').trim()) {
    toast.error('ابتدا عنوان / شغل هدف را وارد کنید.')
    currentStep.value = 0
    return
  }
  drafting.value = true
  try {
    await save()
    const { data } = await api.post(`/resumes/${resumeId.value}/ai/draft`, {
      title: resumeData.value.target_job,
    })
    const payload = unwrapItem(data)
    applyDraft(payload)
    toast.success('پیش‌نویس رزومه این شغل آماده شد. موارد را ویرایش کنید.')
  } catch (e) {
    toast.error(apiErrorMessage(e, 'ساخت پیش‌نویس ممکن نشد.'))
  } finally {
    drafting.value = false
  }
}

function applyDraft(payload) {
  if (!resumeData.value || !payload) return
  if (payload.summary) resumeData.value.summary = payload.summary
  if (Array.isArray(payload.skills) && payload.skills.length) {
    const existing = new Set((resumeData.value.skills || []).map((s) => s.name))
    payload.skills.forEach((s) => {
      const name = typeof s === 'string' ? s : s.name
      if (!name || existing.has(name)) return
      resumeData.value.skills.push({
        name,
        level: s.level || 'متوسط',
      })
      existing.add(name)
    })
  }
  if (Array.isArray(payload.experience) && payload.experience.length && !(resumeData.value.experience || []).length) {
    resumeData.value.experience = payload.experience.map((e, i) => ({
      _key: `exp-ai-${Date.now()}-${i}`,
      title: e.title || resumeData.value.target_job,
      company: e.company || 'سازمان مربوطه',
      start_date: e.start_date || '',
      end_date: e.end_date || '',
      is_current: !!e.is_current,
      description: e.description || '',
    }))
  }
  if (Array.isArray(payload.languages) && payload.languages.length && !(resumeData.value.languages || []).length) {
    resumeData.value.languages = payload.languages
  }
}

async function onAiEnhance({ exp, resolve, reject }) {
  if (!ensureAi()) return reject(new Error('disabled'))
  try {
    await save()
    const { data } = await api.post(`/resumes/${resumeId.value}/ai/enhance-experience`, {
      title: exp.title,
      description: exp.description,
    })
    const payload = unwrapItem(data)
    resolve(payload.enhanced || '')
  } catch (e) {
    toast.error(apiErrorMessage(e, 'بهبود AI ممکن نشد.'))
    reject(e)
  }
}

async function onAiSkills({ resolve, reject }) {
  if (!ensureAi()) return reject(new Error('disabled'))
  try {
    await save()
    const { data } = await api.post(`/resumes/${resumeId.value}/ai/suggest-skills`, {
      title: resumeData.value.target_job,
      experiences: resumeData.value.experience,
    })
    const payload = unwrapItem(data)
    resolve(payload.skills || [])
  } catch (e) {
    toast.error(apiErrorMessage(e, 'پیشنهاد مهارت ممکن نشد.'))
    reject(e)
  }
}

onMounted(async () => {
  try {
    await features.fetch()
    if (!auth.user) await auth.fetchMe()
    const { data } = await api.get(`/resumes/${resumeId.value}`)
    const item = unwrapItem(data)
    title.value = item.title || 'رزومه'
    templateId.value = item.template_id || 1
    activeTemplate.value =
      templates.find((t) => t.templateId === templateId.value && t.id !== 'creative')?.id ||
      'modern'
    let d = item.data || clonePlain(emptyData)
    if (!Array.isArray(d.experience)) d.experience = []
    if (!Array.isArray(d.education)) d.education = []
    if (!Array.isArray(d.skills)) d.skills = []
    if (!Array.isArray(d.languages)) d.languages = []
    if (!d.personal) d.personal = { ...emptyData.personal }
    else d.personal = { ...emptyData.personal, ...d.personal }
    // normalize legacy national formats to 10 digits
    d.personal.national_code = String(d.personal.national_code || '')
      .replace(/\D/g, '')
      .slice(0, 10)
    d.personal.postal_code = String(d.personal.postal_code || '')
      .replace(/\D/g, '')
      .slice(0, 10)
    ;(d.education || []).forEach((e) => {
      if (!e.start_date && e.start_year) e.start_date = `${e.start_year}-01`
      if (!e.end_date && e.end_year) e.end_date = `${e.end_year}-01`
    })
    // legacy birth date YYYY-MM-DD → keep empty if not Jalali slash form
    if (d.personal.birth_date && !/^\d{2}\/\d{2}\/\d{4}$/.test(d.personal.birth_date)) {
      const m = String(d.personal.birth_date).match(/^(\d{4})-(\d{2})-(\d{2})$/)
      if (m && Number(m[1]) > 1300 && Number(m[1]) < 1500) {
        d.personal.birth_date = `${m[3]}/${m[2]}/${m[1]}`
      } else {
        d.personal.birth_date = ''
      }
    }
    const draft = readDraft()
    if (draft?.data) {
      const serverPhoto = d.personal.photo
      d = {
        ...d,
        ...draft.data,
        personal: { ...d.personal, ...(draft.data.personal || {}) },
      }
      if (!d.personal.photo && serverPhoto) d.personal.photo = serverPhoto
      if (draft.templateId) templateId.value = Number(draft.templateId) || templateId.value
      if (draft.title) title.value = draft.title
      if (typeof draft.currentStep === 'number') currentStep.value = draft.currentStep
    }
    resumeData.value = d
    draftReady.value = true
  } catch (e) {
    toast.error(apiErrorMessage(e, 'بارگذاری رزومه ناموفق بود.'))
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}
.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
.resume-pane {
  overflow-y: auto;
  direction: ltr;
}
.resume-pane > * {
  direction: rtl;
}
.resume-splitter {
  width: 10px;
  cursor: col-resize;
  background: linear-gradient(to left, #e2e8f0, #cbd5e1, #e2e8f0);
}
.resume-splitter:hover {
  background: #fb923c;
}
.preview-stage {
  overflow: hidden;
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 12px 40px rgba(15, 23, 42, 0.12);
}
.preview-stage :deep(.resume-a4) {
  zoom: var(--preview-zoom, 0.48);
}
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
