<template>
  <AdminLayout>
    <div class="space-y-5">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-gray-800">مدیریت اشتراک‌ها</h1>
        <div class="flex gap-2">
          <button class="btn-muted" :class="tab === 'subscribers' ? 'ring-2 ring-orange-400' : ''" @click="tab = 'subscribers'; loadSubs()">مشترکین</button>
          <button class="btn-muted" :class="tab === 'plans' ? 'ring-2 ring-orange-400' : ''" @click="tab = 'plans'">پلن‌ها</button>
          <button class="btn-dark" @click="openCreate">پلن جدید</button>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard title="اشتراک‌های فعال" :value="fa(store.stats.active_subscriptions)" icon="◎" color="#0f2744" />
        <StatCard title="درآمد ماهانه اشتراک" :value="fa(store.stats.monthly_revenue)" icon="₪" color="#059669" />
        <StatCard title="تمدیدهای امروز" :value="fa(store.stats.renewals_today)" icon="↻" color="#2563eb" />
        <StatCard title="انقضای نزدیک (۳ روز)" :value="fa(store.stats.expiring_soon)" icon="!" color="#dc2626" />
      </div>

      <template v-if="tab === 'plans'">
        <DataTable :columns="planCols" :rows="store.plans" :loading="store.loading" actions>
          <template #cell-index="{ index }">{{ fa(index + 1) }}</template>
          <template #cell-duration_days="{ row }">{{ fa(row.duration_days) }} روز</template>
          <template #cell-price="{ row }">{{ fa(row.price) }}</template>
          <template #cell-features="{ row }">
            <span class="line-clamp-2 text-xs text-slate-500">{{ (row.features || []).join('، ') || '—' }}</span>
          </template>
          <template #cell-is_active="{ row }">
            <span class="rounded-full px-2 py-0.5 text-xs font-bold" :class="row.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'">
              {{ row.is_active ? 'فعال' : 'غیرفعال' }}
            </span>
          </template>
          <template #actions="{ row }">
            <div class="flex justify-end gap-1">
              <button class="act" @click="openEdit(row)">ویرایش</button>
              <button class="act text-red-600" @click="askDelete(row)">حذف</button>
            </div>
          </template>
        </DataTable>
      </template>

      <template v-else>
        <div class="rounded-xl bg-white p-4 shadow-sm">
          <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
            <select v-model="store.filters.status" class="field">
              <option value="">همه وضعیت‌ها</option>
              <option value="active">فعال</option>
              <option value="expired">منقضی</option>
            </select>
            <select v-model="store.filters.plan_id" class="field">
              <option value="">همه پلن‌ها</option>
              <option v-for="p in store.plans" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <input v-model="store.filters.search" class="field" placeholder="جستجو نام/موبایل" @keyup.enter="loadSubs" />
          </div>
          <div class="mt-3 flex gap-2">
            <button class="btn-orange" @click="loadSubs">اعمال فیلتر</button>
          </div>
        </div>

        <DataTable :columns="subCols" :rows="store.subscribers" :loading="store.loading" actions>
          <template #cell-index="{ index }">{{ fa(rowNum(index)) }}</template>
          <template #cell-user="{ row }">
            <div>
              <p class="font-medium">{{ row.name || '—' }}</p>
              <p class="text-xs text-slate-400" dir="ltr">{{ row.mobile }}</p>
            </div>
          </template>
          <template #cell-plan="{ row }">{{ row.plan?.name || '—' }}</template>
          <template #cell-started_at="{ row }">{{ formatDate(row.started_at) }}</template>
          <template #cell-expires_at="{ row }">{{ formatDate(row.expires_at) }}</template>
          <template #cell-status="{ row }">
            <span class="rounded-full px-2 py-0.5 text-xs font-bold" :class="row.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-700'">
              {{ row.status === 'active' ? 'فعال' : 'منقضی' }}
            </span>
          </template>
          <template #actions="{ row }">
            <div class="flex flex-wrap justify-end gap-1">
              <button class="act" @click="renew(row)">تمدید</button>
              <button class="act text-red-600" @click="askCancel(row)">لغو</button>
              <RouterLink class="act" :to="`/admin/users`">کاربر</RouterLink>
            </div>
          </template>
        </DataTable>
        <PaginationBar :meta="store.meta" @page="(p) => store.fetchSubscribers(p)" />
      </template>
    </div>

    <PlanModal :open="modalOpen" :plan="editing" @close="modalOpen = false" @saved="onSaved" />
    <ConfirmDialog :open="confirm.open" :title="confirm.title" :message="confirm.message" @cancel="confirm.open = false" @confirm="runConfirm" />
  </AdminLayout>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import AdminLayout from '../components/layout/AdminLayout.vue';
