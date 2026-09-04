<template>
  <div
    class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-600"
  >
    <div
      class="flex flex-wrap gap-1 border-b border-slate-200 bg-slate-50 p-2 dark:border-slate-600 dark:bg-slate-800"
    >
      <button
        v-for="btn in buttons"
        :key="btn.label"
        type="button"
        class="rounded-lg px-2 py-1 text-xs font-bold text-slate-700 hover:bg-white dark:text-slate-200 dark:hover:bg-slate-700"
        :title="btn.title"
        @mousedown.prevent="run(btn)"
      >
        {{ btn.label }}
      </button>
      <button
        type="button"
        class="mr-auto rounded-lg px-2 py-1 text-xs font-bold text-orange-600 hover:bg-white dark:text-orange-400 dark:hover:bg-slate-700"
        @mousedown.prevent="htmlMode = !htmlMode"
      >
        {{ htmlMode ? 'نمایش دیداری' : 'HTML' }}
      </button>
    </div>
    <textarea
      v-if="htmlMode"
      class="w-full bg-white p-3 text-sm text-slate-800 outline-none dark:bg-slate-900 dark:text-slate-100"
      :class="sizeClass"
      dir="ltr"
      :value="modelValue"
      @input="$emit('update:modelValue', $event.target.value)"
    />
    <div
      v-else
      ref="editor"
      class="prose prose-sm max-w-none bg-white px-3 py-2 text-sm leading-7 text-slate-800 outline-none dark:bg-slate-900 dark:text-slate-100 [&_.math]:font-mono [&_.math]:text-orange-700 dark:[&_.math]:text-orange-400 [&_a]:text-orange-600 [&_h2]:text-lg [&_h2]:font-black [&_h3]:text-base [&_h3]:font-bold [&_img]:max-w-full [&_table]:w-full [&_table]:border-collapse [&_td]:border [&_td]:border-slate-300 [&_td]:p-2 dark:[&_td]:border-slate-600 [&_th]:border [&_th]:border-slate-300 [&_th]:bg-slate-50 [&_th]:p-2 dark:[&_th]:border-slate-600 dark:[&_th]:bg-slate-800"
      :class="sizeClass"
      contenteditable="true"
      @input="onInput"
    />
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
  /** default | page | exam | compact */
  size: { type: String, default: 'default' },
})
const emit = defineEmits(['update:modelValue'])

const editor = ref(null)
const htmlMode = ref(false)
let syncing = false

const sizeClass = computed(() => {
  if (props.size === 'page') return 'min-h-[min(62vh,640px)]'
  if (props.size === 'exam') return 'min-h-[min(42vh,420px)]'
  if (props.size === 'compact') return 'min-h-28'
  return 'min-h-48'
})

const buttons = [
  { label: 'برگردان', cmd: 'undo', title: 'واگرد' },
  { label: 'ازنو', cmd: 'redo', title: 'ازنو' },
  { label: 'پررنگ', cmd: 'bold', title: 'پررنگ' },
  { label: 'ایتالیک', cmd: 'italic', title: 'ایتالیک' },
  { label: 'زیرخط', cmd: 'underline', title: 'زیرخط' },
  { label: 'خط‌خورده', cmd: 'strikeThrough', title: 'خط‌خورده' },
  { label: 'زیروند', cmd: 'subscript', title: 'زیروند' },
  { label: 'بالاوند', cmd: 'superscript', title: 'بالاوند' },
  { label: 'H2', cmd: 'formatBlock', value: 'h2', title: 'عنوان' },
  { label: 'H3', cmd: 'formatBlock', value: 'h3', title: 'زیرعنوان' },
  { label: 'پاراگراف', cmd: 'formatBlock', value: 'p', title: 'پاراگراف' },
  {
    label: 'نقل‌قول',
    cmd: 'formatBlock',
    value: 'blockquote',
    title: 'نقل‌قول',
  },
  { label: 'کد', cmd: 'formatBlock', value: 'pre', title: 'بلوک کد' },
  { label: '• لیست', cmd: 'insertUnorderedList', title: 'لیست نقطه‌ای' },
  { label: '۱. لیست', cmd: 'insertOrderedList', title: 'لیست شماره‌دار' },
  { label: 'تورفتگی+', cmd: 'indent', title: 'افزایش تورفتگی' },
  { label: 'تورفتگی−', cmd: 'outdent', title: 'کاهش تورفتگی' },
  { label: 'راست‌چین', cmd: 'justifyRight', title: 'راست‌چین' },
  { label: 'وسط', cmd: 'justifyCenter', title: 'وسط‌چین' },
  { label: 'چپ‌چین', cmd: 'justifyLeft', title: 'چپ‌چین' },
  { label: 'کشیده', cmd: 'justifyFull', title: 'تراز دوطرفه' },
  { label: 'خط افقی', action: 'hr', title: 'درج خط افقی' },
  { label: 'لینک', action: 'link', title: 'درج لینک' },
  { label: 'تصویر', action: 'image', title: 'درج تصویر' },
  { label: 'فرمول', action: 'formula', title: 'درج فرمول ریاضی (LaTeX)' },
  { label: 'جدول', action: 'table', title: 'درج جدول' },
  { label: 'ردیف+', action: 'tableRow', title: 'افزودن ردیف به جدول' },
  { label: 'ستون+', action: 'tableCol', title: 'افزودن ستون به جدول' },
  { label: 'پاک‌سازی', cmd: 'removeFormat', title: 'حذف قالب' },
]

