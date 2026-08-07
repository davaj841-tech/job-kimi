<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" >
    <div class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
      <div class="mb-4 flex items-center justify-between">
        <div>
          <h3 class="text-lg font-bold">تاریخچه کیف پول</h3>
          <p v-if="user" class="mt-1 text-sm text-slate-500">{{ user.name }} — {{ user.mobile }} — موجودی: {{ fa(user.balance) }}</p>
        </div>
        <button type="button" @click="$emit('close')">✕</button>
      </div>

      <div class="mb-3 flex gap-2">
        <select :value="typeFilter" class="field max-w-xs" @change="$emit('filter', $event.target.value)">
          <option value="">همه انواع</option>
          <option value="deposit">واریز</option>
          <option value="withdrawal">برداشت</option>
          <option value="purchase">خرید</option>
          <option value="refund">بازگشت</option>
        </select>
      </div>

      <div v-if="loading" class="py-8 text-center text-sm text-slate-400">در حال بارگذاری...</div>
      <table v-else class="w-full text-sm">
        <thead>
          <tr class="border-b text-right text-slate-500">
            <th class="py-2 font-medium">نوع</th>
            <th class="py-2 font-medium">مبلغ</th>
            <th class="py-2 font-medium">وضعیت</th>
            <th class="py-2 font-medium">تاریخ</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id" class="border-b border-slate-50">
            <td class="py-2">{{ typeLabel(row.type) }}</td>
            <td class="py-2">{{ fa(row.amount) }}</td>
            <td class="py-2">{{ statusLabel(row.status) }}</td>
            <td class="py-2">{{ formatDateTime(row.created_at) }}</td>
          </tr>
          <tr v-if="!rows.length">
            <td colspan="4" class="py-8 text-center text-slate-400">تراکنشی یافت نشد</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { formatDateTime } from '../../../utils/format';

defineProps({
  open: Boolean,
  user: { type: Object, default: null },
  rows: { type: Array, default: () => [] },
  loading: Boolean,
  typeFilter: { type: String, default: '' },
});
defineEmits(['close', 'filter']);

function fa(n) {
  return new Intl.NumberFormat('fa-IR').format(Number(n || 0));
}
function typeLabel(t) {
  return { deposit: 'واریز', purchase: 'خرید', refund: 'بازگشت', withdrawal: 'برداشت' }[t] || t;
}
function statusLabel(s) {
  return { success: 'موفق', pending: 'در انتظار', failed: 'ناموفق' }[s] || s;
}
</script>

<style scoped>
.field { @apply h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-orange-400; }
</style>
