<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between gap-2">
      <h2 class="text-lg font-bold text-desk-text dark:text-white">سوابق شغلی</h2>
      <button
        type="button"
        class="inline-flex items-center gap-2 rounded-xl bg-slate-100 px-3 py-2 text-sm font-medium dark:bg-slate-800"
        @click="addExperience"
      >
        <PlusIcon class="h-4 w-4" />
        افزودن
      </button>
    </div>

    <div
      ref="sortableRef"
      class="space-y-3"
    >
      <div
        v-for="(exp, index) in local.experience"
        :key="exp._key || index"
        class="rounded-xl border border-surface-line bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50"
      >
        <div class="flex items-start gap-2">
          <div class="cursor-move pt-2 text-desk-muted">
            <Bars3Icon class="h-5 w-5" />
          </div>
          <div class="min-w-0 flex-1 space-y-3">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
              <FormInput
                v-model="exp.title"
                label="عنوان شغلی"
                placeholder="برنامه‌نویس"
              />
              <FormInput
                v-model="exp.company"
                label="شرکت"
                placeholder="شرکت …"
              />
              <JalaliMonthYear
                v-model="exp.start_date"
                label="از تاریخ"
                :error="dateRangeError(exp)"
              />
              <div class="flex items-end gap-2">
                <JalaliMonthYear
                  v-model="exp.end_date"
                  label="تا تاریخ"
                  :disabled="exp.is_current"
                  :error="dateRangeError(exp)"
                  class="flex-1"
                />
                <label class="mb-2 flex items-center gap-1.5 whitespace-nowrap text-xs">
                  <input
                    v-model="exp.is_current"
                    type="checkbox"
                    class="rounded"
                  />
                  تا الان
                </label>
              </div>
              <p
                v-if="dateRangeError(exp)"
                class="md:col-span-2 text-xs text-red-600"
              >
                {{ dateRangeError(exp) }}
              </p>
            </div>
            <div class="relative">
              <textarea
                v-model="exp.description"
                rows="3"
                maxlength="2000"
                class="input-field min-h-[80px] resize-none py-2 text-sm"
                placeholder="توضیحات و دستاوردها…"
              />
              <button
                type="button"
                class="absolute bottom-2 left-2 inline-flex items-center gap-1 text-xs font-medium text-brand hover:underline disabled:opacity-50"
                :disabled="aiBusy === index"
                @click="enhance(index)"
              >
                <SparklesIcon
                  class="h-3.5 w-3.5"
                  :class="{ 'animate-spin': aiBusy === index }"
                />
                بهبود با AI
              </button>
            </div>
          </div>
          <button
            type="button"
            class="p-2 text-desk-muted hover:text-red-500"
            @click="removeExperience(index)"
          >
            <TrashIcon class="h-5 w-5" />
          </button>
        </div>
      </div>
    </div>

    <div
      v-if="!(local.experience || []).length"
      class="rounded-xl border-2 border-dashed border-surface-line py-12 text-center dark:border-slate-700"
    >
      <BriefcaseIcon class="mx-auto mb-3 h-10 w-10 text-slate-300" />
      <p class="text-sm text-desk-muted">هنوز سابقه‌ای اضافه نشده</p>
      <button
        type="button"
        class="mt-2 text-sm font-medium text-brand hover:underline"
        @click="addExperience"
      >
        افزودن اولین سابقه
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import {
  Bars3Icon,
  BriefcaseIcon,
  PlusIcon,
  SparklesIcon,
  TrashIcon,
} from '@heroicons/vue/24/outline'
import Sortable from 'sortablejs'
import FormInput from '../FormInput.vue'
import JalaliMonthYear from '../JalaliMonthYear.vue'
import { compareJalaliMonth, RANGE_ORDER_ERROR } from '../../../utils/jalali'

const props = defineProps({
  modelValue: { type: Object, required: true },
})
const emit = defineEmits(['update:modelValue', 'ai-enhance'])

function dateRangeError(exp) {
  if (exp?.is_current || !exp?.start_date || !exp?.end_date) return ''
  const cmp = compareJalaliMonth(exp.start_date, exp.end_date)
  return cmp !== null && cmp >= 0 ? RANGE_ORDER_ERROR : ''
}

const local = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

const sortableRef = ref(null)
const aiBusy = ref(null)
let sortable = null

function ensureKeys() {
  ;(local.value.experience || []).forEach((e, i) => {
    if (!e._key) e._key = `exp-${Date.now()}-${i}`
  })
}

function addExperience() {
  if (!Array.isArray(local.value.experience)) local.value.experience = []
  local.value.experience.unshift({
    _key: `exp-${Date.now()}`,
    title: '',
    company: '',
    start_date: '',
    end_date: '',
    is_current: false,
    description: '',
  })
}

function removeExperience(index) {
  local.value.experience.splice(index, 1)
}

async function enhance(index) {
  const exp = local.value.experience[index]
  if (!exp?.title || !exp?.description) return
  aiBusy.value = index
  try {
    const enhanced = await new Promise((resolve, reject) => {
      emit('ai-enhance', { exp, resolve, reject })
    })
    if (enhanced) exp.description = enhanced
  } catch (_) {
    /* toast in parent */
  } finally {
    aiBusy.value = null
  }
}

function initSortable() {
  if (!sortableRef.value) return
  sortable?.destroy()
  sortable = Sortable.create(sortableRef.value, {
    handle: '.cursor-move',
    animation: 200,
    onEnd: (e) => {
      if (e.oldIndex == null || e.newIndex == null || e.oldIndex === e.newIndex) return
      const list = local.value.experience
      const [item] = list.splice(e.oldIndex, 1)
      list.splice(e.newIndex, 0, item)
    },
  })
}

onMounted(async () => {
  ensureKeys()
  await nextTick()
  initSortable()
})

watch(
  () => (local.value.experience || []).length,
  async () => {
    ensureKeys()
    await nextTick()
    initSortable()
  },
)
</script>
