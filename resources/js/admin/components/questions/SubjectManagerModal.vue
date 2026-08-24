<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
  >
    <div
      class="max-h-[88vh] w-full max-w-xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl"
    >
      <div class="mb-4 flex items-center justify-between">
        <h3 class="text-lg font-bold text-slate-800">📚 مدیریت دروس</h3>
        <button
          type="button"
          class="text-slate-400 hover:text-slate-600"
          @click="$emit('close')"
        >
          ✕
        </button>
      </div>

      <p class="mb-4 text-xs leading-5 text-slate-500">
        نام‌های درسی که در ورود اکسل پیدا نشوند با برچسب
        <span class="font-bold text-amber-700">نامرتبط</span>
        اینجا ثبت می‌شوند. درس مقصد را انتخاب کنید و دکمه
        <span class="font-bold">آپدیت</span>
        را بزنید تا نام درس در سوالات مربوطه هم اصلاح شود.
      </p>

      <div
        v-if="unmatchedSubjects.length"
        class="mb-5 space-y-3 rounded-2xl border border-amber-200 bg-amber-50/70 p-3"
      >
        <p class="text-sm font-bold text-amber-900">
          دروس نامرتبط واردشده از اکسل ({{ unmatchedSubjects.length }})
        </p>
        <div
          v-for="s in unmatchedSubjects"
          :key="'u-' + s.id"
          class="rounded-xl border border-amber-200 bg-white p-3"
        >
          <div class="mb-2 flex items-center gap-2">
            <span class="text-lg">{{ s.icon || '❓' }}</span>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-bold text-slate-800">
                {{ s.name }}
              </p>
              <p class="text-[11px] text-slate-400" dir="ltr">{{ s.slug }}</p>
            </div>
            <span
              class="shrink-0 rounded-md bg-amber-200 px-1.5 py-0.5 text-[10px] font-bold text-amber-900"
            >
              نامرتبط
            </span>
          </div>
          <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
            <div class="min-w-0 flex-1">
              <label class="label">تخصیص به درس مورد نظر</label>
              <select v-model="assignMap[s.id]" class="field">
                <option value="">انتخاب درس مقصد…</option>
                <option v-for="t in matchedSubjects" :key="t.id" :value="t.id">
                  {{ t.icon || '📘' }} {{ t.name }}
                </option>
              </select>
            </div>
            <button
              type="button"
              class="h-10 shrink-0 rounded-xl bg-orange-500 px-4 text-sm font-bold text-white disabled:opacity-50"
              :disabled="!assignMap[s.id] || updatingId === s.id"
              @click="assignAndUpdate(s)"
            >
              {{ updatingId === s.id ? '...' : 'آپدیت' }}
            </button>
          </div>
        </div>
      </div>

      <form
        class="mb-5 grid grid-cols-[88px_1fr_1fr_auto] items-end gap-2"
        @submit.prevent="submit"
      >
        <div>
          <label class="label">آیکون</label>
          <select v-model="form.icon" class="field text-center text-lg">
            <option v-for="ic in iconOptions" :key="ic" :value="ic">
              {{ ic }}
            </option>
          </select>
        </div>
        <div>
          <label class="label">نام درس *</label>
          <input
            v-model="form.name"
            class="field"
            required
            placeholder="مثلاً ریاضی"
          />
        </div>
        <div>
          <label class="label">اسلاگ (اختیاری)</label>
          <input
            v-model="form.slug"
            class="field text-left"
            dir="ltr"
            placeholder="math"
          />
        </div>
        <button
          type="submit"
          class="h-10 rounded-xl bg-orange-500 px-4 text-sm font-bold text-white disabled:opacity-50"
          :disabled="saving"
        >
          {{ editingId ? 'ذخیره' : 'افزودن' }}
        </button>
      </form>
      <div v-if="editingId" class="-mt-3 mb-4">
        <button
          type="button"
          class="text-xs font-bold text-slate-500 underline"
          @click="resetForm"
        >
          انصراف از ویرایش
        </button>
      </div>

      <p v-if="error" class="mb-3 text-sm text-red-500">{{ error }}</p>

      <div
        v-if="subjectsStore.loading"
        class="py-8 text-center text-sm text-slate-500"
      >
        در حال بارگذاری...
      </div>
      <div v-else class="space-y-2">
        <p class="text-xs font-bold text-slate-500">همه دروس</p>
        <div
          v-for="s in subjectsStore.subjects"
          :key="s.id"
          class="flex items-center justify-between rounded-xl border px-3 py-2"
          :class="
            s.is_unmatched
              ? 'border-amber-300 bg-amber-50/60'
              : 'border-slate-100'
          "
        >
          <div class="flex min-w-0 items-center gap-2">
            <span class="text-lg">{{ s.icon || '📘' }}</span>
            <div class="min-w-0">
              <p class="truncate text-sm font-bold text-slate-800">
                {{ s.name }}
              </p>
              <p class="text-[11px] text-slate-400" dir="ltr">{{ s.slug }}</p>
            </div>
            <span
              v-if="s.is_unmatched"
              class="shrink-0 rounded-md bg-amber-200 px-1.5 py-0.5 text-[10px] font-bold text-amber-900"
            >
              نامرتبط
            </span>
            <span
              v-if="!s.is_active"
              class="shrink-0 rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-500"
            >
              غیرفعال
            </span>
          </div>
          <div class="flex shrink-0 gap-1">
            <button type="button" class="act" @click="toggleActive(s)">
              {{ s.is_active ? 'غیرفعال' : 'فعال' }}
            </button>
            <button type="button" class="act" @click="startEdit(s)">
              ویرایش
            </button>
            <button type="button" class="act text-red-600" @click="remove(s)">
              حذف
            </button>
          </div>
        </div>
        <p
          v-if="!subjectsStore.subjects.length"
          class="py-6 text-center text-sm text-slate-400"
        >
          درسی ثبت نشده است
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useToast } from '../../../composables/useToast'
import { useExamSubjectsStore } from '../../stores/examSubjects'

