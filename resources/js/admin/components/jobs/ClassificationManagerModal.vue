<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
  >
    <div
      class="flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-xl"
    >
      <div class="flex items-center justify-between border-b px-5 py-4">
        <h3 class="text-lg font-bold">مدیریت طبقه‌بندی‌ها (مادر / فرزند)</h3>
        <button type="button" @click="$emit('close')">✕</button>
      </div>

      <div class="grid gap-4 overflow-y-auto p-5 md:grid-cols-2">
        <form
          class="space-y-3 rounded-xl border border-slate-200 p-4"
          @submit.prevent="save"
        >
          <p class="text-sm font-bold text-slate-700">
            {{ editingId ? 'ویرایش' : 'افزودن' }} طبقه‌بندی
          </p>
          <input
            v-model="form.name"
            class="field"
            required
            placeholder="نام طبقه‌بندی *"
          />
          <select v-model="form.parent_id" class="field">
            <option value="">بدون مادر (سطح اول)</option>
            <option v-for="p in parentsOnly" :key="p.id" :value="p.id">
              {{ p.raw_name || p.name }}
            </option>
          </select>

          <div>
            <label class="mb-1 block text-xs text-slate-500"
              >شکلک / آیکون</label
            >
            <div
              class="mb-2 flex max-h-36 flex-wrap gap-1 overflow-y-auto rounded-lg border border-slate-100 bg-slate-50/80 p-2"
            >
              <button
                v-for="ic in iconOptions"
                :key="ic"
                type="button"
                class="flex h-9 w-9 items-center justify-center rounded-lg border text-lg leading-none transition"
                :class="
                  isIconSelected(ic)
                    ? 'border-orange-500 bg-orange-50 ring-1 ring-orange-400'
                    : 'border-slate-200 bg-white hover:border-slate-300'
                "
                :title="ic"
                @click="form.icon = ic"
              >
                {{ ic }}
              </button>
            </div>
            <div class="flex items-center gap-2">
              <span
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-xl"
                aria-hidden="true"
                >{{ displayIcon(form.icon) }}</span
              >
              <input
                v-model="form.icon"
                class="field"
                dir="ltr"
                placeholder="یا شکلک دلخواه 🏦"
              />
            </div>
          </div>

          <div>
            <label class="mb-1 block text-xs text-slate-500">رنگ</label>
            <div class="flex items-center gap-2">
              <input
                v-model="form.color"
                type="color"
                class="h-10 w-14 cursor-pointer rounded border border-slate-200"
              />
              <input
                v-model="form.color"
                class="field"
                dir="ltr"
                placeholder="#1e3a5f"
              />
            </div>
            <div class="mt-2 flex flex-wrap gap-1">
              <button
                v-for="c in colorPresets"
                :key="c"
                type="button"
                class="h-6 w-6 rounded-full border border-white shadow"
                :style="{ background: c }"
                @click="form.color = c"
              />
            </div>
          </div>

          <div>
            <label class="mb-1 block text-xs text-slate-500"
              >لوگو (اختیاری)</label
            >
            <input
              type="file"
              accept="image/*"
              class="block w-full text-xs"
              @change="onLogo"
            />
            <div
              v-if="logoPreview || form.logo_url"
              class="mt-2 flex items-center gap-2"
            >
              <img
                :src="logoPreview || form.logo_url"
                alt=""
                class="h-10 w-10 rounded-full object-cover"
              />
              <button
                type="button"
                class="text-xs text-red-600"
                @click="clearLogo"
              >
                حذف لوگو
              </button>
            </div>
          </div>

          <label class="flex items-center gap-2 text-sm">
            <input v-model="form.is_active" type="checkbox" />
            فعال
          </label>
          <label class="flex items-center gap-2 text-sm">
            <input v-model="form.show_on_home" type="checkbox" />
            نمایش در صفحه اول
          </label>

          <p v-if="error" class="text-sm text-red-500">{{ error }}</p>
          <div class="flex gap-2">
            <button
              v-if="editingId"
              type="button"
              class="btn-muted flex-1"
              @click="resetForm"
            >
              انصراف
            </button>
            <button type="submit" class="btn-orange flex-1" :disabled="saving">
              {{ saving ? '...' : 'ذخیره' }}
            </button>
          </div>
        </form>

        <div class="space-y-2">
          <p class="text-sm font-bold text-slate-700">لیست سلسله‌مراتبی</p>
          <div
            v-for="(node, idx) in tree"
            :key="node.id"
            class="rounded-xl border border-slate-100 p-2"
          >
            <div class="flex items-center justify-between gap-2">
              <div class="flex items-center gap-2">
                <span
                  class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full text-sm text-white"
                  :style="{ background: node.color || '#1e3a5f' }"
                >
                  <img
                    v-if="node.logo_url"
                    :src="node.logo_url"
                    class="h-full w-full object-cover"
                    alt=""
                  />
                  <span v-else>{{ displayIcon(node.icon) }}</span>
                </span>
                <div>
                  <p class="text-sm font-bold">{{ node.name }}</p>
                  <p class="text-[11px] text-slate-400">
                    مادر
                    <span v-if="!node.show_on_home" class="text-amber-600">
                      · مخفی از صفحه اول</span
                    >
                  </p>
                </div>
              </div>
              <div class="flex flex-wrap justify-end gap-1">
                <button
                  type="button"
                  class="act"
                  :disabled="idx === 0"
                  @click="move(node, 'up')"
                >
                  ↑
                </button>
                <button
                  type="button"
                  class="act"
                  :disabled="idx === tree.length - 1"
                  @click="move(node, 'down')"
                >
                  ↓
                </button>
                <button type="button" class="act" @click="toggleHome(node)">
                  {{ node.show_on_home ? 'مخفی' : 'نمایش' }}
                </button>
                <button type="button" class="act" @click="edit(node)">
                  ویرایش
                </button>
                <button
                  type="button"
                  class="act text-red-600"
                  @click="remove(node)"
                >
                  حذف
                </button>
              </div>
            </div>
            <div
              v-for="(child, cidx) in node.children || []"
              :key="child.id"
              class="mr-4 mt-2 flex items-center justify-between rounded-lg bg-slate-50 px-2 py-1.5"
            >
              <div class="flex items-center gap-2">
                <span
                  class="flex h-6 w-6 items-center justify-center overflow-hidden rounded-full text-[10px] text-white"
                  :style="{ background: child.color || '#64748b' }"
                >
                  <img
                    v-if="child.logo_url"
                    :src="child.logo_url"
                    class="h-full w-full object-cover"
                    alt=""
                  />
                  <span v-else>{{ displayIcon(child.icon) }}</span>
                </span>
                <p class="text-sm">{{ child.name }}</p>
              </div>
              <div class="flex gap-1">
                <button
                  type="button"
                  class="act"
                  :disabled="cidx === 0"
                  @click="move(child, 'up')"
                >
                  ↑
                </button>
                <button
                  type="button"
                  class="act"
                  :disabled="cidx === (node.children?.length || 0) - 1"
                  @click="move(child, 'down')"
                >
                  ↓
                </button>
                <button type="button" class="act" @click="edit(child)">
                  ویرایش
                </button>
                <button
                  type="button"
                  class="act text-red-600"
                  @click="remove(child)"
                >
                  حذف
                </button>
              </div>
            </div>
          </div>
          <p
            v-if="!tree.length"
            class="py-6 text-center text-sm text-slate-400"
          >
            طبقه‌بندی‌ای نیست
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import adminApi from '../../api/client'
import {
  CLASSIFICATION_ICON_OPTIONS,
  resolveIconEmoji,
} from '../../../utils/classificationIcon'

