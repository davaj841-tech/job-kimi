<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" >
    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
      <h3 class="mb-4 text-lg font-bold">{{ mode === 'charge' ? 'شارژ دستی' : 'کسر موجودی' }}</h3>
      <p v-if="user" class="mb-3 text-sm text-slate-500">{{ user.name }} — موجودی: {{ fa(user.balance) }} تومان</p>
      <form class="space-y-3" @submit.prevent="submit">
        <input v-model.number="amount" type="number" min="1000" required class="field" placeholder="مبلغ (تومان) *" />
        <textarea v-model="note" required class="field min-h-[80px] py-2" :placeholder="mode === 'charge' ? 'توضیحات' : 'دلیل کسر *'" />
        <p v-if="error" class="text-sm text-red-500">{{ error }}</p>
        <div class="flex justify-end gap-2">
          <button type="button" class="btn-muted" @click="$emit('close')">انصراف</button>
          <button type="submit" class="btn-orange" :disabled="saving">{{ saving ? '...' : 'ثبت' }}</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
  open: Boolean,
  mode: { type: String, default: 'charge' },
  user: { type: Object, default: null },
});
const emit = defineEmits(['close', 'submit']);

const amount = ref(10000);
const note = ref('');
const saving = ref(false);
const error = ref('');

watch(
  () => props.open,
  (v) => {
    if (v) {
      amount.value = 10000;
      note.value = props.mode === 'charge' ? 'شارژ دستی ادمین' : '';
      error.value = '';
    }
  }
);

function fa(n) {
  return new Intl.NumberFormat('fa-IR').format(Number(n || 0));
}

async function submit() {
  saving.value = true;
  error.value = '';
  try {
    emit('submit', { amount: amount.value, note: note.value });
  } catch (e) {
    error.value = e?.response?.data?.message || 'خطا';
  } finally {
    saving.value = false;
  }
}
</script>

<style scoped>
.field { @apply h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-orange-400; }
.btn-muted { @apply rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold text-slate-700; }
.btn-orange { @apply rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50; }
</style>
