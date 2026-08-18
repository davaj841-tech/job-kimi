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
          <SearchSelect
            v-model="edu.field"
            label="رشته"
            placeholder="جستجوی رشته تحصیلی…"
            :options="ACADEMIC_FIELDS"
            required
          />
          <FormInput
            v-model="edu.university"
            label="دانشگاه"
            placeholder="دانشگاه تهران"
            required
          />
          <JalaliMonthYear
            v-model="edu.start_date"
            label="از تاریخ"
          />
          <JalaliMonthYear
            v-model="edu.end_date"
            label="تا تاریخ"
          />
          <label class="block">
            <span class="mb-1.5 block text-xs font-medium text-desk-muted">معدل (اختیاری)</span>
            <input
              :value="edu.gpa ?? ''"
              class="input-field text-left"
              dir="ltr"
              inputmode="decimal"
              maxlength="4"
              placeholder="۱۸.۵"
              @input="onGpa(edu, $event)"
              @blur="normalizeGpa(edu)"
            />
            <span class="mt-1 block text-[11px] text-desk-muted">دو رقم صحیح و یک رقم اعشار؛ مثلاً ۱۸۵ می‌شود ۱۸.۵</span>
          </label>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import FormInput from '../FormInput.vue'
import JalaliMonthYear from '../JalaliMonthYear.vue'
import SearchSelect from '../SearchSelect.vue'
import { ACADEMIC_FIELDS } from '../../../data/academicFields'

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
  local.value.education.unshift({
    _key: `edu-${Date.now()}`,
    degree: 'کارشناسی',
    field: '',
    university: '',
    start_date: '',
    end_date: '',
    start_year: null,
    end_year: null,
    gpa: null,
  })
}

function removeEducation(index) {
  local.value.education.splice(index, 1)
}

function onGpa(edu, e) {
  const digits = String(e.target.value || '').replace(/\D/g, '').slice(0, 3)
  if (digits.length <= 2) {
    edu.gpa = digits
    return
  }
  edu.gpa = `${digits.slice(0, 2)}.${digits.slice(2)}`
}

function normalizeGpa(edu) {
  if (edu.gpa === '' || edu.gpa == null) {
    edu.gpa = null
    return
  }
  const n = Number(edu.gpa)
  if (!Number.isFinite(n) || n < 0 || n > 20) {
    edu.gpa = null
    return
  }
  edu.gpa = n.toFixed(1)
}
</script>
