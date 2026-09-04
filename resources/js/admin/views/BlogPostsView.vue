<template>
  <div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-bold text-gray-800">مدیریت مقالات</h1>
      <div class="flex gap-2">
        <button class="btn-dark" @click="openCreate">مقاله جدید</button>
        <button class="btn-orange" @click="aiOpen = true">تولید با AI</button>
      </div>
    </div>

    <div class="rounded-xl bg-white p-4 shadow-sm">
      <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
        <input
          v-model="store.filters.search"
          class="field"
          placeholder="جستجو عنوان"
          @keyup.enter="apply"
        />
        <select v-model="store.filters.status" class="field">
          <option value="">همه وضعیت‌ها</option>
          <option value="draft">پیش‌نویس</option>
          <option value="published">منتشر شده</option>
        </select>
      </div>
      <div class="mt-3 flex gap-2">
        <button class="btn-orange" @click="apply">اعمال فیلتر</button>
        <button class="btn-muted" @click="clear">پاک کردن</button>
      </div>
    </div>

    <DataTable
      :columns="columns"
      :rows="store.posts"
      :loading="store.loading"
      actions
    >
      <template #cell-index="{ index }">{{ fa(rowNum(index)) }}</template>
      <template #cell-title="{ row }">
        <button
          class="text-right font-medium hover:text-orange-600"
          @click="openEdit(row)"
        >
          {{ row.title }}
        </button>
      </template>
      <template #cell-author_name="{ row }">{{
        row.author_name || '—'
      }}</template>
      <template #cell-status="{ row }">
        <span
          class="rounded-full px-2 py-0.5 text-xs font-bold"
          :class="
            row.status === 'published'
              ? 'bg-emerald-100 text-emerald-700'
              : 'bg-slate-100 text-slate-600'
          "
        >
          {{ row.status === 'published' ? 'منتشر شده' : 'پیش‌نویس' }}
        </span>
      </template>
      <template #cell-created_at="{ row }">{{
        formatDate(row.created_at)
      }}</template>
      <template #actions="{ row }">
        <div class="flex flex-wrap justify-end gap-1">
          <button class="act" @click="openEdit(row)">ویرایش</button>
          <button class="act" @click="togglePublish(row)">
            {{ row.status === 'published' ? 'پیش‌نویس' : 'انتشار' }}
          </button>
          <a
            class="act"
            :href="`/blog/${row.slug}`"
            target="_blank"
            rel="noopener"
            >پیش‌نمایش</a
          >
          <button class="act text-red-600" @click="askDelete(row)">حذف</button>
        </div>
      </template>
    </DataTable>

    <PaginationBar :meta="store.meta" @page="go" />
  </div>

  <BlogPostModal
    :open="modalOpen"
    :post="editing"
    @close="modalOpen = false"
    @saved="onSaved"
  />
  <BlogAIGenerateModal
    ref="aiRef"
    :open="aiOpen"
    @close="aiOpen = false"
    @generate="onGenerate"
  />
  <ConfirmDialog
    :open="confirm.open"
    :title="confirm.title"
    :message="confirm.message"
    @cancel="confirm.open = false"
    @confirm="runConfirm"
  />
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import BlogAIGenerateModal from '../components/blog/BlogAIGenerateModal.vue'
import BlogPostModal from '../components/blog/BlogPostModal.vue'
import ConfirmDialog from '../components/ui/ConfirmDialog.vue'
import DataTable from '../components/ui/DataTable.vue'
import PaginationBar from '../components/ui/PaginationBar.vue'
import { formatDate } from '../../utils/format'
import { useToast } from '../../composables/useToast'
import { useBlogPostsStore } from '../stores/blogPosts'

const store = useBlogPostsStore()
const toast = useToast()
const modalOpen = ref(false)
const aiOpen = ref(false)
const editing = ref(null)
const aiRef = ref(null)
const confirm = reactive({ open: false, title: '', message: '', action: null })

const columns = [
  { key: 'index', label: '#' },
  { key: 'title', label: 'عنوان' },
  { key: 'author_name', label: 'نویسنده' },
  { key: 'status', label: 'وضعیت' },
  { key: 'created_at', label: 'تاریخ' },
]

function fa(n) {
  return new Intl.NumberFormat('fa-IR').format(Number(n || 0))
}
function rowNum(i) {
  return (
    ((store.meta.current_page || 1) - 1) * (store.meta.per_page || 20) + i + 1
  )
}

async function apply() {
  await store.fetchBlogPosts(1)
}
async function clear() {
  store.resetFilters()
  await store.fetchBlogPosts(1)
}
async function go(p) {
  await store.fetchBlogPosts(p)
}
function openCreate() {
  editing.value = null
  modalOpen.value = true
}
async function openEdit(row) {
  try {
    editing.value = await store.fetchBlogPost(row.id)
    modalOpen.value = true
  } catch (e) {
    toast.error(e.response?.data?.message || 'بارگذاری ناموفق بود.')
  }
}
async function togglePublish(row) {
  try {
    if (row.status === 'published') await store.draft(row.id)
    else await store.publish(row.id)
    toast.success('وضعیت مقاله به‌روزرسانی شد.')
  } catch (e) {
    toast.error(e.response?.data?.message || 'تغییر وضعیت ناموفق بود.')
  }
}
function askDelete(row) {
  confirm.open = true
  confirm.title = 'حذف مقاله'
  confirm.message = `مقاله «${row.title}» حذف شود؟`
  confirm.action = async () => {
    await store.deleteBlogPost(row.id)
    toast.success('مقاله حذف شد.')
  }
}
async function runConfirm() {
  const fn = confirm.action
  confirm.open = false
  try {
    if (fn) await fn()
  } catch (e) {
    toast.error(e.response?.data?.message || 'عملیات ناموفق بود.')
  }
}
async function onSaved({ id, payload }) {
  try {
    if (id) await store.updateBlogPost(id, payload)
    else await store.createBlogPost(payload)
    modalOpen.value = false
    if (payload.status === 'draft') {
      toast.success(
        'مقاله ذخیره شد. تا زمان انتشار، در صفحه اصلی نمایش داده نمی‌شود.'
      )
    } else {
      toast.success('مقاله منتشر شد و در صفحه اصلی نمایش داده می‌شود.')
    }
  } catch (e) {
    toast.error(e.response?.data?.message || 'ذخیره ناموفق بود.')
  }
}
async function onGenerate(topic) {
  aiRef.value?.setLoading(true)
  try {
    const data = await store.generateWithAI(topic)
    aiRef.value?.setResult(data)
    await store.fetchBlogPosts(1)
    toast.success('مقاله AI تولید شد.')
  } catch (e) {
    aiRef.value?.setError(e.response?.data?.message || 'تولید ناموفق بود.')
  }
}

onMounted(() => store.fetchBlogPosts(1))
</script>

<style scoped>
.field {
  @apply h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-orange-400;
}
.btn-dark {
  @apply rounded-xl bg-[#0f2744] px-4 py-2.5 text-sm font-bold text-white;
}
.btn-orange {
  @apply rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-bold text-white;
}
.btn-muted {
  @apply rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold text-slate-700;
}
.act {
  @apply rounded-lg bg-slate-100 px-2 py-1 text-[11px] font-bold text-slate-700;
}
</style>
