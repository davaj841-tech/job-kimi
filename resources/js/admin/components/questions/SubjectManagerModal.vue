<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
  >
    <div
      class="max-h-[88vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl"
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

      <form
        class="mb-5 grid grid-cols-[88px_1fr_1fr_auto] items-end gap-2"
        @submit.prevent="submit"
      >
        <div>
          <label class="label">آیکون</label>
          <select v-model="form.icon" class="field text-center text-lg">
            <option
              v-for="ic in iconOptions"
              :key="ic"
              :value="ic"
            >
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
        <div
          v-for="s in subjectsStore.subjects"
          :key="s.id"
          class="flex items-center justify-between rounded-xl border border-slate-100 px-3 py-2"
        >
          <div class="flex items-center gap-2">
            <span class="text-lg">{{ s.icon || '📘' }}</span>
            <div>
              <p class="text-sm font-bold text-slate-800">{{ s.name }}</p>
              <p class="text-[11px] text-slate-400" dir="ltr">{{ s.slug }}</p>
            </div>
            <span
              v-if="!s.is_active"
              class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-500"
            >
              غیرفعال
            </span>
          </div>
          <div class="flex gap-1">
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
import { onMounted, reactive, ref } from 'vue'
import { useToast } from '../../../composables/useToast'
import { useExamSubjectsStore } from '../../stores/examSubjects'

defineProps({ open: Boolean })
defineEmits(['close'])

const subjectsStore = useExamSubjectsStore()
const toast = useToast()

const iconOptions = [
  '📘', '📗', '📕', '📙', '📚', '📖',
  '🧮', '🔢', '✏️', '📝', '🖊️',
  '🧠', '💡', '🔬', '⚗️', '🧪',
  '🌍', '📜', '🕌', '✝️', '🔤',
  '🗣️', '📐', '📊', '💻', '⚙️',
  '🏛️', '⚖️', '🏥', '💼', '🎯',
  '⭐', '✅', '📌', '🗂️', '📁',
]

const saving = ref(false)
const error = ref('')
const editingId = ref(null)
const form = reactive({ name: '', slug: '', icon: '📘' })

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
      toast.success('درس به‌روزرسانی شد.')
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
        'حذف ناموفق بود — احتمالاً در سوالات استفاده شده است.'
    )
  }
}

onMounted(() => {
  subjectsStore.fetchSubjects().catch(() => {})
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
