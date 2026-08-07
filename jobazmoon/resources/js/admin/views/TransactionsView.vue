<template>
  <AdminLayout>
    <div class="space-y-5">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-gray-800">تراکنش‌های مالی</h1>
      </div>

      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard title="درآمد امروز" :value="fa(store.stats.revenue_today)" icon="₪" color="#059669" />
        <StatCard title="درآمد هفته" :value="fa(store.stats.revenue_week)" icon="₪" color="#2563eb" />
        <StatCard title="درآمد ماه" :value="fa(store.stats.revenue_month)" icon="₪" color="#0f2744" />
        <StatCard
          title="موفق / ناموفق"
          :value="`${fa(store.stats.success_count)} / ${fa(store.stats.failed_count)}`"
          icon="◎"
          color="#f97316"
        />
      </div>

      <div class="rounded-xl bg-white p-4 shadow-sm">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-5">
          <input v-model="store.filters.date_from" type="date" class="field" />
          <input v-model="store.filters.date_to" type="date" class="field" />
          <select v-model="store.filters.gateway" class="field">
            <option value="">همه درگاه‌ها</option>
            <option value="zarinpal">زرین‌پال</option>
            <option value="wallet">کیف پول</option>
          </select>
          <select v-model="store.filters.type" class="field">
            <option value="">همه انواع</option>
            <option value="deposit">واریز</option>
            <option value="purchase">خرید</option>
            <option value="refund">بازگشت وجه</option>
            <option value="withdrawal">برداشت</option>
          </select>
          <select v-model="store.filters.status" class="field">
            <option value="">همه وضعیت‌ها</option>
            <option value="success">موفق</option>
            <option value="pending">در انتظار</option>
            <option value="failed">ناموفق</option>
          </select>
        </div>
        <div class="mt-3 flex gap-2">
          <button class="btn-orange" @click="apply">اعمال فیلتر</button>
          <button class="btn-muted" @click="clear">پاک کردن</button>
        </div>
      </div>

      <DataTable :columns="columns" :rows="store.transactions" :loading="store.loading" actions>
        <template #cell-index="{ index }">{{ fa(rowNum(index)) }}</template>
        <template #cell-user="{ row }">{{ row.user_name || '—' }}</template>
        <template #cell-amount="{ row }">{{ fa(row.amount) }}</template>
        <template #cell-type="{ row }">
          <span class="rounded-full px-2 py-0.5 text-xs font-bold" :class="typeClass(row.type)">{{ typeLabel(row.type) }}</span>
        </template>
        <template #cell-gateway="{ row }">{{ gatewayLabel(row.gateway) }}</template>
        <template #cell-status="{ row }">
          <span class="rounded-full px-2 py-0.5 text-xs font-bold" :class="statusClass(row.status)">{{ statusLabel(row.status) }}</span>
        </template>
        <template #cell-created_at="{ row }">{{ formatDateTime(row.created_at) }}</template>
        <template #actions="{ row }">
          <button class="act" @click="openDetail(row)">جزئیات</button>
        </template>
      </DataTable>
      <PaginationBar :meta="store.meta" @page="go" />
    </div>

    <TransactionDetailModal :open="detailOpen" :tx="selected" @close="detailOpen = false" />
  </AdminLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import AdminLayout from '../components/layout/AdminLayout.vue';
import DataTable from '../components/ui/DataTable.vue';
import PaginationBar from '../components/ui/PaginationBar.vue';
import StatCard from '../components/ui/StatCard.vue';
import TransactionDetailModal from '../components/transactions/TransactionDetailModal.vue';
import { formatDateTime, apiErrorMessage } from '../../utils/format';
import { useToast } from '../../composables/useToast';
import { useTransactionsStore } from '../stores/transactions';

const store = useTransactionsStore();
const toast = useToast();
const detailOpen = ref(false);
const selected = ref(null);

const columns = [
  { key: 'index', label: '#' },
  { key: 'user', label: 'کاربر' },
  { key: 'amount', label: 'مبلغ' },
  { key: 'type', label: 'نوع' },
  { key: 'gateway', label: 'درگاه' },
  { key: 'status', label: 'وضعیت' },
  { key: 'reference_id', label: 'پیگیری' },
  { key: 'created_at', label: 'تاریخ' },
];

function fa(n) {
  return new Intl.NumberFormat('fa-IR').format(Number(n || 0));
}
function rowNum(i) {
  return (store.meta.from || ((store.meta.current_page - 1) * (store.meta.per_page || 20) + 1) || 1) + i;
}
function typeLabel(t) {
  return { deposit: 'واریز', purchase: 'خرید', refund: 'بازگشت وجه', withdrawal: 'برداشت' }[t] || t;
}
function typeClass(t) {
  return {
    deposit: 'bg-blue-100 text-blue-800',
    purchase: 'bg-emerald-100 text-emerald-800',
    refund: 'bg-orange-100 text-orange-800',
    withdrawal: 'bg-slate-100 text-slate-700',
  }[t] || 'bg-slate-100';
}
function gatewayLabel(g) {
  return { zarinpal: 'زرین‌پال', wallet: 'کیف پول' }[g] || g;
}
function statusLabel(s) {
  return { success: 'موفق', pending: 'در انتظار', failed: 'ناموفق' }[s] || s;
}
function statusClass(s) {
  return {
    success: 'bg-emerald-100 text-emerald-800',
    pending: 'bg-yellow-100 text-yellow-800',
    failed: 'bg-red-100 text-red-700',
  }[s] || 'bg-slate-100';
}

onMounted(async () => {
  try {
    await Promise.all([store.fetchStats(), store.fetchTransactions()]);
  } catch (e) {
    toast.error(apiErrorMessage(e));
  }
});

function apply() {
  store.fetchTransactions(1);
}
function clear() {
  store.filters = { date_from: '', date_to: '', gateway: '', type: '', status: '' };
  store.fetchTransactions(1);
}
function go(p) {
  store.fetchTransactions(p);
}
async function openDetail(row) {
  try {
    selected.value = await store.fetchTransaction(row.id);
    detailOpen.value = true;
  } catch (e) {
    selected.value = row;
    detailOpen.value = true;
  }
}
</script>

<style scoped>
.field { @apply h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-orange-400; }
.btn-muted { @apply rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold text-slate-700; }
.btn-orange { @apply rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-bold text-white; }
.act { @apply rounded-lg px-2 py-1 text-xs font-bold text-slate-600 hover:bg-slate-100; }
</style>
