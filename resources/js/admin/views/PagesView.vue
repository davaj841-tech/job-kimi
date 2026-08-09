<template>
  <AdminLayout>
    <div class="space-y-5">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold">صفحات CMS</h1>
        <button class="btn-dark" @click="openCreate">صفحه جدید</button>
      </div>
      <DataTable :columns="columns" :rows="rows" :loading="loading" actions>
        <template #cell-index="{ index }">{{ index + 1 }}</template>
        <template #cell-is_published="{ row }">{{ row.is_published ? 'منتشر' : 'پیش‌نویس' }}</template>
        <template #actions="{ row }">
          <button class="act" @click="edit(row)">ویرایش</button>
          <button class="act text-red-600" @click="remove(row)">حذف</button>
        </template>
      </DataTable>
    </div>

    <div v-if="modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <form class="max-h-[92vh] w-full max-w-2xl space-y-3 overflow-y-auto rounded-2xl bg-white p-5" @submit.prevent="save">
        <h3 class="font-bold">{{ form.id ? 'ویرایش صفحه' : 'صفحه جدید' }}</h3>
        <input v-model="form.title" required class="field" placeholder="عنوان" />
        <input v-model="form.slug" class="field" dir="ltr" placeholder="slug" />
        <textarea v-model="form.content" rows="10" class="field min-h-[180px] py-2" placeholder="محتوا (HTML)" />
        <input v-model="form.meta_title" class="field" placeholder="meta title" />
        <textarea v-model="form.meta_description" rows="2" class="field py-2" placeholder="meta description" />
        <label class="flex items-center gap-2 text-sm"><input v-model="form.is_published" type="checkbox" /> منتشر شده</label>
        <div class="flex justify-end gap-2">
          <button type="button" class="btn-muted" @click="modal = false">انصراف</button>
          <button class="btn-orange">ذخیره</button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import adminApi from '../api/client';
import AdminLayout from '../components/layout/AdminLayout.vue';
import DataTable from '../components/ui/DataTable.vue';
import { unwrapList, apiErrorMessage } from '../../utils/format';
import { useToast } from '../../composables/useToast';

const toast = useToast();
const rows = ref([]);
const loading = ref(false);
const modal = ref(false);
const form = reactive({ id: null, title: '', slug: '', content: '', meta_title: '', meta_description: '', is_published: false });
const columns = [
  { key: 'index', label: '#' },
  { key: 'title', label: 'عنوان' },
  { key: 'slug', label: 'slug' },
  { key: 'is_published', label: 'وضعیت' },
];

onMounted(load);
async function load() {
  loading.value = true;
  try {
    const { data } = await adminApi.get('/admin/pages');
    rows.value = unwrapList(data);
  } finally {
    loading.value = false;
  }
}
function openCreate() {
  Object.assign(form, { id: null, title: '', slug: '', content: '', meta_title: '', meta_description: '', is_published: false });
  modal.value = true;
}
function edit(row) {
  Object.assign(form, row);
  modal.value = true;
}
async function save() {
  try {
    const payload = { ...form };
    delete payload.id;
    if (form.id) await adminApi.put(`/admin/pages/${form.id}`, payload);
    else await adminApi.post('/admin/pages', payload);
    toast.success('ذخیره شد');
    modal.value = false;
    load();
  } catch (e) {
    toast.error(apiErrorMessage(e));
  }
}
async function remove(row) {
  if (!confirm('حذف؟')) return;
  await adminApi.delete(`/admin/pages/${row.id}`);
  load();
}
</script>

<style scoped>
.field { @apply h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none; }
.btn-dark { @apply rounded-xl bg-[#0f2744] px-4 py-2.5 text-sm font-bold text-white; }
.btn-muted { @apply rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold; }
.btn-orange { @apply rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-bold text-white; }
.act { @apply rounded-lg px-2 py-1 text-xs font-bold text-slate-600 hover:bg-slate-100; }
</style>
