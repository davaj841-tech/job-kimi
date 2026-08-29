<template>
  <div ref="rootEl" class="pdp relative" :class="{ 'pdp--disabled': disabled }">
    <label
      v-if="label"
      class="mb-1.5 block text-xs font-medium text-desk-muted dark:text-slate-400"
    >
      {{ label }}
      <span v-if="required" class="text-brand">*</span>
    </label>

    <button
      type="button"
      class="pdp-trigger"
      :disabled="disabled"
      :aria-expanded="open"
      dir="rtl"
      @click="toggle"
    >
      <span
        class="min-w-0 flex-1 truncate"
        :class="displayText ? 'text-ink dark:text-white' : 'text-slate-400'"
      >
        {{ displayText || placeholder }}
      </span>
      <svg
        class="h-4 w-4 shrink-0 text-brand"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
      >
        <rect x="3" y="5" width="18" height="16" rx="2" />
        <path d="M3 9h18M8 3v4M16 3v4" />
      </svg>
    </button>

    <p v-if="error" class="mt-1 text-xs text-red-600 dark:text-red-400">
      {{ error }}
    </p>

    <Teleport to="body">
      <div
        v-if="open"
        class="pdp-pop"
        :style="popStyle"
        dir="rtl"
        @mousedown.prevent
      >
        <div class="pdp-head">
          <button
            type="button"
            class="pdp-nav"
            aria-label="ماه بعد"
            @click="shiftMonth(1)"
          >
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
              <path
                d="M7.05 4.55a1 1 0 011.4 0l5 5a1 1 0 010 1.4l-5 5a1 1 0 11-1.4-1.4L11.1 10 7.05 5.95a1 1 0 010-1.4z"
              />
            </svg>
          </button>

          <!-- راست به چپ: ماه سپس سال -->
          <div class="flex flex-1 items-center justify-center gap-1">
            <button
              type="button"
              class="pdp-meta-btn"
              :class="{ 'is-open': monthOpen }"
              @click="toggleMonthList"
            >
              <span class="font-bold">{{ monthName }}</span>
              <svg
                class="h-3 w-3 opacity-70"
                viewBox="0 0 20 20"
                fill="currentColor"
              >
                <path
                  d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                />
              </svg>
            </button>
            <button
              type="button"
              class="pdp-meta-btn"
              :class="{ 'is-open': yearOpen }"
              @click="toggleYearList"
            >
              <span class="font-bold">{{ fa(view.jy) }}</span>
              <svg
                class="h-3 w-3 opacity-70"
                viewBox="0 0 20 20"
                fill="currentColor"
              >
                <path
                  d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                />
              </svg>
            </button>
          </div>

          <button
            type="button"
            class="pdp-nav"
            aria-label="ماه قبل"
            @click="shiftMonth(-1)"
          >
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
              <path
                d="M12.95 4.55a1 1 0 010 1.4L8.9 10l4.05 4.05a1 1 0 11-1.4 1.4l-5-5a1 1 0 010-1.4l5-5a1 1 0 011.4 0z"
              />
            </svg>
          </button>
        </div>

        <!-- لیست سال (جدولی) -->
        <div v-if="yearOpen" class="pdp-grid-table">
          <button
            v-for="y in yearOptions"
            :key="y"
            type="button"
            class="pdp-grid-opt"
            :class="{ 'is-active': y === view.jy }"
            @click="pickYear(y)"
          >
            {{ fa(y) }}
          </button>
        </div>

        <!-- لیست ماه (جدولی) -->
        <div
          v-else-if="monthOpen || mode === 'month'"
          class="pdp-grid-table pdp-grid-table--months"
        >
          <button
            v-for="(m, i) in months"
            :key="m"
            type="button"
            class="pdp-grid-opt"
            :class="{
              'is-active':
                (selected &&
                  selected.jy === view.jy &&
                  selected.jm === i + 1) ||
                (!selected && view.jm === i + 1),
            }"
            @click="pickMonth(i + 1)"
          >
            {{ m }}
          </button>
        </div>

        <template v-else>
          <div class="pdp-weekdays">
            <span v-for="w in weekdays" :key="w">{{ w }}</span>
          </div>
          <div class="pdp-grid">
            <button
              v-for="(cell, idx) in cells"
              :key="idx"
              type="button"
              class="pdp-day"
              :class="{
                'is-empty': !cell,
                'is-friday': cell && cell.wd === 6,
                'is-selected': cell && isSelected(cell),
                'is-today': cell && isToday(cell),
              }"
              :disabled="!cell"
              @click="cell && pickDay(cell)"
            >
              <span v-if="cell">{{ fa(cell.jd) }}</span>
            </button>
          </div>
        </template>

        <div class="pdp-foot">
          <button type="button" class="pdp-link" @click="clearValue">
            پاک کردن
          </button>
          <button type="button" class="pdp-link" @click="pickToday">
            امروز
          </button>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import {
  computed,
  nextTick,
  onBeforeUnmount,
  onMounted,
  reactive,
  ref,
  watch,
} from 'vue'
import {
  formatJalaliDayParts,
  formatJalaliMonthParts,
  gregorianIsoToJalaliParts,
  jalaliMonthLength,
  jalaliPartsToIso,
  jalaliWeekday,
  JALALI_MONTHS,
  parseJalaliDay,
  parseJalaliMonth,
  toFaDigits,
  toJalali,
} from '../../utils/jalali'

