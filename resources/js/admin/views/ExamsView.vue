<template>
  <AdminLayout>
    <div class="space-y-5">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <h1 class="text-2xl font-bold text-gray-800">مدیریت آزمون‌ها</h1>
          <span class="rounded-full bg-orange-100 px-3 py-1 text-xs font-bold text-orange-700">
            {{ fa(store.meta.total || 0) }}
          </span>
        </div>
        <button class="rounded-xl bg-[#0f2744] px-4 py-2.5 text-sm font-bold text-white" @click="openCreate">
          آزمون جدید
        </button>
      </div>

      <div class="rounded-xl bg-white p-4 shadow-sm">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-5">
          <input v-model="store.filters.search" class="field lg:col-span-2" placeholder="جستجو عنوان آزمون" @keyup.enter="apply" />
          <select v-model="store.filters.job_classification_id" class="field">
            <option value="">همه طبقه‌بندی‌ها</option>
            <option v-for="c in store.classifications" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
          <select v-model="store.filters.status" class="field">
            <option value="">همه وضعیت‌ها</option>
            <option value="published">منتشر شده</option>
            <option value="draft">پیش‌نویس</option>
            <option value="archived">بایگانی</option>
          </select>
          <select v-model="store.filters.is_free" class="field">
            <option value="">همه قیمت‌ها</option>
            <option value="1">رایگان</option>
            <option value="0">پولی</option>
          </select>
        </div>
        <div class="mt-3 flex flex-wrap gap-2">
          <select v-model="store.filters.sort" class="field max-w-xs">
            <option value="desc">جدیدترین</option>
            <option value="attempts">بیشترین شرکت‌کننده</option>
            <option value="asc">قدیمی‌ترین</option>
          </select>
          <button class="rounded-xl bg-orange-500 px-4 py-2 text-sm font-bold text-white" @click="apply">اعمال فیلتر</button>
          <button class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-bold" @click="clear">پاک کردن</button>
        </div>
      </div>

      <DataTable :columns="columns" :rows="store.exams" :loading="store.loading" actions>
        <template #cell-index="{ index }">{{ fa(rowNum(index)) }}</template>
        <template #cell-title="{ row }">
          <button class="text-right font-medium text-slate-800 hover:text-orange-600" @click="openEdit(row)">
            {{ row.title }}
          </button>
        </template>
        <template #cell-category_name="{ row }">{{ row.category_name || '—' }}</template>
        <template #cell-question_count="{ row }">{{ fa(row.question_count) }}</template>
        <template #cell-duration_minutes="{ row }">{{ fa(row.duration_minutes) }} دقیقه</template>
        <template #cell-price="{ row }">{{ priceLabel(row) }}</template>
        <template #cell-status="{ row }">
          <span class="rounded-full px-2 py-0.5 text-xs font-bold" :class="statusClass(row.status)">
            {{ statusLabel(row.status) }}
          </span>
        </template>
        <template #actions="{ row }">
          <div class="flex flex-wrap justify-end gap-1">
            <RouterLink class="act" :to="{ name: 'admin-exam-take', params: { id: row.id } }">آزمون‌گیری</RouterLink>
            <button class="act" @click="openEdit(row)">ویرایش</button>
            <RouterLink class="act" :to="{ path: '/admin/questions', query: { exam_id: row.id } }">سوالات</RouterLink>
            <button class="act" @click="openStats(row)">آمار</button>
            <button class="act text-red-600" @click="askArchive(row)">بایگانی</button>
          </div>
        </template>
        <template #empty>
          <p class="py-6 text-slate-500">آزمونی یافت نشد</p>
        </template>
      </DataTable>

      <div v-if="store.meta.last_page" class="flex items-center justify-between rounded-xl bg-white px-4 py-3 text-sm shadow-sm">
        <p class="text-slate-500">
          نمایش {{ fa(store.meta.from || 0) }} تا {{ fa(store.meta.to || 0) }} از {{ fa(store.meta.total || 0) }}
        </p>
        <div class="flex gap-1">
          <button :disabled="(store.meta.current_page || 1) <= 1" @click="go((store.meta.current_page || 1) - 1)">قبلی</button>
          <button
            v-for="p in pages"
            :key="p"
            class="min-w-8 rounded-lg px-2 py-1 text-xs font-bold"
            :class="p === store.meta.current_page ? 'bg-orange-500 text-white' : 'bg-slate-100'"
            @click="go(p)"
          >
            {{ fa(p) }}
          </button>
          <button
            :disabled="(store.meta.current_page || 1) >= (store.meta.last_page || 1)"
            @click="go((store.meta.current_page || 1) + 1)"
          >
            بعدی
          </button>
        </div>
      </div>
    </div>

    <ExamModal
      :open="modalOpen"
      :exam="editing"
      :classifications="store.classifications"
      @close="modalOpen = false"
      @saved="onSaved"
    />
    <ExamStatsModal :open="statsOpen" :loading="store.statsLoading" :stats="store.stats" @close="statsOpen = false" />
    <ConfirmDialog
      :open="confirm.open"
      :title="confirm.title"
      :message="confirm.message"
      @cancel="confirm.open = false"
      @confirm="runConfirm"
    />
  </AdminLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import AdminLayout from '../components/layout/AdminLayout.vue';
