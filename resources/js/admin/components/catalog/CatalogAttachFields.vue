<template>
  <div class="space-y-3 rounded-xl border border-slate-200 p-3">
    <p class="text-sm font-bold text-slate-800">آزمون‌ها و فایل‌های فروش</p>
    <label class="flex items-center gap-2 text-sm">
      <input
        type="checkbox"
        class="rounded border-slate-300 text-orange-500"
        :checked="autoCatalog"
        @change="$emit('update:autoCatalog', $event.target.checked)"
      />
      افزودن خودکار بر اساس طبقه‌بندی
    </label>
    <p class="text-[11px] text-slate-400">
      آزمون و PDF همان رسته به‌صورت خودکار می‌آید. در کنار آن می‌توانید موردی را دستی انتخاب کنید.
    </p>

    <div>
      <label class="mb-1 block text-xs text-slate-500">انتخاب دستی آزمون</label>
      <input
        v-model="examQ"
        class="mb-2 h-9 w-full rounded-lg border border-slate-200 px-2 text-xs"
        placeholder="جستجوی آزمون…"
      />
      <div class="max-h-36 overflow-y-auto rounded-lg border border-slate-100 p-2">
        <label
          v-for="item in filteredExams"
          :key="item.id"
          class="flex cursor-pointer items-center gap-2 rounded px-1 py-1 text-xs hover:bg-slate-50"
        >
          <input
            type="checkbox"
            class="rounded border-slate-300 text-orange-500"
            :checked="examIds.includes(item.id)"
            @change="toggle('exam', item.id, $event.target.checked)"
          />
          <span class="truncate">{{ item.title }}</span>
        </label>
        <p
          v-if="!filteredExams.length"
          class="py-2 text-center text-[11px] text-slate-400"
        >
          آزمونی یافت نشد
        </p>
      </div>
    </div>

    <div>
      <label class="mb-1 block text-xs text-slate-500">انتخاب دستی فایل PDF</label>
      <input
        v-model="pdfQ"
        class="mb-2 h-9 w-full rounded-lg border border-slate-200 px-2 text-xs"
        placeholder="جستجوی فایل…"
      />
      <div class="max-h-36 overflow-y-auto rounded-lg border border-slate-100 p-2">
        <label
          v-for="item in filteredPdfs"
          :key="item.id"
          class="flex cursor-pointer items-center gap-2 rounded px-1 py-1 text-xs hover:bg-slate-50"
        >
          <input
            type="checkbox"
            class="rounded border-slate-300 text-orange-500"
            :checked="pdfIds.includes(item.id)"
            @change="toggle('pdf', item.id, $event.target.checked)"
          />
          <span class="truncate">{{ item.title }}</span>
        </label>
        <p
          v-if="!filteredPdfs.length"
          class="py-2 text-center text-[11px] text-slate-400"
        >
          فایلی یافت نشد
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import adminApi from '../../api/client'

const props = defineProps({
  autoCatalog: { type: Boolean, default: true },
  examIds: { type: Array, default: () => [] },
  pdfIds: { type: Array, default: () => [] },
})
const emit = defineEmits(['update:autoCatalog', 'update:examIds', 'update:pdfIds'])

const exams = ref([])
const pdfs = ref([])
const examQ = ref('')
const pdfQ = ref('')

const filteredExams = computed(() => {
  const q = examQ.value.trim()
  const list = exams.value
  if (!q) return list.slice(0, 80)
  return list.filter((i) => String(i.title || '').includes(q)).slice(0, 80)
})

const filteredPdfs = computed(() => {
  const q = pdfQ.value.trim()
  const list = pdfs.value
  if (!q) return list.slice(0, 80)
  return list.filter((i) => String(i.title || '').includes(q)).slice(0, 80)
})

function unwrap(payload) {
  const d = payload?.data?.data ?? payload?.data ?? []
  if (Array.isArray(d)) return d
  if (Array.isArray(d?.data)) return d.data
  return []
}

function toggle(kind, id, on) {
  const key = kind === 'exam' ? 'examIds' : 'pdfIds'
  const cur = [...(props[key] || [])].map(Number)
  const next = on ? [...new Set([...cur, Number(id)])] : cur.filter((x) => x !== Number(id))
  emit(kind === 'exam' ? 'update:examIds' : 'update:pdfIds', next)
}

onMounted(async () => {
  try {
    const [e, p] = await Promise.all([
      adminApi.get('/admin/exams', { params: { per_page: 100, status: 'published' } }),
      adminApi.get('/admin/pdf-products', { params: { per_page: 100, is_active: 1 } }),
    ])
    exams.value = unwrap(e).map((i) => ({ id: Number(i.id), title: i.title }))
    pdfs.value = unwrap(p).map((i) => ({ id: Number(i.id), title: i.title }))
  } catch {
    exams.value = []
    pdfs.value = []
  }
})
</script>
