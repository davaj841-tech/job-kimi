<template>
  <div class="overflow-hidden rounded-xl bg-white shadow-sm">
    <div v-if="loading" class="p-10 text-center text-sm text-slate-500">در حال بارگذاری...</div>
    <div v-else class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-gray-600">
          <tr>
            <th
              v-for="col in columns"
              :key="col.key"
              class="px-4 py-3 text-right font-medium"
            >
              {{ col.label }}
            </th>
            <th v-if="actions" class="px-4 py-3 text-right font-medium">عملیات</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(row, index) in rows"
            :key="row.id || index"
            class="border-t border-slate-100 hover:bg-gray-50 odd:bg-slate-50/60"
          >
            <td
              v-for="col in columns"
              :key="col.key"
              class="px-4 py-3 text-right text-slate-700"
            >
              <slot :name="`cell-${col.key}`" :row="row" :index="index">
                {{ row[col.key] }}
              </slot>
            </td>
            <td v-if="actions" class="px-4 py-3 text-right">
              <slot name="actions" :row="row" :index="index" />
            </td>
          </tr>
          <tr v-if="!rows.length">
            <td :colspan="columns.length + (actions ? 1 : 0)" class="px-4 py-10 text-center text-slate-400">
              <slot name="empty">موردی یافت نشد</slot>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
defineProps({
  columns: { type: Array, required: true },
  rows: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  actions: { type: Boolean, default: true },
});
</script>
