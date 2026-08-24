<template>
  <ExamModal
    :model-value="modelValue"
    title="پاسخنامه"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div
      class="grid max-h-96 grid-cols-5 gap-2 overflow-y-auto p-1 sm:grid-cols-8"
    >
      <button
        v-for="(q, idx) in questions"
        :key="q.id"
        type="button"
        class="relative flex aspect-square flex-col items-center justify-center rounded-lg text-sm font-medium transition"
        :class="sheetClass(idx)"
        @click="go(idx)"
      >
        <span>{{ toFaDigits(idx + 1) }}</span>
        <span
          v-if="session.isFlagged(q.id)"
          class="absolute left-0.5 top-0.5 h-2 w-2 rounded-full bg-amber-500"
        />
      </button>
    </div>

    <template #footer>
      <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-ink-muted dark:text-slate-400">
          {{ toFaDigits(session.answeredCount) }} پاسخ ·
          {{ toFaDigits(session.flaggedCount) }} علامت
        </p>
        <button
          type="button"
          class="rounded-xl bg-brand px-4 py-2 text-sm font-bold text-white"
          @click="emit('submit')"
        >
          اتمام آزمون
        </button>
      </div>
    </template>
  </ExamModal>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import ExamModal from './ExamModal.vue'
import { useExamSessionStore } from '../../stores/examSession'
import { useExamStore } from '../../stores/exam'
import { toFaDigits } from '../../utils/format'

defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{
  'update:modelValue': [boolean]
  submit: []
}>()

const session = useExamSessionStore()
const examStore = useExamStore()
const questions = computed(() => session.questions)

function sheetClass(idx: number) {
  const q = questions.value[idx]
  const answered = Boolean(examStore.answers[q?.id])
  const current = session.currentIndex === idx
  if (current) return 'ring-2 ring-brand bg-brand-soft text-brand'
  if (answered) return 'bg-brand text-white'
  if (session.isFlagged(q?.id))
    return 'bg-amber-100 text-amber-800 dark:bg-amber-900/40'
  return 'bg-slate-100 text-ink dark:bg-slate-800 dark:text-slate-200'
}

function go(idx: number) {
  session.navigateTo(idx)
  emit('update:modelValue', false)
}
</script>
