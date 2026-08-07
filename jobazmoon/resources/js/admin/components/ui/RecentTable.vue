<template>
  <div class="rounded-2xl bg-white shadow-sm">
    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
      <h3 class="text-base font-bold text-slate-800">{{ title }}</h3>
      <RouterLink v-if="link" :to="link" class="text-xs font-bold text-orange-500 hover:underline">
        مشاهده همه
      </RouterLink>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-slate-500">
          <tr>
            <th
              v-for="col in columns"
              :key="col"
              class="px-4 py-3 text-right font-medium"
            >
              {{ col }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, idx) in normalizedRows" :key="idx" class="border-t border-slate-100">
            <td
              v-for="(cell, cIdx) in row"
              :key="cIdx"
              class="px-4 py-3 text-slate-700"
            >
              {{ cell }}
            </td>
          </tr>
          <tr v-if="!normalizedRows.length">
            <td :colspan="columns.length" class="px-4 py-8 text-center text-slate-400">
              موردی یافت نشد
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  title: { type: String, required: true },
  columns: { type: Array, required: true },
  rows: { type: Array, default: () => [] },
  link: { type: String, default: '' },
});

const normalizedRows = computed(() =>
  (props.rows || []).map((row) => (Array.isArray(row) ? row : Object.values(row)))
);
</script>
