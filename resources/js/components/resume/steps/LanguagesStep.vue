<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between gap-2">
      <h2 class="text-lg font-bold text-desk-text dark:text-white">زبان‌ها</h2>
      <button
        type="button"
        class="inline-flex items-center gap-2 rounded-xl bg-slate-100 px-3 py-2 text-sm font-medium dark:bg-slate-800"
        @click="addLanguage"
      >
        <PlusIcon class="h-4 w-4" />
        افزودن
      </button>
    </div>

    <div
      v-if="!(local.languages || []).length"
      class="rounded-xl border-2 border-dashed border-surface-line py-10 text-center text-sm text-desk-muted dark:border-slate-700"
    >
      اختیاری — زبان‌های خود را اضافه کنید
    </div>

    <div class="space-y-3">
      <div
        v-for="(lang, index) in local.languages"
        :key="index"
        class="flex flex-col gap-2 rounded-xl border border-surface-line bg-slate-50 p-3 sm:flex-row dark:border-slate-700 dark:bg-slate-800/50"
      >
        <FormInput
          v-model="lang.name"
          label="زبان"
          placeholder="انگلیسی"
          class="flex-1"
        />
        <label class="block sm:w-40">
          <span class="mb-1.5 block text-xs font-medium text-desk-muted">سطح</span>
          <select
            v-model="lang.level"
            class="input-field"
          >
            <option
              v-for="lv in levels"
              :key="lv"
              :value="lv"
            >
              {{ lv }}
            </option>
          </select>
        </label>
        <button
          type="button"
          class="self-end p-2 text-desk-muted hover:text-red-500"
          @click="local.languages.splice(index, 1)"
        >
          <TrashIcon class="h-5 w-5" />
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import FormInput from '../FormInput.vue'

const levels = ['مبتدی', 'متوسط', 'حرفه‌ای', 'A1', 'A2', 'B1', 'B2', 'C1', 'C2']

const props = defineProps({
  modelValue: { type: Object, required: true },
})
const emit = defineEmits(['update:modelValue'])

const local = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

function addLanguage() {
  if (!Array.isArray(local.value.languages)) local.value.languages = []
  local.value.languages.unshift({ name: '', level: 'متوسط' })
}
</script>
