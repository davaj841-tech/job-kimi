<template>
  <div class="mx-auto max-w-2xl px-4 py-6">
    <div class="mb-4 flex items-center justify-between gap-3">
      <h1 class="text-xl font-black text-ink">اعلان‌ها</h1>
      <button class="text-sm font-bold text-brand" @click="store.markAllRead()">همه را خواندم</button>
    </div>

    <div class="mb-4 flex gap-2">
      <button class="tab" :class="store.filter === 'all' ? 'active' : ''" @click="setFilter('all')">همه</button>
      <button class="tab" :class="store.filter === 'unread' ? 'active' : ''" @click="setFilter('unread')">خوانده‌نشده</button>
    </div>

    <SkeletonCard v-if="store.loading" :count="4" />
    <div v-else class="space-y-2">
      <button
        v-for="n in store.items"
        :key="n.id"
        type="button"
        class="card-soft w-full p-3 text-right transition"
        :class="!n.is_read ? 'ring-1 ring-orange-200' : ''"
        @click="openItem(n)"
      >
        <div class="flex items-start justify-between gap-2">
          <div>
            <p class="text-sm font-bold">{{ n.title }}</p>
            <p class="mt-1 text-xs leading-6 text-ink-muted">{{ n.message }}</p>
          </div>
          <span v-if="!n.is_read" class="mt-1 h-2 w-2 shrink-0 rounded-full bg-red-500" />
        </div>
      </button>
      <p v-if="!store.items.length" class="py-10 text-center text-sm text-ink-muted">اعلانی یافت نشد</p>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import SkeletonCard from '../components/ui/SkeletonCard.vue';
import { useNotificationsStore } from '../stores/notifications';

const store = useNotificationsStore();
const router = useRouter();

onMounted(() => store.fetchNotifications(1));

function setFilter(f) {
  store.filter = f;
  store.fetchNotifications(1);
}

async function openItem(n) {
  if (!n.is_read) await store.markRead(n.id);
  if (n.link) {
    const path = String(n.link).replace(/^https?:\/\/[^/]+/, '');
    router.push(path || '/notifications');
  }
}
</script>

<style scoped>
.tab { @apply rounded-xl bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600; }
.tab.active { @apply bg-brand text-white; }
</style>
