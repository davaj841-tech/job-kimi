<template>
  <div class="overflow-hidden rounded-xl border border-slate-200">
    <div class="flex flex-wrap gap-1 border-b border-slate-200 bg-slate-50 p-2">
      <button
        v-for="btn in buttons"
        :key="btn.label"
        type="button"
        class="rounded-lg px-2 py-1 text-xs font-bold text-slate-700 hover:bg-white"
        :title="btn.title"
        @click.prevent="run(btn)"
      >
        {{ btn.label }}
      </button>
    </div>
    <div
      ref="editor"
      class="prose prose-sm max-w-none min-h-48 px-3 py-2 text-sm leading-7 outline-none [&_table]:w-full [&_table]:border-collapse [&_td]:border [&_td]:border-slate-300 [&_td]:p-2 [&_th]:border [&_th]:border-slate-300 [&_th]:bg-slate-50 [&_th]:p-2"
      contenteditable="true"
      @input="onInput"
    />
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';

const props = defineProps({
  modelValue: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue']);

const editor = ref(null);
let syncing = false;

const buttons = [
  { label: 'پررنگ', cmd: 'bold', title: 'پررنگ' },
  { label: 'ایتالیک', cmd: 'italic', title: 'ایتالیک' },
  { label: 'زیرخط', cmd: 'underline', title: 'زیرخط' },
  { label: 'H2', cmd: 'formatBlock', value: 'h2', title: 'عنوان' },
  { label: 'H3', cmd: 'formatBlock', value: 'h3', title: 'زیرعنوان' },
  { label: '• لیست', cmd: 'insertUnorderedList', title: 'لیست نقطه‌ای' },
  { label: '۱. لیست', cmd: 'insertOrderedList', title: 'لیست شماره‌دار' },
  { label: 'راست‌چین', cmd: 'justifyRight', title: 'راست‌چین' },
  { label: 'وسط', cmd: 'justifyCenter', title: 'وسط‌چین' },
  { label: 'لینک', action: 'link', title: 'درج لینک' },
  { label: 'جدول', action: 'table', title: 'درج جدول' },
  { label: 'پاک‌سازی', cmd: 'removeFormat', title: 'حذف قالب' },
];

onMounted(() => {
  if (editor.value) editor.value.innerHTML = props.modelValue || '';
});

watch(
  () => props.modelValue,
  (val) => {
    if (!editor.value || syncing) return;
    if (editor.value.innerHTML !== (val || '')) {
      editor.value.innerHTML = val || '';
    }
  }
);

function onInput() {
  syncing = true;
  emit('update:modelValue', editor.value?.innerHTML || '');
  queueMicrotask(() => {
    syncing = false;
  });
}

function run(btn) {
  editor.value?.focus();
  if (btn.action === 'link') {
    const url = window.prompt('آدرس لینک:');
    if (url) document.execCommand('createLink', false, url);
  } else if (btn.action === 'table') {
    insertTable();
  } else {
    document.execCommand(btn.cmd, false, btn.value || null);
  }
  onInput();
}

function insertTable() {
  const rows = Number(window.prompt('تعداد ردیف؟', '3') || 0);
  const cols = Number(window.prompt('تعداد ستون؟', '3') || 0);
  if (!rows || !cols || rows < 1 || cols < 1 || rows > 20 || cols > 10) return;

  let html = '<table><tbody>';
  for (let r = 0; r < rows; r += 1) {
    html += '<tr>';
    for (let c = 0; c < cols; c += 1) {
      if (r === 0) html += '<th>عنوان</th>';
      else html += '<td>&nbsp;</td>';
    }
    html += '</tr>';
  }
  html += '</tbody></table><p><br></p>';
  document.execCommand('insertHTML', false, html);
}
</script>
