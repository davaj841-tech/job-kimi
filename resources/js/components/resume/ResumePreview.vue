<template>
  <div
    id="resume-preview"
    class="resume-a4"
    :class="'layout-' + layout"
    :style="{
      '--accent': theme.accent,
      '--header': headerBg,
      '--sidebar': theme.sidebar,
      fontFamily: theme.fontFamily,
    }"
    dir="rtl"
  >
    <div v-if="layout === 'sidebar' || layout === 'split'" class="cv-split">
      <aside class="cv-side">
        <img
          v-if="data.personal?.photo"
          :src="data.personal.photo"
          class="cv-photo"
          alt=""
        />
        <h1 class="cv-side-name">
          {{ data.personal?.full_name || 'نام و نام خانوادگی' }}
        </h1>
        <p v-if="data.target_job" class="cv-role">
          {{ data.target_job }}
        </p>
        <h2>اطلاعات شخصی</h2>
        <div
          v-for="(row, i) in personalRows"
          :key="'p-' + i"
          class="cv-fact cv-fact-stack"
        >
          <span class="cv-fact-k">{{ row.label }}</span>
          <span class="cv-fact-v">{{ row.value }}</span>
        </div>
        <template v-if="(data.skills || []).length">
          <h2>مهارت‌ها</h2>
          <div class="cv-chips">
            <span
              v-for="(s, i) in data.skills"
              :key="'sk-' + i"
              class="cv-chip"
            >
              {{ s.name }}
              <em v-if="s.level">{{ s.level }}</em>
            </span>
          </div>
        </template>
        <template v-if="(data.languages || []).length">
          <h2>زبان‌ها</h2>
          <div
            v-for="(lang, i) in data.languages"
            :key="'lang-' + i"
            class="cv-lang"
          >
            <span>{{ lang.name }}</span>
            <span class="cv-dots">
              <i
                v-for="n in 5"
                :key="n"
                :class="{ on: n <= levelDots(lang.level) }"
              />
            </span>
          </div>
        </template>
      </aside>
      <div class="cv-main">
        <section v-if="data.summary" class="cv-sec">
          <h2>معرفی</h2>
          <p class="cv-text">{{ data.summary }}</p>
        </section>
        <div class="cv-two">
          <section v-if="(data.education || []).length" class="cv-sec">
            <h2>تحصیلات</h2>
            <div
              v-for="(edu, i) in data.education"
              :key="'edu-s-' + i"
              class="cv-item"
            >
              <div class="cv-item-top">
                <div>
                  <p class="cv-item-title">
                    {{ [edu.degree, edu.field].filter(Boolean).join(' — ') }}
                  </p>
                  <p class="cv-item-sub">{{ edu.university }}</p>
                  <p v-if="edu.gpa" class="cv-item-sub">
                    معدل {{ toFa(edu.gpa) }}
                  </p>
                </div>
                <span class="cv-date">{{ eduRange(edu) }}</span>
              </div>
            </div>
          </section>
          <section v-if="(data.experience || []).length" class="cv-sec">
            <h2>سوابق شغلی</h2>
            <div
              v-for="(exp, i) in data.experience"
              :key="'exp-s-' + i"
              class="cv-item"
            >
              <div class="cv-item-top">
                <div>
                  <p class="cv-item-title">{{ exp.title }}</p>
                  <p class="cv-item-sub">{{ exp.company }}</p>
                </div>
                <span class="cv-date">{{ expRange(exp) }}</span>
              </div>
              <p v-if="exp.description" class="cv-text">
                {{ exp.description }}
              </p>
            </div>
          </section>
        </div>
      </div>
    </div>

    <template v-else>
      <div v-if="isBanner" class="cv-banner">
        <img
          v-if="data.personal?.photo"
          :src="data.personal.photo"
          class="cv-photo"
          alt=""
        />
        <div>
          <h1>{{ data.personal?.full_name || 'نام و نام خانوادگی' }}</h1>
          <p v-if="data.target_job" class="cv-role">
            {{ data.target_job }}
          </p>
        </div>
      </div>
      <template v-else>
        <div class="cv-bar" />
        <header class="cv-head">
          <img
            v-if="data.personal?.photo"
            :src="data.personal.photo"
            class="cv-photo"
            alt=""
          />
          <div class="cv-head-text">
            <h1>{{ data.personal?.full_name || 'نام و نام خانوادگی' }}</h1>
            <p v-if="data.target_job" class="cv-role">
              {{ data.target_job }}
            </p>
          </div>
        </header>
      </template>

      <section class="cv-sec">
        <h2>اطلاعات شخصی</h2>
        <div class="cv-facts">
          <div v-for="(row, i) in personalRows" :key="i" class="cv-fact">
            <span class="cv-fact-k">{{ row.label }}</span>
            <span class="cv-fact-v">{{ row.value }}</span>
          </div>
        </div>
      </section>

      <section v-if="data.summary" class="cv-sec">
        <h2>معرفی</h2>
        <p class="cv-text">{{ data.summary }}</p>
      </section>

      <div class="cv-two">
        <section v-if="(data.education || []).length" class="cv-sec">
          <h2>تحصیلات</h2>
          <table class="cv-grid">
            <thead>
              <tr>
                <th>مقطع / رشته</th>
                <th>دانشگاه</th>
                <th>از تاریخ تا تاریخ</th>
                <th>معدل</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(edu, i) in data.education" :key="'edu-' + i">
                <td>
                  {{ [edu.degree, edu.field].filter(Boolean).join(' — ') }}
                </td>
                <td>{{ edu.university }}</td>
                <td class="cv-period" dir="ltr">{{ eduRange(edu) }}</td>
                <td dir="ltr">{{ edu.gpa ? toFa(edu.gpa) : '' }}</td>
              </tr>
            </tbody>
          </table>
        </section>
        <section v-if="(data.experience || []).length" class="cv-sec">
          <h2>سوابق شغلی</h2>
          <table class="cv-grid">
            <thead>
              <tr>
                <th>عنوان</th>
                <th>محل کار</th>
                <th>از تاریخ تا تاریخ</th>
                <th>توضیحات</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(exp, i) in data.experience" :key="'exp-' + i">
                <td>{{ exp.title }}</td>
                <td>{{ exp.company }}</td>
                <td class="cv-period" dir="ltr">{{ expRange(exp) }}</td>
                <td>{{ exp.description }}</td>
              </tr>
            </tbody>
          </table>
        </section>
      </div>

      <section v-if="(data.skills || []).length" class="cv-sec">
        <h2>مهارت‌ها</h2>
        <div class="cv-chips">
          <span v-for="(s, i) in data.skills" :key="'sk-' + i" class="cv-chip">
            {{ s.name }}
            <em v-if="s.level">{{ s.level }}</em>
          </span>
        </div>
      </section>

      <section v-if="(data.languages || []).length" class="cv-sec">
        <h2>زبان‌ها</h2>
        <div class="cv-langs">
          <div
            v-for="(lang, i) in data.languages"
            :key="'lang-' + i"
            class="cv-lang"
          >
            <span>{{ lang.name }}</span>
            <span class="cv-dots">
              <i
                v-for="n in 5"
                :key="n"
                :class="{ on: n <= levelDots(lang.level) }"
              />
            </span>
          </div>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { resumeThemeById } from '../../data/resumeThemes'

