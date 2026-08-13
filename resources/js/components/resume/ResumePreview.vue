<template>
  <div
    id="resume-preview"
    class="bg-white text-slate-900"
    :class="rootClass"
    dir="rtl"
  >
    <!-- Modern / Creative -->
    <div
      v-if="templateStyle === 'modern' || templateStyle === 'creative'"
      class="p-7"
    >
      <div
        class="flex items-start gap-4 border-b-2 pb-5"
        :class="templateStyle === 'creative' ? 'border-desk-orange' : 'border-brand'"
      >
        <img
          v-if="data.personal?.photo"
          :src="data.personal.photo"
          class="h-20 w-20 rounded-full object-cover"
          alt=""
        />
        <div class="min-w-0 flex-1">
          <h1 class="text-2xl font-black">{{ data.personal?.full_name || 'نام شما' }}</h1>
          <p
            class="mt-1 text-base font-medium"
            :class="templateStyle === 'creative' ? 'text-desk-orange' : 'text-brand'"
          >
            {{ data.target_job || 'عنوان شغلی' }}
          </p>
          <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-600">
            <span v-if="data.personal?.email">{{ data.personal.email }}</span>
            <span v-if="data.personal?.mobile">{{ data.personal.mobile }}</span>
            <span v-if="data.personal?.national_code"
              >کد ملی: {{ data.personal.national_code }}</span
            >
            <span v-if="data.personal?.birth_date"
              >تولد: {{ data.personal.birth_date }}</span
            >
            <span v-if="birthPlace">محل تولد: {{ birthPlace }}</span>
            <span v-if="maritalLabel">{{ maritalLabel }}</span>
            <span v-if="data.personal?.field_of_study"
              >رشته: {{ data.personal.field_of_study }}</span
            >
            <span v-if="data.personal?.address">{{ data.personal.address }}</span>
          </div>
        </div>
      </div>

      <p
        v-if="data.summary"
        class="mt-4 text-sm leading-relaxed text-slate-700"
      >
        {{ data.summary }}
      </p>

      <section
        v-if="(data.experience || []).length"
        class="mt-5"
      >
        <h2 class="mb-3 border-b border-slate-200 pb-1 text-sm font-bold">سوابق شغلی</h2>
        <div
          v-for="(exp, i) in data.experience"
          :key="i"
          class="mb-3"
        >
          <div class="flex justify-between gap-2">
            <div>
              <h3 class="text-sm font-bold">{{ exp.title }}</h3>
              <p class="text-xs text-brand">{{ exp.company }}</p>
            </div>
            <span class="shrink-0 text-xs text-slate-500">{{ dateRange(exp) }}</span>
          </div>
          <p
            v-if="exp.description"
            class="mt-1 whitespace-pre-line text-xs leading-relaxed text-slate-600"
          >
            {{ exp.description }}
          </p>
        </div>
      </section>

      <section
        v-if="(data.education || []).length"
        class="mt-5"
      >
        <h2 class="mb-3 border-b border-slate-200 pb-1 text-sm font-bold">تحصیلات</h2>
        <div
          v-for="(edu, i) in data.education"
          :key="i"
          class="mb-2 flex justify-between gap-2"
        >
          <div>
            <h3 class="text-sm font-bold">{{ edu.degree }} · {{ edu.field }}</h3>
            <p class="text-xs text-slate-600">{{ edu.university }}</p>
          </div>
          <span class="text-xs text-slate-500">{{ yearRange(edu) }}</span>
        </div>
      </section>

      <section
        v-if="(data.skills || []).length"
        class="mt-5"
      >
        <h2 class="mb-3 border-b border-slate-200 pb-1 text-sm font-bold">مهارت‌ها</h2>
        <div class="flex flex-wrap gap-1.5">
          <span
            v-for="(skill, i) in data.skills"
            :key="i"
            class="rounded-md bg-slate-100 px-2 py-1 text-xs"
          >
            {{ skill.name || skill }}
          </span>
        </div>
      </section>

      <section
        v-if="(data.languages || []).length"
        class="mt-5"
      >
        <h2 class="mb-3 border-b border-slate-200 pb-1 text-sm font-bold">زبان‌ها</h2>
        <div class="flex flex-wrap gap-2 text-xs">
          <span
            v-for="(lang, i) in data.languages"
            :key="i"
          >
            {{ lang.name }}<span v-if="lang.level"> ({{ lang.level }})</span>
          </span>
        </div>
      </section>
    </div>

    <!-- Classic -->
    <div
      v-else-if="templateStyle === 'classic'"
      class="p-7 font-serif"
    >
      <div class="border-b border-slate-800 pb-4 text-center">
        <h1 class="text-2xl font-bold tracking-wide">{{ data.personal?.full_name }}</h1>
        <p class="mt-1 text-sm">{{ data.target_job }}</p>
        <p class="mt-2 text-xs text-slate-600">
          {{ [data.personal?.email, data.personal?.mobile, data.personal?.address].filter(Boolean).join(' · ') }}
        </p>
      </div>
      <p
        v-if="data.summary"
        class="mt-4 text-center text-sm italic text-slate-700"
      >
        {{ data.summary }}
      </p>
      <template
        v-for="block in classicBlocks"
        :key="block.title"
      >
        <div
          v-if="block.show"
          class="mt-5"
        >
          <h2 class="mb-2 text-center text-xs font-bold uppercase tracking-widest text-slate-800">
            {{ block.title }}
          </h2>
          <div
            class="text-xs leading-relaxed"
            v-html="block.html"
          />
        </div>
      </template>
    </div>

    <!-- Minimal -->
    <div
      v-else
      class="p-7"
    >
      <h1 class="text-xl font-semibold tracking-tight">{{ data.personal?.full_name }}</h1>
      <p class="text-sm text-slate-500">{{ data.target_job }}</p>
      <p class="mt-2 text-[11px] text-slate-500">
        {{ [data.personal?.email, data.personal?.mobile].filter(Boolean).join('  ·  ') }}
      </p>
      <hr class="my-4 border-slate-200" />
      <p
        v-if="data.summary"
        class="mb-4 text-xs leading-6 text-slate-700"
      >
        {{ data.summary }}
      </p>
      <div
        v-for="(exp, i) in data.experience || []"
        :key="'m' + i"
        class="mb-3"
      >
        <p class="text-xs font-semibold">{{ exp.title }} — {{ exp.company }}</p>
        <p class="text-[10px] text-slate-400">{{ dateRange(exp) }}</p>
        <p class="mt-1 whitespace-pre-line text-[11px] text-slate-600">{{ exp.description }}</p>
      </div>
      <div
        v-if="(data.skills || []).length"
        class="mt-4 text-[11px] text-slate-600"
      >
        {{ (data.skills || []).map((s) => s.name || s).join(' · ') }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  data: { type: Object, required: true },
  template: { type: String, default: 'modern' },
  templateId: { type: Number, default: 1 },
})

