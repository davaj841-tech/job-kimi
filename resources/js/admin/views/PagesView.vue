<template>
      <div class="space-y-5">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold">صفحات CMS</h1>
          <p class="mt-1 text-sm text-slate-500">
            صفحات با اسلاگ terms، privacy، about و contact در فوتر و سایت نمایش داده می‌شوند.
            معرفی مدیران از بخش پایین همین صفحه در «درباره ما» دیده می‌شود.
          </p>
        </div>
        <button class="btn-dark" @click="openCreate">صفحه جدید</button>
      </div>
      <DataTable :columns="columns" :rows="rows" :loading="loading" actions>
        <template #cell-index="{ index }">{{ index + 1 }}</template>
        <template #cell-is_published="{ row }">{{
          row.is_published ? 'منتشر' : 'پیش‌نویس'
        }}</template>
        <template #actions="{ row }">
          <button class="act" @click="edit(row)">ویرایش</button>
          <button class="act text-red-600" @click="remove(row)">حذف</button>
        </template>
      </DataTable>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div>
          <h2 class="text-lg font-bold">مدیران سایت</h2>
          <p class="mt-1 text-xs text-slate-500">
            در پایین صفحه درباره ما به صورت ۳ ستون نمایش داده می‌شود: نام، سمت، عکس پرسنلی ۳×۴، شرح.
          </p>
        </div>
        <button type="button" class="btn-orange" @click="addMember">
          افزودن مدیر
        </button>
      </div>
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div
          v-for="(m, idx) in team"
          :key="m.id || 'new-' + idx"
          class="space-y-2 rounded-xl border border-slate-200 p-3"
        >
          <input
            v-model="m.name"
            class="field"
            placeholder="نام و نام خانوادگی"
          />
          <input
            v-model="m.role"
            class="field"
            placeholder="سمت (مثلاً مدیرعامل)"
          />
          <label class="block text-xs text-slate-500">عکس پرسنلی ۳×۴</label>
          <input type="file" accept="image/*" @change="onPhoto($event, m)" />
          <img
            v-if="m.preview || m.photo_url"
            :src="m.preview || m.photo_url"
            alt=""
            class="mx-auto h-32 w-24 rounded object-cover object-top"
          />
          <textarea
            v-model="m.bio"
            rows="4"
            class="field h-auto py-2"
            placeholder="شرح کوتاه درباره خودش"
          />
          <div class="flex justify-between gap-2">
            <button type="button" class="act" @click="saveMember(m)">
              ذخیره
            </button>
            <button
              v-if="m.id"
              type="button"
              class="act text-red-600"
              @click="removeMember(m, idx)"
            >
              حذف
            </button>
          </div>
        </div>
      </div>
    </section>

    <Teleport to="body">
      <div
        v-if="modal"
        class="fixed inset-0 z-[80] flex flex-col bg-slate-100"
      >
        <header
          class="flex shrink-0 items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3"
        >
          <div class="min-w-0">
            <p class="text-xs font-bold text-slate-400">ویرایشگر صفحه</p>
            <h3 class="truncate text-lg font-black text-slate-800">
              {{ form.title || (form.id ? 'ویرایش صفحه' : 'صفحه جدید') }}
            </h3>
          </div>
          <div class="flex shrink-0 items-center gap-2">
            <button type="button" class="btn-muted" @click="modal = false">
              انصراف
            </button>
            <button type="button" class="btn-orange" :disabled="saving" @click="save">
              {{ saving ? '...' : 'ذخیره صفحه' }}
            </button>
          </div>
        </header>

        <div class="mx-auto flex min-h-0 w-full max-w-7xl flex-1 flex-col gap-4 overflow-hidden p-4 lg:flex-row">
          <aside
            class="w-full shrink-0 space-y-3 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-4 lg:w-72"
          >
            <label class="block text-xs font-bold text-slate-500">عنوان</label>
            <input
              v-model="form.title"
              required
              class="field"
              placeholder="عنوان صفحه"
            />
            <label class="block text-xs font-bold text-slate-500">اسلاگ</label>
            <input
              v-model="form.slug"
              class="field"
              dir="ltr"
              placeholder="about"
            />
            <label class="block text-xs font-bold text-slate-500">عنوان سئو</label>
            <input
              v-model="form.meta_title"
              class="field"
              placeholder="meta title"
            />
            <label class="block text-xs font-bold text-slate-500">توضیح سئو</label>
            <textarea
              v-model="form.meta_description"
              rows="3"
              class="field h-auto py-2"
              placeholder="meta description"
            />
            <label class="flex items-center gap-2 text-sm font-bold text-slate-700">
              <input v-model="form.is_published" type="checkbox" />
              منتشر شده
            </label>
            <p class="text-[11px] leading-5 text-slate-400">
              از نوار ابزار برای تیتر، فهرست، لینک، جدول و تصویر استفاده کنید. حالت HTML برای ویرایش دقیق است.
            </p>
          </aside>

          <div class="min-h-0 min-w-0 flex-1 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-3">
            <p class="mb-2 text-xs font-bold text-slate-500">محتوای صفحه</p>
            <RichEditor v-model="form.content" size="page" />
          </div>
        </div>
      </div>
    </Teleport>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import adminApi from '../api/client'