const props = defineProps({
  data: { type: Object, required: true },
  template: { type: String, default: 'modern' },
  templateId: { type: Number, default: 1 },
})

const theme = computed(() => resumeThemeById(props.templateId))
const layout = computed(() => theme.value.layout || 'classic')
const isBanner = computed(() =>
  ['banner', 'magazine', 'bold', 'coral', 'aurora', 'dual'].includes(
    layout.value
  )
)
const headerBg = computed(() => {
  const h = theme.value.header
  if (
    layout.value === 'classic' &&
    (h === '#ffffff' || String(h).startsWith('#f'))
  ) {
    return '#0f172a'
  }
  return h
})

const maritalLabel = computed(() => {
  const map = { single: 'مجرد', married: 'متاهل', divorced: 'مطلقه / متعلقه' }
  const v = props.data?.personal?.marital_status
  return v ? map[v] || v : ''
})

const personalRows = computed(() => {
  const p = props.data?.personal || {}
  const birthPlace = [p.birth_province, p.birth_city]
    .filter(Boolean)
    .join(' / ')
  return [
    { label: 'موبایل', value: p.mobile },
    { label: 'تلفن منزل', value: p.home_phone },
    { label: 'ایمیل', value: p.email },
    { label: 'کد ملی', value: p.national_code },
    { label: 'تاریخ تولد', value: p.birth_date },
    { label: 'محل تولد', value: birthPlace },
    { label: 'وضعیت تاهل', value: maritalLabel.value },
    { label: 'وضعیت سربازی', value: p.military_status },
    { label: 'سابقه بیمه', value: p.insurance_history },
    { label: 'رشته تحصیلی', value: p.field_of_study },
    { label: 'آدرس', value: p.address },
    { label: 'کد پستی', value: p.postal_code },
  ].filter((r) => String(r.value || '').trim())
})

