<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" >
    <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
      <div class="mb-4 flex items-center justify-between">
        <h3 class="text-lg font-bold">ورود اکسل سوالات</h3>
        <button @click="$emit('close')">✕</button>
      </div>

      <p class="mb-3 text-sm text-slate-500">
        ستون‌ها: exam_slug, question_text, option_a..d, correct_answer, explanation, difficulty, subject
      </p>

      <input type="file" accept=".xlsx,.xls,.csv" class="mb-4 block w-full text-sm" @change="onFile" />

      <div v-if="previewRows.length" class="mb-4 overflow-x-auto rounded-xl border border-slate-100">
        <table class="min-w-full text-xs">
          <thead class="bg-slate-50">
            <tr>
              <th v-for="h in previewHeaders" :key="h" class="px-2 py-2 text-right">{{ h }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, i) in previewRows" :key="i" class="border-t">
              <td v-for="h in previewHeaders" :key="h" class="px-2 py-2">{{ row[h] }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="result" class="mb-4 rounded-xl bg-slate-50 p-3 text-sm">
        <p>موفق: {{ result.success ?? result.imported ?? result.created ?? '—' }}</p>
        <p>خطا: {{ result.failed ?? result.errors_count ?? 0 }}</p>
        <ul v-if="result.errors?.length" class="mt-2 max-h-28 overflow-y-auto text-xs text-red-500">
          <li v-for="(err, i) in result.errors.slice(0, 10)" :key="i">{{ err }}</li>
        </ul>
      </div>

      <p v-if="error" class="mb-3 text-sm text-red-500">{{ error }}</p>

      <div class="flex justify-end gap-2">
        <button class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-bold" @click="$emit('close')">بستن</button>
        <button
          class="rounded-xl bg-orange-500 px-4 py-2 text-sm font-bold text-white disabled:opacity-50"
          :disabled="!file || loading"
          @click="importFile"
        >
          {{ loading ? 'در حال ورود...' : 'شروع ورود' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({ open: Boolean });
const emit = defineEmits(['close', 'imported']);

const file = ref(null);
const loading = ref(false);
const error = ref('');
const result = ref(null);
const previewHeaders = ref([]);
const previewRows = ref([]);

function onFile(e) {
  file.value = e.target.files?.[0] || null;
  result.value = null;
  previewHeaders.value = [];
  previewRows.value = [];
  // CSV quick preview only
  if (file.value?.name?.endsWith('.csv')) {
    const reader = new FileReader();
    reader.onload = () => {
      const lines = String(reader.result || '').split(/\r?\n/).filter(Boolean).slice(0, 6);
      if (!lines.length) return;
      const headers = lines[0].split(',').map((h) => h.trim());
      previewHeaders.value = headers;
      previewRows.value = lines.slice(1, 6).map((line) => {
        const cols = line.split(',');
        const obj = {};
        headers.forEach((h, i) => {
          obj[h] = (cols[i] || '').trim();
        });
        return obj;
      });
    };
    reader.readAsText(file.value);
  }
}

async function importFile() {
  if (!file.value) return;
  loading.value = true;
  error.value = '';
  try {
    emit('imported', file.value);
  } catch (e) {
    error.value = e.response?.data?.message || 'ورود ناموفق بود.';
  } finally {
    loading.value = false;
  }
}

defineExpose({
  setResult(payload) {
    result.value = payload;
  },
  setError(msg) {
    error.value = msg;
  },
});
</script>
