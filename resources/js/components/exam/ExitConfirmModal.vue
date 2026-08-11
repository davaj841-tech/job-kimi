<template>
  <ExamModal
    :model-value="modelValue"
    title="خروج از آزمون"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <p class="text-sm leading-7 text-ink-soft dark:text-slate-300">
      با خروج، تایمر متوقف نمی‌شود. پاسخ‌های ذخیره‌شده حفظ می‌شوند و می‌توانید
      دوباره وارد شوید.
    </p>
    <template #footer>
      <div class="flex gap-2">
        <button
          type="button"
          class="flex-1 rounded-xl border border-surface-line py-2.5 text-sm dark:border-slate-600"
          @click="emit('update:modelValue', false)"
        >
          ادامه آزمون
        </button>
        <button
          type="button"
          class="flex-1 rounded-xl bg-brand py-2.5 text-sm font-bold text-white"
          @click="emit('confirm')"
        >
          خروج
        </button>
      </div>
    </template>
  </ExamModal>
</template>

<script setup lang="ts">
import ExamModal from './ExamModal.vue'

defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{
  'update:modelValue': [boolean]
  confirm: []
}>()
</script>