const templateStyle = computed(() => {
  if (props.template) return props.template
  return { 1: 'modern', 2: 'minimal', 3: 'classic' }[props.templateId] || 'modern'
})

const birthPlace = computed(() => {
  const p = props.data?.personal || {}
  return [p.birth_province, p.birth_city].filter(Boolean).join(' / ')
})

const maritalLabel = computed(() => {
  const map = {
    single: 'مجرد',
    married: 'متاهل',
    divorced: 'مطلقه / متعلقه',
  }
  const v = props.data?.personal?.marital_status
  return v ? map[v] || v : ''
})

const rootClass = computed(() =>
  templateStyle.value === 'creative' ? 'bg-gradient-to-br from-white to-orange-50' : '',
)

function dateRange(exp) {
  const start = exp.start_date || ''
  const end = exp.is_current ? 'تاکنون' : exp.end_date || ''
  return [start, end].filter(Boolean).join(' – ')
}

function yearRange(edu) {
  return [edu.start_year, edu.end_year].filter(Boolean).join(' – ')
}

const classicBlocks = computed(() => {
  const d = props.data || {}
  const expHtml = (d.experience || [])
    .map(
      (e) =>
        `<p><strong>${e.title || ''}</strong> — ${e.company || ''}<br/><span style="color:#64748b">${dateRange(e)}</span><br/>${(e.description || '').replace(/\n/g, '<br/>')}</p>`,
    )
    .join('')
  const eduHtml = (d.education || [])
    .map(
      (e) =>
        `<p><strong>${e.degree || ''} · ${e.field || ''}</strong><br/>${e.university || ''} · ${yearRange(e)}</p>`,
    )
    .join('')
  const skills = (d.skills || []).map((s) => s.name || s).join('، ')
  const langs = (d.languages || []).map((l) => `${l.name}${l.level ? ` (${l.level})` : ''}`).join('، ')

  return [
    { title: 'Experience', show: !!(d.experience || []).length, html: expHtml },
    { title: 'Education', show: !!(d.education || []).length, html: eduHtml },
    { title: 'Skills', show: !!skills, html: `<p>${skills}</p>` },
    { title: 'Languages', show: !!langs, html: `<p>${langs}</p>` },
  ]
})
</script>