import ConfirmDialog from '../components/ui/ConfirmDialog.vue';
import DataTable from '../components/ui/DataTable.vue';
import PaginationBar from '../components/ui/PaginationBar.vue';
import StatCard from '../components/ui/StatCard.vue';
import PlanModal from '../components/subscriptions/PlanModal.vue';
import { formatDate, apiErrorMessage } from '../../utils/format';
import { useToast } from '../../composables/useToast';
import { useSubscriptionsStore } from '../stores/subscriptions';

const store = useSubscriptionsStore();
const toast = useToast();
const tab = ref('plans');
const modalOpen = ref(false);
const editing = ref(null);
const confirm = reactive({ open: false, title: '', message: '', action: null });

const planCols = [
  { key: 'index', label: '#' },
  { key: 'name', label: 'نام پلن' },
  { key: 'duration_days', label: 'مدت' },
  { key: 'price', label: 'قیمت' },
  { key: 'features', label: 'ویژگی‌ها' },
  { key: 'is_active', label: 'وضعیت' },
];
const subCols = [
  { key: 'index', label: '#' },
  { key: 'user', label: 'کاربر' },
  { key: 'plan', label: 'پلن' },
  { key: 'started_at', label: 'شروع' },
  { key: 'expires_at', label: 'پایان' },
  { key: 'status', label: 'وضعیت' },
];

function fa(n) {
  return new Intl.NumberFormat('fa-IR').format(Number(n || 0));
}
function rowNum(i) {
  return (store.meta.from || ((store.meta.current_page - 1) * (store.meta.per_page || 20) + 1) || 1) + i;
}

onMounted(async () => {
  await Promise.all([store.fetchStats(), store.fetchPlans()]);
});

function openCreate() {
  editing.value = null;
  modalOpen.value = true;
}
function openEdit(row) {
  editing.value = row;
  modalOpen.value = true;
}
async function loadSubs() {
  await store.fetchSubscribers(1);
}
async function onSaved({ id, payload }) {
  try {
    if (id) await store.updatePlan(id, payload);
    else await store.createPlan(payload);
    toast.success('پلن ذخیره شد');
    modalOpen.value = false;
  } catch (e) {
    toast.error(apiErrorMessage(e));
  }
}
function askDelete(row) {
  confirm.open = true;
  confirm.title = 'حذف پلن';
  confirm.message = `پلن «${row.name}» حذف شود؟`;
  confirm.action = async () => {
    await store.deletePlan(row.id);
    toast.success('حذف شد');
  };
}
function askCancel(row) {
  confirm.open = true;
  confirm.title = 'لغو اشتراک';
  confirm.message = `اشتراک ${row.name || row.mobile} لغو شود؟`;
  confirm.action = async () => {
    await store.cancelSubscriber(row.id);
    toast.success('اشتراک لغو شد');
  };
}
async function renew(row) {
  try {
    await store.renewSubscriber(row.id);
    toast.success('تمدید شد');
  } catch (e) {
    toast.error(apiErrorMessage(e));
  }
}
async function runConfirm() {
  try {
    await confirm.action?.();
  } catch (e) {
    toast.error(apiErrorMessage(e));
  } finally {
    confirm.open = false;
  }
}
</script>

<style scoped>
.field { @apply h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-orange-400; }
.btn-dark { @apply rounded-xl bg-[#0f2744] px-4 py-2.5 text-sm font-bold text-white; }
.btn-muted { @apply rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold text-slate-700; }
.btn-orange { @apply rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-bold text-white; }
.act { @apply rounded-lg px-2 py-1 text-xs font-bold text-slate-600 hover:bg-slate-100; }
</style>
