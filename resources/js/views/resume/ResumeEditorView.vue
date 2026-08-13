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
          <div class="hidden items-center gap-1 rounded-xl bg-slate-100 p-1 md:flex dark:bg-slate-800">
            <button
              v-for="t in templates"
              :key="t.id"
              type="button"
              class="rounded-lg px-2.5 py-1.5 text-xs transition"
              :class="
                activeTemplate === t.id
                  ? 'bg-white font-medium shadow-sm dark:bg-slate-700'
                  : 'text-desk-muted'
              "
              @click="setTemplate(t)"
            >
              {{ t.name }}
            </button>
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
      class="mx-auto flex max-w-7xl flex-col lg:h-[calc(100dvh-3.5rem)] lg:flex-row"
    >
      <div class="flex-1 space-y-4 overflow-y-auto p-3 sm:p-5">
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

        <div
          class="rounded-2xl border border-surface-line bg-white p-4 sm:p-6 dark:border-slate-800 dark:bg-slate-900"
        >
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
            @click="currentStep--"
          >
            قبلی
          </button>
          <button
            v-if="currentStep < steps.length - 1"
            type="button"
            class="rounded-xl bg-brand px-5 py-2.5 text-sm text-white"
            @click="currentStep++"
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

      <aside class="hidden w-[420px] shrink-0 overflow-y-auto bg-slate-200 p-5 dark:bg-slate-950 lg:block">
        <p class="mb-3 text-center text-xs text-desk-muted">پیش‌نمایش زنده</p>
        <div class="origin-top scale-[0.82] overflow-hidden rounded-xl shadow-2xl">
          <ResumePreview
            :data="resumeData"
            :template="activeTemplate"
            :template-id="templateId"
          />
        </div>
      </aside>
    </div>

    <PreviewModal
      v-model="showPreview"
      :data="resumeData || emptyData"
      :template="activeTemplate"
    />
  </div>
</template>

<script setup>
import { computed, markRaw, onMounted, ref } from 'vue'
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
    birth_date: '1370-01-01',
    national_code: '',
    mobile: '',
    email: '',
    address: '',
    photo: null,
    birth_province: '',
    birth_city: '',
    marital_status: '',
    field_of_study: '',
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
const currentStep = ref(0)
const activeTemplate = ref('modern')
const templateId = ref(1)
const showPreview = ref(false)
const title = ref('')
const resumeData = ref(null)

const resumeId = computed(() => route.params.id)

function setTemplate(t) {
  activeTemplate.value = t.id
  templateId.value = t.templateId
}

function fillFromProfile() {
  const u = auth.user
  if (!u || !resumeData.value) return
  resumeData.value.personal.full_name = u.name || resumeData.value.personal.full_name
  resumeData.value.personal.email = u.email || resumeData.value.personal.email
  resumeData.value.personal.mobile = u.mobile || resumeData.value.personal.mobile
  resumeData.value.personal.national_code =
    u.national_code || resumeData.value.personal.national_code
  resumeData.value.personal.address = u.province || resumeData.value.personal.address
  resumeData.value.personal.photo = u.avatar || resumeData.value.personal.photo
  toast.success('اطلاعات پروفایل اعمال شد.')
}

function sanitizeData(data) {
  const d = structuredClone(data)
  ;(d.experience || []).forEach((e) => {
    delete e._key
    if (e.is_current) e.end_date = null
  })
  ;(d.education || []).forEach((e) => {
    delete e._key
    if (e.start_year === '' || e.start_year == null) e.start_year = null
    else e.start_year = Number(e.start_year)
    if (e.end_year === '' || e.end_year == null) e.end_year = null
    else e.end_year = Number(e.end_year)
    if (e.gpa === '' || e.gpa == null) e.gpa = null
    else e.gpa = Number(e.gpa)
  })
  if (!d.personal.photo) d.personal.photo = null
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
    const url = URL.createObjectURL(data)
    const a = document.createElement('a')
    a.href = url
    a.download = `resume-${resumeId.value}.pdf`
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    toast.error(apiErrorMessage(e, 'دانلود PDF ممکن نشد.'))
  } finally {
    exporting.value = false
  }
}

function finishAndPreview() {
  showPreview.value = true
}

function ensureAi() {
  if (!features.isEnabled('ai-resume')) {
    toast.error('قابلیت AI رزومه فعال نیست.')
    return false
  }
  return true
}

async function onAiSummary({ resolve, reject }) {
  if (!ensureAi()) return reject(new Error('disabled'))
  try {
    await save()
    const { data } = await api.post(`/resumes/${resumeId.value}/ai/summary`, {
      title: resumeData.value.target_job,
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
    const d = item.data || structuredClone(emptyData)
    if (!Array.isArray(d.experience)) d.experience = []
    if (!Array.isArray(d.education)) d.education = []
    if (!Array.isArray(d.skills)) d.skills = []
    if (!Array.isArray(d.languages)) d.languages = []
    if (!d.personal) d.personal = { ...emptyData.personal }
    else d.personal = { ...emptyData.personal, ...d.personal }
    resumeData.value = d
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
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
