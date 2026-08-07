<template>
  <AdminLayout>
    <div class="space-y-5">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-gray-800">مدیریت آگهی‌های استخدام</h1>
        <div class="flex gap-2">
          <button class="btn-muted" @click="classOpen = true">طبقه‌بندی‌ها</button>
          <button class="btn-dark" @click="openCreate">آگهی جدید</button>
          <button class="btn-muted" @click="importOpen = true">ورود Excel</button>
        </div>
      </div>

      <div class="rounded-xl bg-white p-4 shadow-sm">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
          <select v-model="store.filters.status" class="field">
            <option value="">همه وضعیت‌ها</option>
            <option value="pending">در انتظار</option>
            <option value="approved">تایید شده</option>
            <option value="rejected">رد شده</option>
          </select>
          <select v-model="store.filters.province" class="field">
            <option value="">همه استان‌ها</option>
            <option v-for="p in store.filterOptions.provinces" :key="p" :value="p">{{ p }}</option>
          </select>
          <input v-model="store.filters.city" class="field" placeholder="شهر" />
          <select v-model="store.filters.job_classification_id" class="field">
            <option value="">همه طبقه‌بندی‌ها</option>
            <option v-for="c in store.classifications" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
          <input v-model="store.filters.deadline_from" type="date" class="field" />
          <input v-model="store.filters.deadline_to" type="date" class="field" />
          <input v-model="store.filters.search" class="field lg:col-span-2" placeholder="جستجو عنوان/طبقه‌بندی" @keyup.enter="apply" />
        </div>
        <div class="mt-3 flex gap-2">
          <button class="btn-orange" @click="apply">اعمال فیلتر</button>
          <button class="btn-muted" @click="clear">پاک کردن</button>
        </div>
      </div>

      <DataTable :columns="columns" :rows="store.posts" :loading="store.loading" actions>
        <template #cell-index="{ index }">{{ fa(rowNum(index)) }}</template>
        <template #cell-title="{ row }">
          <button class="text-right font-medium hover:text-orange-600" @click="openEdit(row)">{{ row.title }}</button>
        </template>
        <template #cell-location="{ row }">{{ locationText(row) }}</template>
        <template #cell-registration_deadline="{ row }">{{ formatDate(row.registration_deadline) }}</template>
        <template #cell-status="{ row }">
          <span class="rounded-full px-2 py-0.5 text-xs font-bold" :class="statusClass(row.status)">{{ statusLabel(row.status) }}</span>
        </template>
        <template #cell-view_count="{ row }">{{ fa(row.view_count) }}</template>
        <template #actions="{ row }">
          <div class="flex flex-wrap justify-end gap-1">
            <button class="act" @click="openEdit(row)">مشاهده</button>
            <button v-if="row.status === 'pending'" class="act text-emerald-700" @click="askApprove(row)">تایید</button>
            <button v-if="row.status === 'pending'" class="act text-red-600" @click="askReject(row)">رد</button>
            <button class="act" @click="openEdit(row)">ویرایش</button>
            <button class="act text-red-600" @click="askDelete(row)">حذف</button>
          </div>
        </template>
      </DataTable>

      <PaginationBar :meta="store.meta" @page="go" />
    </div>

    <JobPostModal
      :open="modalOpen"
      :post="editing"
      :provinces="store.filterOptions.provinces.length ? store.filterOptions.provinces : defaultProvinces"
      :classifications="store.classifications"
      @close="modalOpen = false"
      @saved="onSaved"
    />
    <ClassificationManagerModal
      :open="classOpen"
      @close="classOpen = false"
      @changed="onClassChanged"
    />
    <JobImportModal ref="importRef" :open="importOpen" @close="importOpen = false" @imported="onImport" />
    <ConfirmDialog :open="confirm.open" :title="confirm.title" :message="confirm.message" @cancel="confirm.open = false" @confirm="runConfirm" />
  </AdminLayout>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import AdminLayout from '../components/layout/AdminLayout.vue';
import ConfirmDialog from '../components/ui/ConfirmDialog.vue';
import DataTable from '../components/ui/DataTable.vue';
import ClassificationManagerModal from '../components/jobs/ClassificationManagerModal.vue';
import JobImportModal from '../components/jobs/JobImportModal.vue';
import JobPostModal from '../components/jobs/JobPostModal.vue';
import PaginationBar from '../components/ui/PaginationBar.vue';
import { formatDate } from '../../utils/format';
import { useToast } from '../../composables/useToast';
import { useJobPostsStore } from '../stores/jobPosts';

const store = useJobPostsStore();
const toast = useToast();
const modalOpen = ref(false);
const importOpen = ref(false);
const classOpen = ref(false);
const editing = ref(null);
const importRef = ref(null);
const confirm = reactive({ open: false, title: '', message: '', action: null });

