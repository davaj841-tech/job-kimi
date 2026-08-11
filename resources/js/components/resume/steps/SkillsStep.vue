<template>
  <div class="space-y-5">
    <h2 class="text-lg font-bold text-desk-text dark:text-white">مهارت‌ها</h2>

    <button
      type="button"
      class="flex w-full items-center justify-center gap-2 rounded-xl border border-brand/25 bg-brand-soft p-3.5 text-sm font-medium text-brand transition hover:bg-brand/10 disabled:opacity-50 dark:bg-brand/10"
      :disabled="aiLoading"
      @click="suggestSkills"
    >
      <SparklesIcon
        class="h-4 w-4"
        :class="{ 'animate-spin': aiLoading }"
      />
      پیشنهاد مهارت بر اساس سوابق
    </button>

    <div class="rounded-xl border border-surface-line bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
      <div class="mb-3 flex flex-wrap gap-2">
        <span
          v-for="(skill, index) in local.skills"
          :key="skill.name + index"
          class="inline-flex items-center gap-1 rounded-lg bg-brand/10 px-3 py-1.5 text-sm text-brand"
        >
          {{ skill.name }}
          <span
            v-if="skill.level"
            class="text-[10px] opacity-70"
            >({{ skill.level }})</span
          >
          <button
            type="button"
            class="hover:text-red-500"
            @click="removeSkill(index)"
          >
            <XMarkIcon class="h-3.5 w-3.5" />
          </button>
        </span>
      </div>
      <div class="flex flex-col gap-2 sm:flex-row">
        <input
          v-model="newSkill"
          type="text"
          class="input-field flex-1"
          placeholder="مهارت را بنویسید و Enter بزنید…"
          @keydown.enter.prevent="addSkill"
        />
        <select
          v-model="newLevel"
          class="input-field sm:w-36"
        >
          <option value="متوسط">متوسط</option>
          <option value="مبتدی">مبتدی</option>
          <option value="حرفه‌ای">حرفه‌ای</option>
        </select>
      </div>
    </div>

    <div>
      <p class="mb-2 text-sm text-desk-muted">مهارت‌های پرتکرار:</p>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="skill in popularSkills"
          :key="skill"
          type="button"
          class="rounded-lg border border-surface-line bg-white px-3 py-1.5 text-sm transition hover:border-brand hover:text-brand disabled:opacity-40 dark:border-slate-700 dark:bg-slate-800"
          :disabled="hasSkill(skill)"
          @click="addNamed(skill)"
        >
          + {{ skill }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { SparklesIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const popularSkills = [
  'Vue.js',
  'React',
  'Laravel',
  'Python',
  'UI/UX',
  'Figma',
  'Git',
  'Docker',
  'TypeScript',
  'کار تیمی',
  'Office',
  'SQL',
]

const props = defineProps({
  modelValue: { type: Object, required: true },
})
const emit = defineEmits(['update:modelValue', 'ai-skills'])

const local = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

const newSkill = ref('')
const newLevel = ref('متوسط')
const aiLoading = ref(false)

function hasSkill(name) {
  return (local.value.skills || []).some((s) => s.name === name)
}

function addSkill() {
  const name = newSkill.value.trim()
  if (!name || hasSkill(name)) return
  if (!Array.isArray(local.value.skills)) local.value.skills = []
  local.value.skills.push({ name, level: newLevel.value })
  newSkill.value = ''
}

function addNamed(name) {
  if (hasSkill(name)) return
  if (!Array.isArray(local.value.skills)) local.value.skills = []
  local.value.skills.push({ name, level: 'متوسط' })
}

function removeSkill(index) {
  local.value.skills.splice(index, 1)
}

async function suggestSkills() {
  aiLoading.value = true
  try {
    const skills = await new Promise((resolve, reject) => {
      emit('ai-skills', { resolve, reject })
    })
    if (Array.isArray(skills)) {
      skills.forEach((name) => {
        if (typeof name === 'string' && name.trim() && !hasSkill(name.trim())) {
          local.value.skills.push({ name: name.trim(), level: 'متوسط' })
        }
      })
    }
  } catch (_) {
    /* parent toast */
  } finally {
    aiLoading.value = false
  }
}
</script>
