<template>
  <div ref="root" class="relative w-full max-w-xl">
    <input
      ref="inputEl"
      v-model="q"
      type="search"
      class="h-10 w-full rounded-xl border px-4 text-sm outline-none"
      :class="
        dark
          ? 'border-white/20 bg-white/10 text-white placeholder:text-white/50 focus:border-desk-orange'
          : 'border-surface-line bg-white text-ink focus:border-brand'
      "
      placeholder="جستجو در آزمون، آگهی، PDF، بلاگ..."
      @focus="open = true"
      @keydown.down.prevent="move(1)"
      @keydown.up.prevent="move(-1)"
      @keydown.enter.prevent="onEnter"
      @keydown.esc="close"
    />
    <div
      v-if="open && (q.length >= 2 || recent.length || popular.length)"
      class="absolute inset-x-0 top-full z-50 mt-2 max-h-96 overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-xl"
    >
      <div v-if="loading" class="p-4 text-center text-xs text-slate-400">
        در حال جستجو...
      </div>
      <template v-else-if="q.length >= 2">
        <section
          v-for="group in groups"
          :key="group.key"
          class="border-b border-slate-50 p-2"
        >
          <div class="mb-1 flex items-center justify-between px-2">
            <p class="text-xs font-bold text-slate-500">{{ group.label }}</p>
          </div>
          <button
            v-for="(item, idx) in group.items"
            :key="item.type + '-' + item.id"
            type="button"
            class="block w-full rounded-lg px-2 py-2 text-right text-sm"
            :class="
              flatIndex(group.key, idx) === activeIndex
                ? 'bg-orange-50'
                : 'hover:bg-slate-50'
            "
            @mouseenter="activeIndex = flatIndex(group.key, idx)"
            @click="go(item)"
          >
            <span v-html="highlight(item.title)"></span>
            <span
              v-if="item.company_name"
              class="block text-[11px] text-slate-400"
              >{{ item.company_name }}</span
            >
          </button>
          <p
            v-if="!group.items.length"
            class="px-2 py-1 text-xs text-slate-400"
          >
            موردی نیست
          </p>
        </section>
        <RouterLink
          :to="`/exams?search=${encodeURIComponent(q)}`"
          class="block p-3 text-center text-sm font-bold text-brand hover:bg-slate-50"
          @click="close"
        >
          مشاهده همه نتایج
        </RouterLink>
      </template>
      <div v-else class="p-3">
        <p v-if="recent.length" class="mb-2 text-xs font-bold text-slate-500">
          جستجوهای اخیر
        </p>
        <button
          v-for="r in recent"
          :key="r"
          type="button"
          class="mb-1 block text-sm text-slate-700"
          @click="pickRecent(r)"
        >
          {{ r }}
        </button>
        <p class="mb-2 mt-3 text-xs font-bold text-slate-500">محبوب</p>
        <button
          v-for="p in popular"
          :key="p"
          type="button"
          class="mb-1 ml-2 inline-block rounded-full bg-slate-100 px-2 py-1 text-xs"
          @click="pickPopular(p)"
        >
          {{ p }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api/client'

defineProps({ dark: Boolean })

const router = useRouter()
const q = ref('')
const open = ref(false)
const loading = ref(false)
const results = ref({ exams: [], job_posts: [], pdfs: [], blog_posts: [] })
const popular = ref([])
const recent = ref([])
const root = ref(null)
const activeIndex = ref(-1)
let timer

const groups = computed(() => [
  {
    key: 'exams',
    label: 'آزمون‌ها',
    items: (results.value.exams || []).map((i) => ({ ...i, type: 'exam' })),
  },
  {
    key: 'jobs',
    label: 'آگهی‌ها',
    items: (results.value.job_posts || []).map((i) => ({ ...i, type: 'job' })),
  },
  {
    key: 'pdfs',
    label: 'فایل‌ها',
    items: (results.value.pdfs || []).map((i) => ({ ...i, type: 'pdf' })),
  },
  {
    key: 'blog',
    label: 'بلاگ',
    items: (results.value.blog_posts || []).map((i) => ({
      ...i,
      type: 'blog',
    })),
  },
])

const flatItems = computed(() => groups.value.flatMap((g) => g.items))

function flatIndex(groupKey, idx) {
  let n = 0
  for (const g of groups.value) {
    if (g.key === groupKey) return n + idx
    n += g.items.length
  }
  return -1
}

onMounted(() => {
  try {
    recent.value = JSON.parse(localStorage.getItem('recent_searches') || '[]')
  } catch {
    recent.value = []
  }
  document.addEventListener('click', onDoc)
  api
    .get('/search', { params: { q: '' } })
    .then(({ data }) => {
      popular.value = data.data?.popular || []
    })
    .catch(() => {})
})
onUnmounted(() => document.removeEventListener('click', onDoc))

watch(q, () => {
  clearTimeout(timer)
  activeIndex.value = -1
  if (q.value.trim().length < 2) return
  timer = setTimeout(search, 200)
})

function onDoc(e) {
  if (root.value && !root.value.contains(e.target)) open.value = false
}

async function search() {
  loading.value = true
  try {
    const { data } = await api.get('/search/suggestions', {
      params: { q: q.value.trim() },
    })
    results.value = data.data || results.value
  } finally {
    loading.value = false
  }
}

function pickRecent(term) {
  q.value = term
  search()
}

function pickPopular(term) {
  q.value = term
  search()
}

function highlight(text) {
  const term = q.value.trim().replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  if (!term) return escapeHtml(text)
  return escapeHtml(String(text)).replace(
    new RegExp(`(${term})`, 'gi'),
    '<mark class="bg-yellow-100 text-inherit rounded px-0.5">$1</mark>'
  )
}

function escapeHtml(s) {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
}

function move(delta) {
  const len = flatItems.value.length
  if (!len) return
  activeIndex.value = (activeIndex.value + delta + len) % len
}

function onEnter() {
  if (activeIndex.value >= 0 && flatItems.value[activeIndex.value]) {
    go(flatItems.value[activeIndex.value])
    return
  }
  goAll()
}

function saveRecent(term) {
  const list = [term, ...recent.value.filter((x) => x !== term)].slice(0, 8)
  recent.value = list
  localStorage.setItem('recent_searches', JSON.stringify(list))
}

function go(item) {
  saveRecent(q.value.trim())
  close()
  router.push(item.url || fallbackUrl(item))
}

function fallbackUrl(item) {
  if (item.slug && item.type === 'exam') return `/exams/${item.slug}`
  if (item.slug && item.type === 'blog') return `/blog/${item.slug}`
  if (item.type === 'job') return `/jobs/${item.id}`
  if (item.type === 'pdf') return `/pdfs/${item.id}`
  return '/'
}

function goAll() {
  if (!q.value.trim()) return
  saveRecent(q.value.trim())
  close()
  router.push(`/exams?search=${encodeURIComponent(q.value.trim())}`)
}

function close() {
  open.value = false
}
</script>
