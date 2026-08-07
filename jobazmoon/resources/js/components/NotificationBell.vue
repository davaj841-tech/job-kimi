<template>
  <div class="relative" ref="root">
    <button
      type="button"
      class="relative rounded-lg p-2 transition"
      :class="dark ? 'text-white/85 hover:bg-white/10' : 'text-ink hover:bg-slate-100'"
      aria-label="اعلان‌ها"
      @click="toggle"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        width="20"
        height="20"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
      >
        <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
        <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
      </svg>
      <span
        v-if="store.unreadCount > 0"
        class="absolute -left-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white"
      >
        {{ store.unreadCount > 9 ? '۹+' : toFa(store.unreadCount) }}
      </span>
    </button>

    <div
      v-if="open"
      class="absolute left-0 top-full z-50 mt-2 w-80 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl"
    >
      <div class="flex items-center justify-between border-b px-3 py-2">
        <p class="text-sm font-bold text-slate-800">اعلان‌ها</p>
        <button type="button" class="text-xs font-bold text-brand" @click="markAll">همه را خواندم</button>
      </div>
      <div class="max-h-80 overflow-y-auto">
        <button
          v-for="n in preview"
          :key="n.id"
          type="button"
          class="block w-full border-b border-slate-50 px-3 py-2.5 text-right transition hover:bg-slate-50"
          :class="!n.is_read ? 'bg-orange-50/50' : ''"
          @click="openItem(n)"
        >
          <p class="text-sm font-bold text-slate-800">{{ n.title }}</p>
          <p class="mt-0.5 line-clamp-2 text-xs text-slate-500">{{ n.message }}</p>
          <p class="mt-1 text-[10px] text-slate-400">{{ timeAgo(n.created_at) }}</p>
        </button>
        <p v-if="!preview.length" class="px-3 py-8 text-center text-xs text-slate-400">اعلانی نیست</p>
      </div>
      <RouterLink
        to="/notifications"
        class="block border-t py-2.5 text-center text-xs font-bold text-brand"
        @click="open = false"
      >
        مشاهده همه
      </RouterLink>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useNotificationsStore } from '../stores/notifications';
import { toFaDigits } from '../utils/format';

defineProps({
  dark: { type: Boolean, default: false },
});

const store = useNotificationsStore();
const router = useRouter();
const open = ref(false);
const root = ref(null);

const preview = computed(() => store.items.slice(0, 6));

onMounted(async () => {
  await store.fetchUnreadCount();
  document.addEventListener('click', onDocClick);
});
onUnmounted(() => document.removeEventListener('click', onDocClick));

function onDocClick(e) {
  if (root.value && !root.value.contains(e.target)) open.value = false;
}

async function toggle() {
  open.value = !open.value;
  if (open.value) await store.fetchNotifications(1);
}

async function markAll() {
  await store.markAllRead();
}

async function openItem(n) {
  if (!n.is_read) await store.markRead(n.id);
  open.value = false;
  if (n.link) router.push(n.link.replace(/^https?:\/\/[^/]+/, '') || '/notifications');
  else router.push('/notifications');
}

function toFa(n) {
  return toFaDigits(n);
}

function timeAgo(iso) {
  if (!iso) return '';
  const diff = Date.now() - new Date(iso).getTime();
  const m = Math.floor(diff / 60000);
  if (m < 1) return 'همین الان';
  if (m < 60) return `${toFaDigits(m)} دقیقه پیش`;
  const h = Math.floor(m / 60);
  if (h < 24) return `${toFaDigits(h)} ساعت پیش`;
  return `${toFaDigits(Math.floor(h / 24))} روز پیش`;
}
</script>