import ConfirmDialog from '../components/ui/ConfirmDialog.vue';
import DataTable from '../components/ui/DataTable.vue';
import ExamModal from '../components/exams/ExamModal.vue';
import ExamStatsModal from '../components/exams/ExamStatsModal.vue';
import { useToast } from '../../composables/useToast';
import { useExamsStore } from '../stores/exams';

const store = useExamsStore();
const toast = useToast();

const modalOpen = ref(false);
const statsOpen = ref(false);
const editing = ref(null);
const confirm = reactive({ open: false, title: '', message: '', action: null });

const columns = [
  { key: 'index', label: '#' },
  { key: 'title', label: 'عنوان' },
  { key: 'category_name', label: 'طبقه‌بندی' },
  { key: 'question_count', label: 'سوالات' },
  { key: 'duration_minutes', label: 'مدت' },
  { key: 'price', label: 'قیمت/اشتراک' },
  { key: 'status', label: 'وضعیت' },
];

const pages = computed(() => {
  const cur = store.meta.current_page || 1;
  const last = store.meta.last_page || 1;
  const out = [];
  for (let i = Math.max(1, cur - 2); i <= Math.min(last, Math.max(1, cur - 2) + 4); i++) out.push(i);
  return out;
});

function fa(n) {
  return new Intl.NumberFormat('fa-IR').format(Number(n || 0));
}
function rowNum(i) {
  return (store.meta.from || 1) + i;
}
function priceLabel(row) {
  if (row.is_free) return 'رایگان';
  if (row.subscription_required === 'paid') return 'اشتراک';
  return `${fa(row.price)} ریال`;
}
function statusLabel(s) {
  return { published: 'منتشر شده', draft: 'پیش‌نویس', archived: 'بایگانی' }[s] || s;
}
function statusClass(s) {
  return {
    published: 'bg-emerald-100 text-emerald-700',
    draft: 'bg-slate-100 text-slate-600',
    archived: 'bg-red-100 text-red-700',
  }[s] || 'bg-slate-100';
}

async function apply() {
  await store.fetchExams(1);
}
async function clear() {
  store.resetFilters();
  await store.fetchExams(1);
}
async function go(p) {
  await store.fetchExams(p);
}

function openCreate() {
  editing.value = null;
  modalOpen.value = true;
}
async function openEdit(row) {
  try {
    editing.value = await store.fetchExam(row.id);
    modalOpen.value = true;
  } catch (e) {
    toast.error(e.response?.data?.message || 'بارگذاری آزمون ناموفق بود.');
  }
}
async function openStats(row) {
  statsOpen.value = true;
  try {
    await store.fetchStats(row.id);
  } catch (e) {
    toast.error(e.response?.data?.message || 'آمار در دسترس نیست.');
  }
}
function askArchive(row) {
  confirm.open = true;
  confirm.title = 'بایگانی آزمون';
  confirm.message = `آزمون «${row.title}» بایگانی شود؟`;
  confirm.action = async () => {
    try {
      await store.deleteExam(row.id);
      toast.success('آزمون بایگانی شد.');
    } catch (e) {
      toast.error(e.response?.data?.message || 'عملیات ناموفق بود.');
    }
  };
}
async function runConfirm() {
  const fn = confirm.action;
  confirm.open = false;
  if (fn) await fn();
}
async function onSaved({ id, payload }) {
  try {
    if (id) await store.updateExam(id, payload);
    else await store.createExam(payload);
    modalOpen.value = false;
    toast.success('آزمون ذخیره شد.');
  } catch (e) {
    toast.error(e.response?.data?.message || 'ذخیره ناموفق بود.');
  }
}

onMounted(async () => {
  await Promise.all([
    store.fetchClassifications(),
    store.fetchExams(1),
  ]);
});
</script>

<style scoped>
.field {
  @apply h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-orange-400;
}
.act {
  @apply rounded-lg bg-slate-100 px-2 py-1 text-[11px] font-bold text-slate-700;
}
</style>
