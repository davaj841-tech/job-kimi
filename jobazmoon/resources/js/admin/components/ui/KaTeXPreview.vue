<template>
  <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm leading-7 text-slate-800" dir="rtl">
    <div v-if="error" class="text-xs text-red-500">{{ error }}</div>
    <div v-else-if="!html" class="text-xs text-slate-400">پیش‌نمایش خالی</div>
    <div v-else class="katex-preview overflow-x-auto" v-html="html" />
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import katex from 'katex';
import 'katex/dist/katex.min.css';

const props = defineProps({
  latex: { type: String, default: '' },
  displayMode: { type: Boolean, default: false },
});

const html = ref('');
const error = ref('');
let timer;

function render() {
  const raw = props.latex || '';
  if (!raw.trim()) {
    html.value = '';
    error.value = '';
    return;
  }

  try {
    // Support plain text + $...$ / $$...$$ fragments
    const withMath = raw.replace(/\$\$([\s\S]+?)\$\$/g, (_, expr) => {
      return katex.renderToString(expr.trim(), { throwOnError: false, displayMode: true });
    }).replace(/\$([^$\n]+?)\$/g, (_, expr) => {
      return katex.renderToString(expr.trim(), { throwOnError: false, displayMode: false });
    });

    // If no $ delimiters, try whole string as latex when it looks like formula
    if (withMath === raw && /\\|[{}^_]/.test(raw)) {
      html.value = katex.renderToString(raw, {
        throwOnError: false,
        displayMode: props.displayMode,
      });
    } else {
      html.value = withMath.replace(/\n/g, '<br>');
    }
    error.value = '';
  } catch (e) {
    error.value = e.message || 'خطا در رندر فرمول';
    html.value = '';
  }
}

watch(
  () => props.latex,
  () => {
    clearTimeout(timer);
    timer = setTimeout(render, 500);
  },
  { immediate: true }
);
</script>
