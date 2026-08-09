<template>
  <AdminLayout>
    <div class="space-y-5">
      <h1 class="text-2xl font-bold">گزارش حسابرسی</h1>

      <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-5">
        <input v-model="filters.user_id" class="field" placeholder="شناسه کاربر" />
        <input v-model="filters.action" class="field" placeholder="عملیات" />
        <input v-model="filters.entity_type" class="field" placeholder="موجودیت" />
        <input v-model="filters.date_from" type="date" class="field" />
        <input v-model="filters.date_to" type="date" class="field" />
      </div>
      <button class="btn-orange" @click="load(1)">اعمال فیلتر</button>

      <DataTable :columns="columns" :rows="rows" :loading="loading" actions>
        <template #cell-created_at="{ row }">{{ formatDateTime(row.created_at) }}</template>
        <template #cell-user="{ row }">{{ row.user?.name || row.user?.mobile || row.user_id || '—' }}</template>
        <template #cell-entity="{ row }">{{ shortEntity(row.entity_type) }} #{{ row.entity_id || '—' }}</template>
        <template #actions="{ row }">
          <button class="act" @click="open(row)">جزئیات</button>
        </template>
      </DataTable>
      <PaginationBar :meta="meta" @page="load" />
    </div>

    <div v-if="selected" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-5">
        <h3 class="mb-3 font-bold">جزئیات تغییر</h3>
        <p class="mb-2 text-sm"><span class="text-slate-500">عملیات:</span> {{ selected.action }}</p>
        <div class="grid gap-3 md:grid-cols-2">
          <div>
            <p class="mb-1 text-xs font-bold text-slate-500">مقادیر قبلی</p>
            <pre class="overflow-auto rounded-xl bg-slate-50 p-3 text-xs" dir="ltr">{{ pretty(selected.old_values) }}</pre>
          </div>
          <div>
            <p class="mb-1 text-xs font-bold text-slate-500">مقادیر جدید</p>
            <pre class="overflow-auto rounded-xl bg-slate-50 p-3 text-xs" dir="ltr">{{ pretty(selected.new_values) }}</pre>
          </div>
        </div>
        <div class="mt-4 text-left">
          <button class="btn-muted" @click="selected = null">بستن</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import adminApi from '../api/client';
import AdminLayout from '../components/layout/AdminLayout.vue';
import DataTable from '../components/ui/DataTable.vue';
import PaginationBar from '../components/ui/PaginationBar.vue';
import { formatDateTime, unwrapList, unwrapMeta } from '../../utils/format';

const rows = ref([]);
const meta = ref(null);
const loading = ref(false);
const selected = ref(null);
const filters = reactive({ user_id: '', action: '', entity_type: '', date_from: '', date_to: '' });
const columns = [
  { key: 'created_at', label: 'زمان' },
  { key: 'user', label: 'کاربر' },
  { key: 'ip_address', label: 'IP' },
  { key: 'action', label: 'عملیات' },
  { key: 'entity', label: 'موجودیت' },
];

onMounted(() => load(1));

async function load(page = 1) {
  loading.value = true;
  try {
    const { data } = await adminApi.get('/admin/audit-logs', {
      params: { ...filters, page, per_page: 20 },
    });
    rows.value = unwrapList(data);
    meta.value = unwrapMeta(data);
  } finally {
    loading.value = false;
  }
}

function open(row) {
  selected.value = row;
}
function pretty(v) {
  return JSON.stringify(v ?? {}, null, 2);
}
function shortEntity(t) {
  if (!t) return '—';
  return String(t).split('\\').pop();
}
</script>

<style scoped>
.field { @apply h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none; }
.btn-orange { @apply rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-bold text-white; }
.btn-muted { @apply rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold; }
.act { @apply rounded-lg px-2 py-1 text-xs font-bold text-slate-600 hover:bg-slate-100; }
</style>