function levelDots(level) {
  const map = {
    مبتدی: 2,
    متوسط: 3,
    حرفه‌ای: 5,
    A1: 1,
    A2: 2,
    B1: 3,
    B2: 4,
    C1: 5,
    C2: 5,
  }
  return map[level] || 3
}

function toFa(n) {
  if (n == null || n === '') return ''
  return String(n).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d])
}

function ym(val) {
  const m = String(val || '').match(/^(\d{4})-(\d{1,2})/)
  if (m) return `${m[1]}/${String(m[2]).padStart(2, '0')}`
  return String(val || '')
}

function yearRange(start, end) {
  const a = ym(start) || String(start || '')
  const b = ym(end) || String(end || '')
  if (!a && !b) return ''
  if (!b) return toFa(a)
  if (!a) return toFa(b)
  return `${toFa(a)} - ${toFa(b)}`
}

function eduRange(edu) {
  return yearRange(
    edu.start_date || edu.start_year,
    edu.end_date || edu.end_year
  )
}

function expRange(exp) {
  const start = ym(exp.start_date)
  const end = exp.is_current ? 'اکنون' : ym(exp.end_date)
  if (!start && !end) return ''
  if (!end) return toFa(start)
  if (!start) return end === 'اکنون' ? end : toFa(end)
  return `${toFa(start)} - ${end === 'اکنون' ? end : toFa(end)}`
}
</script>