const props = defineProps({ open: Boolean })
const emit = defineEmits(['close', 'changed'])

const tree = ref([])
const flat = ref([])
const saving = ref(false)
const error = ref('')
const editingId = ref(null)
const logoFile = ref(null)
const logoPreview = ref('')
const removeLogo = ref(false)

const iconOptions = CLASSIFICATION_ICON_OPTIONS

function displayIcon(icon) {
  return resolveIconEmoji(icon, '📋')
}

function isIconSelected(ic) {
  return displayIcon(form.icon) === ic || form.icon === ic
}
const colorPresets = [
  '#1e3a5f',
  '#0f766e',
  '#b45309',
  '#9f1239',
  '#1d4ed8',
  '#7c3aed',
  '#166534',
  '#334155',
]

const form = reactive({
  name: '',
  parent_id: '',
  icon: '💼',
  color: '#1e3a5f',
  is_active: true,
  show_on_home: true,
  logo_url: '',
})

const parentsOnly = computed(() =>
  flat.value.filter((c) => !c.parent_id && c.id !== editingId.value)
)

watch(
  () => props.open,
  async (v) => {
    if (v) {
      resetForm()
      await load()
    }
  }
)

async function load() {
  const { data } = await adminApi.get('/admin/job-classifications')
  tree.value = data.data?.tree || []
  flat.value = data.data?.flat || []
}

