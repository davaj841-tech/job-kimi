<template>
  <section class="relative z-10 -mt-6">
    <div class="desk-container">
      <div class="grid grid-cols-4 gap-4 rounded-2xl bg-white p-4 shadow-desk">
        <div
          v-for="item in stats"
          :key="item.label"
          class="flex items-center gap-3 rounded-xl px-3 py-3"
        >
          <div
            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl"
            :class="item.bg"
          >
            <DesktopIcon :name="item.icon" :size="22" :class="item.color" />
          </div>
          <div class="text-right">
            <p class="text-xl font-black text-desk-text">{{ item.value }}</p>
            <p class="text-sm text-desk-muted">{{ item.label }}</p>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue';
import { toFaDigits } from '../../utils/format';
import DesktopIcon from '../DesktopIcon.vue';

const props = defineProps({
  jobsCount: { type: Number, default: 0 },
  examsCount: { type: Number, default: 0 },
  filesCount: { type: Number, default: 0 },
  postsCount: { type: Number, default: 0 },
});

const stats = computed(() => [
  {
    label: 'آگهی استخدام',
    value: toFaDigits(props.jobsCount || '—'),
    icon: 'briefcase',
    bg: 'bg-orange-50',
    color: 'text-desk-orange',
  },
  {
    label: 'آزمون آنلاین',
    value: toFaDigits(props.examsCount || '—'),
    icon: 'clipboard',
    bg: 'bg-blue-50',
    color: 'text-desk-blue',
  },
  {
    label: 'فایل آموزشی',
    value: toFaDigits(props.filesCount || '—'),
    icon: 'file',
    bg: 'bg-emerald-50',
    color: 'text-desk-green',
  },
  {
    label: 'مقاله بلاگ',
    value: toFaDigits(props.postsCount || '—'),
    icon: 'book',
    bg: 'bg-violet-50',
    color: 'text-violet-600',
  },
]);
</script>