import DataTable from '../components/ui/DataTable.vue'
import RichEditor from '../components/ui/RichEditor.vue'
import { unwrapList, apiErrorMessage } from '../../utils/format'
import { useToast } from '../../composables/useToast'

const toast = useToast()
const rows = ref([])
const team = ref([])
const loading = ref(false)
const saving = ref(false)
const modal = ref(false)
const form = reactive({
  id: null,
  title: '',
  slug: '',
  content: '',
  meta_title: '',
  meta_description: '',
  is_published: false,
})
const columns = [
  { key: 'index', label: '#' },
  { key: 'title', label: 'عنوان' },
  { key: 'slug', label: 'slug' },
  { key: 'is_published', label: 'وضعیت' },
]

onMounted(() => {
  load()
  loadTeam()
})
async function load() {
  loading.value = true
  try {
    const { data } = await adminApi.get('/admin/pages')
    rows.value = unwrapList(data)
  } finally {
    loading.value = false
  }
}
function openCreate() {
  Object.assign(form, {
    id: null,
    title: '',
    slug: '',
    content: '',
    meta_title: '',
    meta_description: '',
    is_published: false,
  })
  modal.value = true
}
function edit(row) {
  Object.assign(form, row)
  modal.value = true
}
async function save() {
  if (!form.title?.trim()) {
    toast.error('عنوان را وارد کنید')
    return
  }
  saving.value = true
  try {
    const payload = { ...form }
    delete payload.id
    if (form.id) await adminApi.put(`/admin/pages/${form.id}`, payload)
    else await adminApi.post('/admin/pages', payload)
    toast.success('ذخیره شد')
    modal.value = false
    load()
  } catch (e) {
    toast.error(apiErrorMessage(e))
  } finally {
    saving.value = false
  }
}
async function remove(row) {
  if (!confirm('حذف؟')) return
  await adminApi.delete(`/admin/pages/${row.id}`)
  load()
}

async function loadTeam() {
  const { data } = await adminApi.get('/admin/team-members')
  const list = data?.data
  team.value = Array.isArray(list) ? list.map(mapMember) : []
}

function mapMember(row) {
  return {
    id: row.id,
    name: row.name || '',
    role: row.role || '',
    bio: row.bio || '',
    photo_url: row.photo_url || '',
    photo: null,
    preview: '',
    sort_order: row.sort_order || 0,
  }
}

function addMember() {
  team.value.push({
    id: null,
    name: '',
    role: '',
    bio: '',
    photo_url: '',
    photo: null,
    preview: '',
    sort_order: team.value.length,
  })
}

function onPhoto(e, m) {
  const file = e.target.files?.[0]
  if (!file) return
  m.photo = file
  m.preview = URL.createObjectURL(file)
}

async function saveMember(m) {
  if (!String(m.name || '').trim()) {
    toast.error('نام را وارد کنید')
    return
  }
  const fd = new FormData()
  fd.append('name', m.name)
  fd.append('role', m.role || '')
  fd.append('bio', m.bio || '')
  fd.append('sort_order', String(m.sort_order || 0))
  if (m.photo instanceof File) fd.append('photo', m.photo)
  try {
    if (m.id) {
      await adminApi.post(`/admin/team-members/${m.id}`, fd)
    } else {
      await adminApi.post('/admin/team-members', fd)
    }
    toast.success('ذخیره شد')
    await loadTeam()
  } catch (e) {
    toast.error(apiErrorMessage(e))
  }
}

async function removeMember(m, idx) {
  if (!m.id) {
    team.value.splice(idx, 1)
    return
  }
  if (!confirm('حذف شود؟')) return
  await adminApi.delete(`/admin/team-members/${m.id}`)
  await loadTeam()
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
  @apply rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50;
}
.act {
  @apply rounded-lg px-2 py-1 text-xs font-bold text-slate-600 hover:bg-slate-100;
}
</style>
