<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between gap-2">
      <h2 class="text-lg font-bold text-desk-text dark:text-white">تحصیلات</h2>
      <button
        type="button"
        class="inline-flex items-center gap-2 rounded-xl bg-slate-100 px-3 py-2 text-sm font-medium dark:bg-slate-800"
        @click="addEducation"
      >
        <PlusIcon class="h-4 w-4" />
        افزودن
      </button>
    </div>

    <div class="space-y-3">
      <div
        v-for="(edu, index) in local.education"
        :key="edu._key || index"
        class="rounded-xl border border-surface-line bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50"
      >
        <div class="mb-3 flex justify-end">
          <button
            type="button"
            class="text-desk-muted hover:text-red-500"
            :disabled="(local.education || []).length <= 1"
            @click="removeEducation(index)"
          >
            <TrashIcon class="h-5 w-5" />
          </button>
        </div>
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
          <label class="block">
            <span class="mb-1.5 block text-xs font-medium text-desk-muted">مقطع *</span>
            <select
              v-model="edu.degree"
              class="input-field"
            >
              <option
                v-for="d in degrees"
                :key="d"
                :value="d"
              >
                {{ d }}
              </option>
            </select>
          </label>
          <FormInput
            v-model="edu.field"
            label="رشته"
            placeholder="مهندسی کامپیوتر"
            required
          />
          <FormInput
            v-model="edu.university"
            label="دانشگاه"
            placeholder="دانشگاه تهران"
            required
          />
          <FormInput
            v-model.number="edu.start_year"
            label="سال شروع (شمسی)"
            type="number"
            placeholder="1395"
          />
          <FormInput
            v-model.number="edu.end_year"
            label="سال پایان"
            type="number"
            placeholder="1399"
          />
          <FormInput
            v-model.number="edu.gpa"
            label="معدل (اختیاری)"
            type="number"
            placeholder="17.5"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import FormInput from '../FormInput.vue'

const degrees = ['دیپلم', 'کاردانی', 'کارشناسی', 'ارشد', 'دکترا']

const props = defineProps({
  modelValue: { type: Object, required: true },
})
const emit = defineEmits(['update:modelValue'])

const local = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

function addEducation() {
  if (!Array.isArray(local.value.education)) local.value.education = []
  local.value.education.push({
    _key: `edu-${Date.now()}`,
    degree: 'کارشناسی',
    field: '',
    university: '',
    start_year: null,
    end_year: null,
    gpa: null,
  })
}

function removeEducation(index) {
  if ((local.value.education || []).length <= 1) return
  local.value.education.splice(index, 1)
}
</script>