<style scoped>
.resume-a4 {
  width: 210mm;
  min-height: 297mm;
  box-sizing: border-box;
  background: #fff;
  color: #0f172a;
  padding: 0 0 18mm;
}
.cv-bar {
  height: 8px;
  background: var(--header, #0f172a);
}
.cv-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 16px 18mm 14px;
}
.cv-head-text {
  min-width: 0;
  flex: 1;
}
.cv-head h1,
.cv-banner h1,
.cv-side-name {
  margin: 0;
  font-size: 26px;
  font-weight: 900;
  line-height: 1.35;
}
.cv-role {
  margin: 6px 0 0;
  font-size: 13px;
  font-weight: 700;
  color: var(--accent, #1a365d);
}
.cv-photo {
  width: 96px;
  height: 128px;
  object-fit: cover;
  border-radius: 4px;
  border: 1px solid #cbd5e1;
  background: #e2e8f0;
  flex-shrink: 0;
}
.cv-two {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px 14px;
  padding: 0 18mm;
  margin-top: 12px;
  align-items: start;
  direction: rtl;
}
.cv-two .cv-sec {
  padding: 0;
  margin-top: 0;
}
.cv-two .cv-sec + .cv-sec {
  border-right: 1.5px solid var(--accent, #1a365d);
  padding-right: 12px;
  margin-right: 2px;
}
.cv-two h2 {
  text-align: right;
}
.cv-main .cv-two {
  padding-left: 12mm;
  padding-right: 12mm;
}
.cv-sec {
  padding: 0 18mm;
  margin-top: 12px;
}
.cv-sec h2,
.cv-side h2 {
  margin: 0 0 8px;
  font-size: 12.5px;
  font-weight: 800;
  color: var(--accent, #1a365d);
  border-bottom: 1.5px solid var(--accent, #1a365d);
  padding-bottom: 4px;
}
.cv-facts {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 5px 18px;
}
.cv-grid {
  width: 100%;
  border-collapse: collapse;
  font-size: 10.5px;
  border: 1px solid var(--accent, #1a365d);
}
.cv-grid th {
  background: var(--accent, #1a365d);
  color: #fff;
  text-align: right;
  padding: 5px 6px;
  font-size: 10px;
  border: 1px solid var(--accent, #1a365d);
}
.cv-grid td {
  border: 1px solid #cbd5e1;
  padding: 6px;
  vertical-align: top;
  text-align: right;
}
.cv-fact {
  display: block;
  font-size: 10.5px;
  line-height: 1.6;
  text-align: right;
  direction: rtl;
  unicode-bidi: plaintext;
}
.cv-fact-stack {
  margin-bottom: 6px;
}
.cv-fact-k {
  color: #64748b;
}
.cv-fact-k::after {
  content: ': ';
}
.cv-fact-v {
  color: #0f172a;
  font-weight: 600;
  word-break: break-word;
}
.cv-text {
  margin: 0;
  font-size: 11px;
  line-height: 1.9;
  color: #334155;
  white-space: pre-line;
}
.cv-item {
  margin-bottom: 10px;
  padding-right: 10px;
  border-right: 2px solid #e2e8f0;
}
.cv-item-top {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  align-items: flex-start;
}
.cv-item-title {
  margin: 0;
  font-size: 12px;
  font-weight: 800;
}
.cv-item-sub {
  margin: 2px 0 0;
  font-size: 10.5px;
  color: #64748b;
}
.cv-date {
  flex-shrink: 0;
  font-size: 10.5px;
  font-weight: 700;
  color: var(--accent, #1a365d);
  direction: ltr;
  unicode-bidi: plaintext;
}
.cv-period {
  text-align: center;
  vertical-align: middle;
  white-space: nowrap;
  font-size: 10px;
  font-weight: 700;
  color: var(--accent, #1a365d);
}
.cv-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.cv-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  border-radius: 999px;
  padding: 3px 10px;
  font-size: 10.5px;
  font-weight: 600;
}
.cv-chip em {
  font-style: normal;
  color: #64748b;
  font-size: 9.5px;
  font-weight: 500;
}
.cv-langs {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px 22px;
}
.cv-lang {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 11px;
  gap: 8px;
  margin-bottom: 4px;
}
.cv-dots {
  display: inline-flex;
  gap: 3px;
  direction: ltr;
}
.cv-dots i {
  width: 7px;
  height: 7px;
  border-radius: 999px;
  background: #cbd5e1;
  display: inline-block;
}
.cv-dots i.on {
  background: var(--accent, #1a365d);
}
.cv-banner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 18px 18mm;
  background: var(--header, #0f172a);
  color: #fff;
}
.cv-banner .cv-role {
  color: #fff;
  opacity: 0.9;
}
.cv-banner .cv-photo {
  border-color: rgba(255, 255, 255, 0.35);
}
.cv-split {
  display: grid;
  grid-template-columns: 68mm 1fr;
  min-height: 297mm;
}
.cv-side {
  background: var(--sidebar, #f8fafc);
  padding: 16px 12px 20px;
}
.cv-side .cv-photo {
  display: block;
  margin: 0 auto 12px;
}
.cv-side-name {
  font-size: 18px;
  text-align: center;
}
.cv-side .cv-role {
  text-align: center;
  margin-bottom: 14px;
}
.cv-side h2 {
  margin-top: 14px;
}
.cv-main {
  padding: 12px 0 18px;
}
.cv-main .cv-sec {
  padding-left: 12mm;
  padding-right: 12mm;
}

.layout-timeline .cv-item {
  border-right: 3px solid var(--accent);
  padding-right: 14px;
  position: relative;
}
.layout-cards .cv-sec {
  margin: 10px 14mm 0;
  padding: 10px 12px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #fafafa;
}
.layout-cards .cv-sec h2 {
  border-bottom-width: 0;
  background: var(--accent);
  color: #fff;
  margin: -10px -12px 10px;
  padding: 6px 12px;
  border-radius: 10px 10px 0 0;
}
.layout-compact {
  font-size: 10px;
}
.layout-compact .cv-sec {
  margin-top: 8px;
  padding: 0 14mm;
}
.layout-compact .cv-head h1 {
  font-size: 20px;
}
.layout-elegant .cv-sec h2 {
  border-bottom-width: 0.5px;
  letter-spacing: 0.08em;
  font-weight: 600;
}
.layout-elegant .cv-item {
  border-right: 0;
  border-bottom: 0.5px solid #e7e5e4;
  padding: 0 0 8px;
}
.layout-bold .cv-banner {
  padding: 28px 18mm;
}
.layout-bold .cv-banner h1 {
  font-size: 32px;
}
.layout-magazine .cv-banner h1 {
  font-size: 34px;
  font-weight: 700;
}
.layout-sidebar .cv-side {
  color: #fff;
  background: var(--header, #0f172a);
}
.layout-sidebar .cv-side h2 {
  color: #fff;
  border-bottom-color: rgba(255, 255, 255, 0.35);
}
.layout-sidebar .cv-fact-k,
.layout-sidebar .cv-chip em {
  color: rgba(255, 255, 255, 0.7);
}
.layout-sidebar .cv-fact-v,
.layout-sidebar .cv-side-name,
.layout-sidebar .cv-role {
  color: #fff;
}
.layout-sidebar .cv-chip {
  background: rgba(255, 255, 255, 0.08);
  border-color: rgba(255, 255, 255, 0.2);
  color: #fff;
}
.layout-sidebar .cv-dots i {
  background: rgba(255, 255, 255, 0.25);
}
.layout-sidebar .cv-dots i.on {
  background: #fff;
}
.layout-coral .cv-banner {
  background: linear-gradient(135deg, #fb7185, #e11d48);
}
.layout-coral .cv-sec h2 {
  color: #e11d48;
  border-bottom-color: #fda4af;
}
.layout-aurora .cv-banner {
  background: linear-gradient(120deg, #5b21b6, #7c3aed 45%, #a855f7);
}
.layout-aurora .cv-item {
  border-right: 3px solid #a78bfa;
  background: #f5f3ff;
  border-radius: 0 10px 10px 0;
  padding: 8px 10px;
}
.layout-paper {
  background: #fafaf9;
}
.layout-paper .cv-bar {
  height: 2px;
  background: #a8a29e;
}
.layout-paper .cv-head h1 {
  color: #292524;
  font-weight: 600;
}
.layout-paper .cv-sec h2 {
  color: #57534e;
  border-bottom: 1px dashed #d6d3d1;
  font-weight: 600;
  letter-spacing: 0.04em;
}
.layout-geo .cv-banner {
  clip-path: polygon(0 0, 100% 0, 100% 82%, 0 100%);
  padding-bottom: 36px;
}
.layout-geo .cv-item {
  border-right: 0;
  border: 1px solid #a5f3fc;
  border-radius: 12px;
  padding: 10px;
  background: #ecfeff;
}
.layout-dual .cv-banner {
  background: linear-gradient(90deg, #0f2744 55%, #9f1239 55%);
}
.layout-dual .cv-sec h2 {
  border-bottom: 2px solid #9f1239;
  color: #0f2744;
}
</style>
