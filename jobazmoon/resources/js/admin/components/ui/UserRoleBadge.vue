<template>
  <span
    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-bold"
    :class="classes"
  >
    {{ label }}
  </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  role: { type: String, required: true },
});

const map = {
  admin: { label: 'مدیر', classes: 'bg-purple-100 text-purple-700' },
  operator: { label: 'اپراتور', classes: 'bg-blue-100 text-blue-700' },
  jobseeker: { label: 'کارجو', classes: 'bg-green-100 text-green-700' },
  employer: { label: 'کارفرما', classes: 'bg-orange-100 text-orange-700' },
};

const label = computed(() => map[props.role]?.label || props.role);
const classes = computed(() => map[props.role]?.classes || 'bg-slate-100 text-slate-600');
</script>
