<template>
  <ExamModal
    :model-value="modelValue"
    title="ثبت نهایی آزمون"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <p class="text-sm leading-7 text-ink-soft dark:text-slate-300">
      {{ unanswered }} سوال بدون پاسخ دارید.
      <span v-if="flagged"> همچنین {{ flagged }} سوال علامت‌گذاری شده.</span>
      آیا از ارسال مطمئن هستید؟
    </p>
    <template #footer>
      <div class="flex gap-2">
        <button
          type="button"
          class="flex-1 rounded-xl border border-surface-line py-2.5 text-sm dark:border-slate-600"
          @click="emit('update:modelValue', false)"
        >
          بازگشت
        </button>
        <button
          type="button"
          class="flex-1 rounded-xl bg-brand py-2.5 text-sm font-bold text-white"
          :disabled="loading"
          @click="emit('confirm')"
        >
          {{ loading ? '...' : 'ثبت و مشاهده نتیجه' }}
        </button>
      </div>
    </template>
  </ExamModal>
</template>

<script setup lang="ts">
import ExamModal from './ExamModal.vue'

defineProps<{
  modelValue: boolean
  unanswered: number
  flagged: number
  loading?: boolean
}>()
const emit = defineEmits<{
  'update:modelValue': [boolean]
  confirm: []
}>()
</script>
