<template>
  <div class="grid grid-cols-3 gap-2">
    <select class="field" :value="parts.jy || ''" @change="setPart('jy', $event.target.value)">
      <option value="">سال</option>
      <option v-for="y in years" :key="y" :value="y">{{ fa(y) }}</option>
    </select>
    <select class="field" :value="parts.jm || ''" @change="setPart('jm', $event.target.value)">
      <option value="">ماه</option>
      <option v-for="(m, i) in months" :key="m" :value="i + 1">{{ m }}</option>
    </select>
    <select class="field" :value="parts.jd || ''" @change="setPart('jd', $event.target.value)">
      <option value="">روز</option>
      <option v-for="d in days" :key="d" :value="d">{{ fa(d) }}</option>
    </select>
  </div>
</template>

<script setup>
import { computed, reactive, watch } from 'vue';
import {
  gregorianIsoToJalaliParts,
  jalaliMonthLength,
  jalaliPartsToIso,
  JALALI_MONTHS,
  toJalali,
} from '../../../utils/jalali';

const props = defineProps({
  modelValue: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue']);

const now = toJalali(new Date().getFullYear(), new Date().getMonth() + 1, new Date().getDate());
const years = Array.from({ length: 20 }, (_, i) => now.jy - 2 + i);
const months = JALALI_MONTHS;

const parts = reactive({ jy: '', jm: '', jd: '' });

watch(
  () => props.modelValue,
  (val) => {
    const p = gregorianIsoToJalaliParts(val);
    if (!p) {
      if (!val) {
        parts.jy = '';
        parts.jm = '';
        parts.jd = '';
      }
      return;
    }
    parts.jy = p.jy;
    parts.jm = p.jm;
    parts.jd = p.jd;
  },
  { immediate: true }
);

const days = computed(() => {
  if (!parts.jy || !parts.jm) return Array.from({ length: 31 }, (_, i) => i + 1);
  const len = jalaliMonthLength(Number(parts.jy), Number(parts.jm));
  return Array.from({ length: len }, (_, i) => i + 1);
});

function fa(n) {
  return String(n).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
}

function setPart(key, value) {
  parts[key] = value ? Number(value) : '';
  if (parts.jy && parts.jm && parts.jd) {
    const max = jalaliMonthLength(Number(parts.jy), Number(parts.jm));
    if (Number(parts.jd) > max) parts.jd = max;
    emit('update:modelValue', jalaliPartsToIso(parts));
  } else {
    emit('update:modelValue', '');
  }
}
</script>

<style scoped>
.field { @apply h-10 w-full rounded-xl border border-slate-200 px-2 text-sm outline-none focus:border-orange-400; }
</style>