const props = defineProps({ open: Boolean })
defineEmits(['close'])

const subjectsStore = useExamSubjectsStore()
const toast = useToast()

const iconOptions = [
  '📘',
  '📗',
  '📕',
  '📙',
  '📚',
  '📖',
  '🧮',
  '🔢',
  '✏️',
  '📝',
  '🖊️',
  '🧠',
  '💡',
  '🔬',
  '⚗️',
  '🧪',
  '🌍',
  '📜',
  '🕌',
  '✝️',
  '🔤',
  '🗣️',
  '📐',
  '📊',
  '💻',
  '⚙️',
  '🏛️',
  '⚖️',
  '🏥',
  '💼',
  '🎯',
  '⭐',
  '✅',
  '📌',
  '🗂️',
  '📁',
  '❓',
]

const saving = ref(false)
const updatingId = ref(null)
const error = ref('')
const editingId = ref(null)
const form = reactive({ name: '', slug: '', icon: '📘' })
const assignMap = reactive({})

const unmatchedSubjects = computed(() =>
  (subjectsStore.subjects || []).filter((s) => s.is_unmatched)
)
const matchedSubjects = computed(() =>
  (subjectsStore.subjects || []).filter((s) => !s.is_unmatched)
)

watch(
  () => props.open,
  (v) => {
    if (v) subjectsStore.fetchSubjects(true).catch(() => {})
  }
)

function resetForm() {
  editingId.value = null
  form.name = ''
  form.slug = ''
  form.icon = '📘'
  error.value = ''
}

function startEdit(s) {
  editingId.value = s.id
  form.name = s.name
  form.slug = s.slug
  form.icon = s.icon || '📘'
  if (form.icon && !iconOptions.includes(form.icon)) {
    iconOptions.unshift(form.icon)
  }
  error.value = ''
}

async function assignAndUpdate(s) {
  const targetId = Number(assignMap[s.id] || 0)
  if (!targetId) {
    toast.error('ابتدا درس مقصد را انتخاب کنید.')
    return
  }
  const target = matchedSubjects.value.find((t) => t.id === targetId)
  updatingId.value = s.id
  try {
    await subjectsStore.updateSubject(s.id, { merge_into_id: targetId })
    delete assignMap[s.id]
    toast.success(
      `درس «${s.name}» به «${target?.name || 'درس مقصد'}» تخصیص شد و سوالات آپدیت شدند.`
    )
  } catch (e) {
    toast.error(e.response?.data?.message || 'آپدیت ناموفق بود.')
  } finally {
    updatingId.value = null
  }
}

async function submit() {
  if (!form.name.trim()) {
    error.value = 'نام درس الزامی است.'
    return
  }
  saving.value = true
  error.value = ''
  try {
    const payload = { name: form.name.trim(), icon: form.icon || '📘' }
    if (form.slug.trim()) payload.slug = form.slug.trim()
    if (editingId.value) {
      await subjectsStore.updateSubject(editingId.value, payload)
      toast.success('درس به‌روزرسانی شد و سوالات مرتبط اصلاح شد.')
    } else {
      await subjectsStore.createSubject(payload)
      toast.success('درس ایجاد شد.')
    }
    resetForm()
  } catch (e) {
    error.value = e.response?.data?.message || 'ذخیره ناموفق بود.'
  } finally {
    saving.value = false
  }
}

async function toggleActive(s) {
  try {
    await subjectsStore.updateSubject(s.id, { is_active: !s.is_active })
  } catch (e) {
    toast.error(e.response?.data?.message || 'به‌روزرسانی ناموفق بود.')
  }
}

async function remove(s) {
  if (!window.confirm(`درس «${s.name}» حذف شود؟`)) return
  try {
    await subjectsStore.deleteSubject(s.id)
    toast.success('درس حذف شد.')
  } catch (e) {
    toast.error(
      e.response?.data?.message ||
        'حذف ناموفق بود — ابتدا با دکمه آپدیت به درس دیگری تخصیص دهید.'
    )
  }
}

onMounted(() => {
  subjectsStore.fetchSubjects(true).catch(() => {})
})
</script>

<style scoped>
.label {
  @apply mb-1 block text-xs font-medium text-slate-600;
}
.field {
  @apply h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-orange-400;
}
.act {
  @apply rounded-lg bg-slate-100 px-2 py-1 text-[11px] font-bold text-slate-700;
}
</style>