const props = defineProps({
  modelValue: { type: String, default: '' },
  /** date = روز کامل | month = فقط ماه/سال */
  mode: { type: String, default: 'date' },
  /** jalali = DD/MM/YYYY | iso = YYYY-MM-DD | month = YYYY-MM */
  format: { type: String, default: 'jalali' },
  label: { type: String, default: '' },
  placeholder: { type: String, default: 'روز / ماه / سال' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  error: { type: String, default: '' },
  yearFrom: { type: Number, default: 1330 },
  yearTo: { type: Number, default: 1410 },
})

const emit = defineEmits(['update:modelValue', 'change'])

const open = ref(false)
const yearOpen = ref(false)
const monthOpen = ref(false)
const rootEl = ref(null)
const popStyle = reactive({ top: '0px', left: '0px' })
const weekdays = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج']
const months = JALALI_MONTHS

const now = (() => {
  const d = new Date()
  return toJalali(d.getFullYear(), d.getMonth() + 1, d.getDate())
})()

const view = reactive({ jy: now.jy, jm: now.jm })

const selected = computed(() => parseModel(props.modelValue))

const yearOptions = computed(() => {
  const list = []
  for (let y = props.yearTo; y >= props.yearFrom; y -= 1) list.push(y)
  return list
})

const monthName = computed(() => months[view.jm - 1] || '')

/** نمایش راست‌به‌چپ: روز / ماه / سال */
const displayText = computed(() => {
  const s = selected.value
  if (!s) return ''
  if (props.mode === 'month' || props.format === 'month') {
    const mName = months[s.jm - 1] || toFaDigits(String(s.jm).padStart(2, '0'))
    return `${mName} / ${toFaDigits(s.jy)}`
  }
  // روز / ماه / سال
  return toFaDigits(formatJalaliDayParts(s)).replace(/\//g, ' / ')
})

const cells = computed(() => {
  const len = jalaliMonthLength(view.jy, view.jm)
  const firstWd = jalaliWeekday(view.jy, view.jm, 1)
  const out = Array.from({ length: firstWd }, () => null)
  for (let jd = 1; jd <= len; jd += 1) {
    out.push({
      jy: view.jy,
      jm: view.jm,
      jd,
      wd: jalaliWeekday(view.jy, view.jm, jd),
    })
  }
  while (out.length % 7 !== 0) out.push(null)
  return out
})

watch(
  () => props.modelValue,
  () => {
    const s = selected.value
    if (s) {
      view.jy = s.jy
      view.jm = s.jm
    }
  },
  { immediate: true }
)

function fa(n) {
  return toFaDigits(n)
}

function parseModel(val) {
  if (!val) return null
  if (props.mode === 'month' || props.format === 'month') {
    return parseJalaliMonth(val)
  }
  if (props.format === 'iso') {
    return gregorianIsoToJalaliParts(val)
  }
  return parseJalaliDay(val)
}

function emitValue(parts) {
  let out = ''
  if (parts) {
    if (props.mode === 'month' || props.format === 'month') {
      out = formatJalaliMonthParts(parts)
    } else if (props.format === 'iso') {
      out = jalaliPartsToIso(parts)
    } else {
      out = formatJalaliDayParts(parts)
    }
  }
  emit('update:modelValue', out)
  emit('change', out)
}

function toggle() {
  if (props.disabled) return
  open.value = !open.value
  if (open.value) {
    yearOpen.value = false
    monthOpen.value = props.mode === 'month'
    nextTick(positionPop)
  }
}

function toggleYearList() {
  yearOpen.value = !yearOpen.value
  if (yearOpen.value) monthOpen.value = false
}

function toggleMonthList() {
  if (props.mode === 'month') {
    monthOpen.value = true
    yearOpen.value = false
    return
  }
  monthOpen.value = !monthOpen.value
  if (monthOpen.value) yearOpen.value = false
}

function positionPop() {
  const el = rootEl.value
  if (!el) return
  const trigger = el.querySelector('.pdp-trigger')
  const rect = (trigger || el).getBoundingClientRect()
  const width = 288
  let left = rect.right - width
  if (left < 8) left = 8
  if (left + width > window.innerWidth - 8) left = window.innerWidth - width - 8
  let top = rect.bottom + 6
  if (top + 340 > window.innerHeight) top = Math.max(8, rect.top - 340)
  popStyle.top = `${top + window.scrollY}px`
  popStyle.left = `${left + window.scrollX}px`
}

function shiftMonth(delta) {
  let jm = view.jm + delta
  let jy = view.jy
  if (jm > 12) {
    jm = 1
    jy += 1
  } else if (jm < 1) {
    jm = 12
    jy -= 1
  }
  view.jm = jm
  view.jy = jy
}

function pickYear(y) {
  view.jy = y
  yearOpen.value = false
  if (props.mode === 'month') {
    monthOpen.value = true
  }
}

function pickMonth(jm) {
  view.jm = jm
  monthOpen.value = false
  if (props.mode === 'month' || props.format === 'month') {
    emitValue({ jy: view.jy, jm, jd: 1 })
    open.value = false
  }
}

function pickDay(cell) {
  emitValue({ jy: cell.jy, jm: cell.jm, jd: cell.jd })
  open.value = false
}

function pickToday() {
  view.jy = now.jy
  view.jm = now.jm
  yearOpen.value = false
  monthOpen.value = false
  if (props.mode === 'month' || props.format === 'month') {
    emitValue({ jy: now.jy, jm: now.jm, jd: 1 })
  } else {
    emitValue({ ...now })
  }
  open.value = false
}

function clearValue() {
  emitValue(null)
  open.value = false
}

function isSelected(cell) {
  const s = selected.value
  return s && s.jy === cell.jy && s.jm === cell.jm && s.jd === cell.jd
}

function isToday(cell) {
  return cell.jy === now.jy && cell.jm === now.jm && cell.jd === now.jd
}

function onDocClick(e) {
  if (!open.value) return
  const t = e.target
  if (rootEl.value?.contains(t)) return
  if (t?.closest?.('.pdp-pop')) return
  open.value = false
}

function onKey(e) {
  if (e.key === 'Escape') open.value = false
}

onMounted(() => {
  document.addEventListener('mousedown', onDocClick)
  document.addEventListener('keydown', onKey)
  window.addEventListener('resize', positionPop)
})

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', onDocClick)
  document.removeEventListener('keydown', onKey)
  window.removeEventListener('resize', positionPop)
})
</script>