const columns = [
  { key: 'index', label: '#' },
  { key: 'title', label: 'عنوان آگهی' },
  { key: 'company_name', label: 'طبقه‌بندی' },
  { key: 'location', label: 'استان/شهر' },
  { key: 'registration_deadline', label: 'مهلت ثبت‌نام' },
  { key: 'status', label: 'وضعیت' },
  { key: 'view_count', label: 'بازدید' },
];

const defaultProvinces = ['تهران', 'اصفهان', 'فارس', 'خراسان رضوی', 'آذربایجان شرقی', 'خوزستان', 'البرز', 'قم', 'کرمان', 'گیلان'];

function locationText(row) {
  const provinces = Array.isArray(row.provinces) && row.provinces.length
    ? row.provinces.join('، ')
    : row.province;
  return [row.city, provinces].filter(Boolean).join(' / ') || '—';
}
function fa(n) {
  return new Intl.NumberFormat('fa-IR').format(Number(n || 0));
}
function rowNum(i) {
  return (store.meta.from || ((store.meta.current_page - 1) * (store.meta.per_page || 20) + 1) || 1) + i;
}
function statusLabel(s) {
  return { pending: 'در انتظار', approved: 'تایید شده', rejected: 'رد شده' }[s] || s;
}
function statusClass(s) {
  return {
    pending: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-emerald-100 text-emerald-700',
    rejected: 'bg-red-100 text-red-700',
  }[s] || 'bg-slate-100';
}

async function apply() {
  await store.fetchJobPosts(1);
}
async function clear() {
  store.resetFilters();
  await store.fetchJobPosts(1);
}
async function go(p) {
  await store.fetchJobPosts(p);
}
function openCreate() {
  editing.value = null;
  modalOpen.value = true;
}
async function openEdit(row) {
  try {
    editing.value = await store.fetchJobPost(row.id);
    modalOpen.value = true;
  } catch (e) {
    toast.error(e.response?.data?.message || 'بارگذاری ناموفق بود.');
  }
}
function askApprove(row) {
  confirm.open = true;
  confirm.title = 'تایید آگهی';
  confirm.message = `آگهی «${row.title}» تایید شود؟`;
  confirm.action = async () => {
    await store.approveJobPost(row.id);
    toast.success('آگهی تایید شد.');
  };
}
function askReject(row) {
  confirm.open = true;
  confirm.title = 'رد آگهی';
  confirm.message = `آگهی «${row.title}» رد شود؟`;
  confirm.action = async () => {
    await store.rejectJobPost(row.id);
    toast.success('آگهی رد شد.');
  };
}
function askDelete(row) {
  confirm.open = true;
  confirm.title = 'حذف آگهی';
  confirm.message = `آگهی «${row.title}» حذف شود؟`;
  confirm.action = async () => {
    await store.deleteJobPost(row.id);
    toast.success('آگهی حذف شد.');
  };
}
async function runConfirm() {
  const fn = confirm.action;
  confirm.open = false;
  try {
    if (fn) await fn();
  } catch (e) {
    toast.error(e.response?.data?.message || 'عملیات ناموفق بود.');
  }
}
async function onSaved({ id, payload }) {
  try {
    if (id) await store.updateJobPost(id, payload);
    else await store.createJobPost(payload);
    modalOpen.value = false;
    toast.success('آگهی ذخیره شد.');
  } catch (e) {
    const msg = e.response?.data?.errors
      ? Object.values(e.response.data.errors).flat()[0]
      : e.response?.data?.message;
    toast.error(msg || 'ذخیره ناموفق بود.');
  }
}

function onClassChanged() {
  store.fetchFilterOptions();
  store.fetchClassifications();
}

async function onImport(file) {
  try {
    const result = await store.importFromExcel(file);
    importRef.value?.setResult(result);
    toast.success('ورود اکسل انجام شد.');
  } catch (e) {
    importRef.value?.setError(e.response?.data?.message || 'ورود ناموفق بود.');
  }
}

onMounted(async () => {
  await Promise.all([store.fetchFilterOptions(), store.fetchClassifications(), store.fetchJobPosts(1)]);
});
</script>

<style scoped>
.field { @apply h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-orange-400; }
.btn-dark { @apply rounded-xl bg-[#0f2744] px-4 py-2.5 text-sm font-bold text-white; }
.btn-orange { @apply rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-bold text-white; }
.btn-muted { @apply rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold text-slate-700; }
.act { @apply rounded-lg bg-slate-100 px-2 py-1 text-[11px] font-bold text-slate-700; }
</style>
