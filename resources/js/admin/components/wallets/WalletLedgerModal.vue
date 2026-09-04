<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
  >
    <div
      class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl"
    >
      <div class="mb-4 flex items-center justify-between">
        <div>
          <h3 class="text-lg font-bold">Ledger کیف پول</h3>
          <p v-if="user" class="mt-1 text-sm text-slate-500">
            {{ user.name }} — {{ user.mobile }} — موجودی: {{ fa(user.balance) }}
          </p>
        </div>
        <button type="button" @click="$emit('close')">✕</button>
      </div>

      <div v-if="loading" class="py-8 text-center text-sm text-slate-400">
        در حال بارگذاری...
      </div>
      <table v-else class="w-full text-sm">
        <thead>
          <tr class="border-b text-right text-slate-500">
            <th class="py-2 font-medium">جهت</th>
            <th class="py-2 font-medium">نوع</th>
            <th class="py-2 font-medium">مبلغ</th>
            <th class="py-2 font-medium">موجودی بعد</th>
            <th class="py-2 font-medium">مرجع</th>
            <th class="py-2 font-medium">تاریخ</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in rows"
            :key="row.id"
            class="border-b border-slate-50"
          >
            <td class="py-2">
              {{ row.direction === 'credit' ? 'واریز' : 'برداشت' }}
            </td>
            <td class="py-2">{{ row.type }}</td>
            <td class="py-2">{{ fa(row.amount) }}</td>
            <td class="py-2">{{ fa(row.balance_after) }}</td>
            <td class="py-2 font-mono text-xs" dir="ltr">
              {{ row.reference }}
            </td>
            <td class="py-2">{{ formatDateTime(row.created_at) }}</td>
          </tr>
          <tr v-if="!rows.length">
            <td colspan="6" class="py-8 text-center text-slate-400">
              ردیفی یافت نشد
            </td>
          </tr>
        </tbody>
      </table>
      <PaginationBar
        v-if="meta.total"
        class="mt-4"
        :meta="meta"
        @page="$emit('page', $event)"
      />
    </div>
  </div>
</template>

<script setup>
import PaginationBar from '../ui/PaginationBar.vue'
import { formatDateTime } from '../../../utils/format'

defineProps({
  open: Boolean,
  user: { type: Object, default: null },
  rows: { type: Array, default: () => [] },
  meta: { type: Object, default: () => ({}) },
  loading: Boolean,
})
defineEmits(['close', 'page'])

function fa(n) {
  return new Intl.NumberFormat('fa-IR').format(Number(n || 0))
}
</script>
