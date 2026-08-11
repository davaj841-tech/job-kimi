<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
  >
    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
      <div class="mb-4 flex items-center justify-between">
        <h3 class="text-lg font-bold">تولید سوال با AI</h3>
        <button type="button" @click="$emit('close')">✕</button>
      </div>

      <form class="space-y-3" @submit.prevent="generate">
        <div>
          <label class="mb-1 block text-xs text-slate-600">آزمون هدف</label>
          <select v-model="form.exam_id" required class="field">
            <option disabled value="">انتخاب کنید</option>
            <option v-for="e in exams" :key="e.id" :value="e.id">
              {{ e.title }}
            </option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-xs text-slate-600">درس</label>
          <select v-model="form.subject" class="field">
            <option v-for="s in subjectOptions" :key="s.slug" :value="s.slug">
              {{ s.icon ? s.icon + ' ' : '' }}{{ s.name }}
            </option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-xs text-slate-600">سطح</label>
          <select v-model="form.difficulty" class="field">
            <option value="easy">آسان</option>
            <option value="medium">متوسط</option>
            <option value="hard">سخت</option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-xs text-slate-600"
            >تعداد (۱ تا ۲۰)</label
          >
          <input
            v-model.number="form.count"
            type="number"
            min="1"
            max="20"
            class="field"
          />
        </div>

        <p v-if="message" class="text-sm text-emerald-600">{{ message }}</p>
        <p v-if="error" class="text-sm text-red-500">{{ error }}</p>

        <div class="flex justify-end gap-2 pt-2">
          <button
            type="button"
            class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-bold"
            @click="$emit('close')"
          >
            بستن
          </button>
          <button
            type="submit"
            class="rounded-xl bg-orange-500 px-4 py-2 text-sm font-bold text-white"
            :disabled="loading"
          >
            {{ loading ? 'در حال تولید...' : 'تولید' }}
          </button>
        </div>
      </form>

      <p class="mt-4 text-xs leading-6 text-slate-400">
        سوالات تولیدشده ابتدا در صف AI ذخیره می‌شوند و پس از تایید ادمین به بانک
        سوالات اضافه می‌گردند.
      </p>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useExamSubjectsStore } from '../../stores/examSubjects'

const props = defineProps({
  open: Boolean,
  exams: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'generate'])

const subjectsStore = useExamSubjectsStore()
const loading = ref(false)
const error = ref('')
const message = ref('')
const form = reactive({
  exam_id: '',
  subject: 'general',
  difficulty: 'medium',
  count: 5,
})

const subjectOptions = computed(() => {
  const list = (subjectsStore.subjects || []).filter(
    (s) => s.is_active !== false
  )
  if (list.length) return list
  return [
    { slug: 'general', name: 'عمومی', icon: '📝' },
    { slug: 'math', name: 'ریاضی', icon: '➗' },
    { slug: 'literature', name: 'ادبیات', icon: '📖' },
    { slug: 'islamic', name: 'معارف', icon: '🕌' },
    { slug: 'chemistry', name: 'شیمی', icon: '🧪' },
    { slug: 'physics', name: 'فیزیک', icon: '⚛️' },
    { slug: 'iq', name: 'هوش', icon: '🧠' },
    { slug: 'english', name: 'انگلیسی', icon: '🔤' },
  ]
})

watch(
  () => props.open,
  async (v) => {
    if (v) {
      error.value = ''
      message.value = ''
      loading.value = false
      if (!subjectsStore.subjects.length) {
        try {
          await subjectsStore.fetchSubjects()
        } catch (_) {
          // fallback options
        }
      }
      if (!form.subject && subjectOptions.value[0]) {
        form.subject = subjectOptions.value[0].slug
      }
    }
  }
)

onMounted(async () => {
  try {
    await subjectsStore.fetchSubjects()
  } catch (_) {
    // ignore
  }
})

async function generate() {
  loading.value = true
  error.value = ''
  message.value = ''
  try {
    emit('generate', {
      exam_id: Number(form.exam_id),
      subject: form.subject,
      difficulty: form.difficulty,
      count: Number(form.count),
    })
  } finally {
    loading.value = false
  }
}

defineExpose({
  setMessage(msg) {
    message.value = msg
  },
  setError(msg) {
    error.value = msg
  },
  setLoading(v) {
    loading.value = v
  },
})
</script>

<style scoped>
.field {
  @apply w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-orange-400;
}
</style>