<style scoped>
.pdp-trigger {
  @apply flex h-11 w-full items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-sm text-ink outline-none transition hover:border-orange-300 focus:border-orange-400 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-500 dark:bg-slate-800 dark:text-slate-100;
}
.pdp-pop {
  position: absolute;
  z-index: 80;
  width: 288px;
  border-radius: 14px;
  border: 1px solid #1e293b;
  background: #fff;
  box-shadow: 0 12px 40px rgba(15, 23, 42, 0.18);
  overflow: hidden;
}
:global(.dark) .pdp-pop {
  background: #0f172a;
  border-color: #334155;
}
.pdp-head {
  @apply flex items-center gap-1 px-2 py-2;
}
.pdp-nav {
  @apply flex h-8 w-8 items-center justify-center rounded-full bg-orange-500 text-white shadow-sm transition hover:bg-orange-600;
}
.pdp-meta-btn {
  @apply inline-flex items-center gap-1 rounded-lg px-2 py-1 text-sm text-slate-800 hover:bg-slate-100 dark:text-slate-100 dark:hover:bg-slate-700;
}
.pdp-meta-btn.is-open {
  @apply bg-orange-50 text-orange-700 dark:bg-orange-500/20 dark:text-orange-300;
}
.pdp-weekdays {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  background: #475569;
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  text-align: center;
}
.pdp-weekdays span {
  padding: 6px 0;
}
.pdp-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 0;
  padding: 4px;
}
.pdp-day {
  height: 34px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  color: #334155;
  transition:
    background 0.15s,
    color 0.15s;
}
.pdp-day:not(.is-empty):hover {
  background: #fff7ed;
}
.pdp-day.is-empty {
  visibility: hidden;
}
.pdp-day.is-friday {
  background: #fce7f3;
  color: #15803d;
}
.pdp-day.is-friday:hover {
  background: #fbcfe8;
}
.pdp-day.is-selected {
  background: #f97316 !important;
  color: #fff !important;
}
.pdp-day.is-today:not(.is-selected) {
  box-shadow: inset 0 0 0 1.5px #f97316;
}
.pdp-list {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 4px;
  max-height: 240px;
  overflow: auto;
  padding: 8px;
}
.pdp-grid-table {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 4px;
  max-height: 260px;
  overflow: auto;
  padding: 8px;
}
.pdp-grid-table--months {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}
.pdp-grid-opt {
  @apply rounded-lg px-2 py-2 text-center text-xs font-semibold text-slate-700 hover:bg-orange-50 dark:text-slate-100 dark:hover:bg-slate-700 sm:text-sm;
}
.pdp-grid-opt.is-active {
  @apply bg-orange-500 font-bold text-white hover:bg-orange-500;
}
.pdp-list-opt {
  @apply w-full rounded-lg px-2 py-2 text-center text-xs font-medium text-slate-700 hover:bg-orange-50 dark:text-slate-200 dark:hover:bg-slate-700 sm:text-sm;
}
.pdp-list-opt.is-active {
  @apply bg-orange-500 font-bold text-white hover:bg-orange-500;
}
.pdp-foot {
  @apply flex items-center justify-between border-t border-slate-100 px-3 py-2 dark:border-slate-700;
}
.pdp-link {
  @apply text-xs font-medium text-orange-600 hover:underline;
}
</style>
