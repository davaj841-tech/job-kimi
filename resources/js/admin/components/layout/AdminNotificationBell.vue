<template>
  <div ref="root" class="relative">
    <button
      type="button"
      class="relative rounded-lg bg-slate-100 p-2 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
      aria-label="اعلان‌ها"
      @click="toggle"
    >
      <span class="text-sm" aria-hidden="true">🔔</span>
      <span
        v-if="unread > 0"
        class="absolute -left-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white"
      >
        {{ unread > 9 ? '۹+' : unread }}
      </span>
    </button>

    <div
      v-if="open"
      class="absolute left-0 top-full z-50 mt-2 w-80 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900"
    >
      <div
        class="flex items-center justify-between border-b px-3 py-2 dark:border-slate-700"
      >
        <p class="text-sm font-bold text-slate-800 dark:text-slate-100">
          اعلان‌ها
        </p>
        <button
          type="button"
          class="text-xs font-bold text-orange-600"
          @click="markAll"
        >
          همه را خواندم
        </button>
      </div>
      <div class="max-h-80 overflow-y-auto">
        <button
          v-for="n in items"
          :key="n.id"
          type="button"
          class="block w-full border-b border-slate-50 px-3 py-2.5 text-right transition hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800"
          :class="!n.is_read ? 'bg-orange-50/50 dark:bg-orange-950/30' : ''"
          @click="openItem(n)"
        >
          <p class="text-sm font-bold text-slate-800 dark:text-slate-100">
            {{ n.title }}
          </p>
          <p class="mt-0.5 line-clamp-2 text-xs text-slate-500">
            {{ n.message }}
          </p>
        </button>
        <p
          v-if="!items.length"
          class="px-3 py-8 text-center text-xs text-slate-400"
        >
          اعلانی نیست
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import adminApi from '../../api/client'

const router = useRouter()
const open = ref(false)
const root = ref(null)
const unread = ref(0)
const items = ref([])
let poll

onMounted(() => {
  void refreshCount()
  poll = setInterval(() => refreshCount(), 30000)
  document.addEventListener('click', onDocClick)
})

onUnmounted(() => {
  if (poll) clearInterval(poll)
  document.removeEventListener('click', onDocClick)
})

function onDocClick(e) {
  if (root.value && !root.value.contains(e.target)) open.value = false
}

async function refreshCount() {
  try {
    const { data } = await adminApi.get('/notifications/unread-count')
    unread.value = data.data?.count ?? 0
  } catch {
    unread.value = 0
  }
}

async function toggle() {
  open.value = !open.value
  if (!open.value) return
  try {
    const { data } = await adminApi.get('/notifications', {
      params: { per_page: 8 },
    })
    const payload = data.data
    items.value = payload?.data || payload || []
  } catch {
    items.value = []
  }
}

async function markAll() {
  try {
    await adminApi.post('/notifications/read-all')
    unread.value = 0
    items.value = items.value.map((n) => ({ ...n, is_read: true }))
  } catch {
    /* ignore */
  }
}

async function openItem(n) {
  try {
    if (!n.is_read) await adminApi.post(`/notifications/${n.id}/read`)
  } catch {
    /* ignore */
  }
  unread.value = Math.max(0, unread.value - (n.is_read ? 0 : 1))
  open.value = false
  const link = n.link || n.data?.link
  if (typeof link === 'string' && link.startsWith('/admin')) {
    router.push(link)
  } else {
    router.push('/admin/dashboard')
  }
}
</script>