onMounted(() => {
  if (editor.value) editor.value.innerHTML = props.modelValue || ''
})

watch(
  () => props.modelValue,
  (val) => {
    if (htmlMode.value || !editor.value || syncing) return
    if (editor.value.innerHTML !== (val || '')) {
      editor.value.innerHTML = val || ''
    }
  }
)

watch(htmlMode, async (on) => {
  if (!on) {
    await nextTick()
    if (editor.value) editor.value.innerHTML = props.modelValue || ''
  }
})

function onInput() {
  syncing = true
  emit('update:modelValue', editor.value?.innerHTML || '')
  queueMicrotask(() => {
    syncing = false
  })
}

function run(btn) {
  editor.value?.focus()
  if (btn.action === 'link') {
    const url = window.prompt('آدرس لینک:')
    if (url) document.execCommand('createLink', false, url)
  } else if (btn.action === 'image') {
    const url = window.prompt('آدرس تصویر:')
    if (url) document.execCommand('insertImage', false, url)
  } else if (btn.action === 'formula') {
    insertFormula()
  } else if (btn.action === 'table') {
    insertTable()
  } else if (btn.action === 'hr') {
    document.execCommand('insertHorizontalRule', false, null)
  } else if (btn.action === 'tableRow') {
    insertTableRow()
  } else if (btn.action === 'tableCol') {
    insertTableCol()
  } else {
    document.execCommand(btn.cmd, false, btn.value || null)
  }
  onInput()
}

function insertFormula() {
  const latex = window.prompt(
    'فرمول LaTeX را وارد کنید:\nمثال: x^2 + y^2 = z^2 یا \\frac{a}{b}'
  )
  if (!latex) return
  const trimmed = latex.trim()
  const html = `<span class="math" data-latex="${escapeAttr(trimmed)}">\\(${trimmed}\\)</span>&nbsp;`
  document.execCommand('insertHTML', false, html)
}

function escapeAttr(s) {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;')
}

function insertTable() {
  const rows = Number(window.prompt('تعداد ردیف؟', '3') || 0)
  const cols = Number(window.prompt('تعداد ستون؟', '3') || 0)
  if (!rows || !cols || rows < 1 || cols < 1 || rows > 20 || cols > 10) return

  let html = '<table><tbody>'
  for (let r = 0; r < rows; r += 1) {
    html += '<tr>'
    for (let c = 0; c < cols; c += 1) {
      if (r === 0) html += '<th>عنوان</th>'
      else html += '<td>&nbsp;</td>'
    }
    html += '</tr>'
  }
  html += '</tbody></table><p><br></p>'
  document.execCommand('insertHTML', false, html)
}

function closestTableCell() {
  const sel = window.getSelection()
  if (!sel || !sel.rangeCount) return null
  let node = sel.anchorNode
  if (node && node.nodeType === Node.TEXT_NODE) node = node.parentElement
  return node?.closest?.('td,th') || null
}

function insertTableRow() {
  const cell = closestTableCell()
  if (!cell) {
    window.alert('ابتدا داخل یک سلول جدول کلیک کنید.')
    return
  }
  const row = cell.parentElement
  const table = cell.closest('table')
  if (!row || !table) return
  const cols = row.children.length
  const tr = document.createElement('tr')
  for (let i = 0; i < cols; i += 1) {
    const td = document.createElement('td')
    td.innerHTML = '&nbsp;'
    tr.appendChild(td)
  }
  row.after(tr)
}

function insertTableCol() {
  const cell = closestTableCell()
  if (!cell) {
    window.alert('ابتدا داخل یک سلول جدول کلیک کنید.')
    return
  }
  const table = cell.closest('table')
  if (!table) return
  const index = Array.from(cell.parentElement.children).indexOf(cell)
  table.querySelectorAll('tr').forEach((tr, rowIdx) => {
    const ref = tr.children[index]
    const el = document.createElement(
      rowIdx === 0 && tr.querySelector('th') ? 'th' : 'td'
    )
    el.innerHTML = rowIdx === 0 ? 'عنوان' : '&nbsp;'
    if (ref) ref.after(el)
    else tr.appendChild(el)
  })
}
</script>
