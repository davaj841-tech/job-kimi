<template>
      <div class="space-y-5">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold">بنرها</h1>
        <button class="btn-dark" @click="openCreate">بنر جدید</button>
      </div>
      <DataTable :columns="columns" :rows="rows" :loading="loading" actions>
        <template #cell-index="{ index }">{{ index + 1 }}</template>
        <template #cell-is_active="{ row }">{{
          row.is_active ? 'فعال' : 'غیرفعال'
        }}</template>
        <template #actions="{ row }">
          <button class="act" @click="edit(row)">ویرایش</button>
          <button class="act text-red-600" @click="remove(row)">حذف</button>
        </template>
      </DataTable>
    </div>

    <div
      v-if="modal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    >
      <form
        class="w-full max-w-lg space-y-3 rounded-2xl bg-white p-5"
        @submit.prevent="save"
      >
        <h3 class="font-bold">{{ form.id ? 'ویرایش بنر' : 'بنر جدید' }}</h3>
        <input
          v-model="form.title"
          required
          class="field"
          placeholder="عنوان"
        />
        <input v-model="form.link" class="field" dir="ltr" placeholder="لینک" />
        <select v-model="form.position" class="field">
          <option value="home_hero">هیرو صفحه اول</option>
          <option value="home_top">بالای خانه</option>
          <option value="home_middle">وسط خانه</option>
          <option value="exam_sidebar">سایدبار آزمون</option>
        </select>
        <input
          v-model.number="form.sort_order"
          type="number"
          class="field"
          placeholder="ترتیب"
        />
        <label class="flex items-center gap-2 text-sm"
          ><input v-model="form.is_active" type="checkbox" /> فعال</label
        >
        <input type="file" accept="image/*" @change="onFile" />
        <div class="flex justify-end gap-2">
          <button type="button" class="btn-muted" @click="modal = false">
            انصراف
          </button>
          <button class="btn-orange">ذخیره</button>
        </div>
      </form>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import adminApi from '../api/client'
import DataTable from '../components/ui/DataTable.vue'
import { unwrapList, apiErrorMessage } from '../../utils/format'
import { useToast } from '../../composables/useToast'

const toast = useToast()
const rows = ref([])
const loading = ref(false)
const modal = ref(false)
const file = ref(null)
const form = reactive({
  id: null,
  title: '',
  link: '',
  position: 'home_top',
  sort_order: 0,
  is_active: true,
})
const columns = [
  { key: 'index', label: '#' },
  { key: 'title', label: 'عنوان' },
  { key: 'position', label: 'موقعیت' },
  { key: 'sort_order', label: 'ترتیب' },
  { key: 'is_active', label: 'وضعیت' },
]

onMounted(load)
async function load() {
  loading.value = true
  try {
    const { data } = await adminApi.get('/admin/banners')
    rows.value = unwrapList(data)
  } finally {
    loading.value = false
  }
}
function openCreate() {
  Object.assign(form, {
    id: null,
    title: '',
    link: '',
    position: 'home_top',
    sort_order: 0,
    is_active: true,
  })
  file.value = null
  modal.value = true
}
function edit(row) {
  Object.assign(form, row)
  file.value = null
  modal.value = true
}
function onFile(e) {
  file.value = e.target.files?.[0] || null
}
async function save() {
  try {
    const fd = new FormData()
    fd.append('title', form.title)
    fd.append('link', form.link || '')
    fd.append('position', form.position)
    fd.append('sort_order', String(form.sort_order || 0))
    fd.append('is_active', form.is_active ? '1' : '0')
    if (file.value) fd.append('image', file.value)
    if (form.id) {
      fd.append('_method', 'PUT')
      await adminApi.post(`/admin/banners/${form.id}`, fd)
    } else {
      await adminApi.post('/admin/banners', fd)
    }
    toast.success('ذخیره شد')
    modal.value = false
    load()
  } catch (e) {
    toast.error(apiErrorMessage(e))
  }
}
async function remove(row) {
  if (!confirm('حذف شود؟')) return
  await adminApi.delete(`/admin/banners/${row.id}`)
  load()
}
</script>

<style scoped>
.field {
  @apply h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none;
}
.btn-dark {
  @apply rounded-xl bg-[#0f2744] px-4 py-2.5 text-sm font-bold text-white;
}
.btn-muted {
  @apply rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold;
}
.btn-orange {
  @apply rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-bold text-white;
}
.act {
  @apply rounded-lg px-2 py-1 text-xs font-bold text-slate-600 hover:bg-slate-100;
}
</style>