function resetForm() {
  editingId.value = null
  form.name = ''
  form.parent_id = ''
  form.icon = '💼'
  form.color = '#1e3a5f'
  form.is_active = true
  form.show_on_home = true
  form.logo_url = ''
  logoFile.value = null
  logoPreview.value = ''
  removeLogo.value = false
  error.value = ''
}

function edit(item) {
  editingId.value = item.id
  form.name = item.raw_name || item.name
  form.parent_id = item.parent_id || ''
  form.icon = resolveIconEmoji(item.icon || '💼')
  form.color = item.color || '#1e3a5f'
  form.is_active = item.is_active !== false
  form.show_on_home = item.show_on_home !== false
  form.logo_url = item.logo_url || ''
  logoFile.value = null
  logoPreview.value = ''
  removeLogo.value = false
}

function onLogo(e) {
  const file = e.target.files?.[0] || null
  logoFile.value = file
  removeLogo.value = false
  logoPreview.value = file ? URL.createObjectURL(file) : ''
}

function clearLogo() {
  logoFile.value = null
  logoPreview.value = ''
  form.logo_url = ''
  removeLogo.value = true
}

async function save() {
  saving.value = true
  error.value = ''
  try {
    const fd = new FormData()
    fd.append('name', form.name.trim())
    if (form.parent_id) fd.append('parent_id', form.parent_id)
    fd.append('icon', form.icon || '💼')
    fd.append('color', form.color || '#1e3a5f')
    fd.append('is_active', form.is_active ? '1' : '0')
    fd.append('show_on_home', form.show_on_home ? '1' : '0')
    if (logoFile.value) fd.append('logo', logoFile.value)
    if (removeLogo.value) fd.append('remove_logo', '1')

    if (editingId.value) {
      fd.append('_method', 'PUT')
      await adminApi.post(`/admin/job-classifications/${editingId.value}`, fd)
    } else {
      await adminApi.post('/admin/job-classifications', fd)
    }
    resetForm()
    await load()
    emit('changed')
  } catch (e) {
    error.value =
      e.response?.data?.message ||
      Object.values(e.response?.data?.errors || {})?.[0]?.[0] ||
      'ذخیره ناموفق بود.'
  } finally {
    saving.value = false
  }
}

async function move(item, direction) {
  try {
    const { data } = await adminApi.post('/admin/job-classifications/reorder', {
      id: item.id,
      direction,
    })
    tree.value = data.data?.tree || tree.value
    flat.value = data.data?.flat || flat.value
    emit('changed')
  } catch (e) {
    error.value = e.response?.data?.message || 'مرتب‌سازی ناموفق بود.'
  }
}

async function toggleHome(item) {
  try {
    const fd = new FormData()
    fd.append('show_on_home', item.show_on_home ? '0' : '1')
    fd.append('_method', 'PUT')
    await adminApi.post(`/admin/job-classifications/${item.id}`, fd)
    await load()
    emit('changed')
  } catch (e) {
    error.value = e.response?.data?.message || 'تغییر نمایش ناموفق بود.'
  }
}

async function remove(item) {
  if (!window.confirm(`حذف «${item.name}»؟`)) return
  try {
    await adminApi.delete(`/admin/job-classifications/${item.id}`)
    await load()
    emit('changed')
  } catch (e) {
    error.value = e.response?.data?.message || 'حذف ناموفق بود.'
  }
}
</script>

<style scoped>
.field {
  @apply h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-orange-400;
}
.btn-muted {
  @apply rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold;
}
.btn-orange {
  @apply rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50;
}
.act {
  @apply rounded-lg bg-white px-2 py-1 text-[11px] font-bold text-slate-700 shadow-sm disabled:opacity-30;
}
</style>
