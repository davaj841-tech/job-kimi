<template>
  <div class="px-4 py-4">
    <div class="card-soft mb-4 bg-gradient-to-l from-brand to-brand-dark p-5 text-white">
      <p class="text-xs text-white/80">موجودی کیف پول</p>
      <p class="mt-1 text-3xl font-black">{{ formatPrice(balance) }}</p>
    </div>

    <div class="card-soft mb-4 p-4">
      <label class="mb-2 block text-sm font-medium">شارژ کیف پول (ریال)</label>
      <input v-model.number="amount" type="number" class="input-field mb-3" min="10000" step="1000" />
      <label class="mb-2 block text-sm font-medium">درگاه پرداخت</label>
      <select v-model="gateway" class="input-field mb-3">
        <option v-for="g in gateways" :key="g.name" :value="g.name">{{ g.display_name }}</option>
      </select>
      <button class="btn-primary" :disabled="charging || amount < 10000 || !gateway" @click="charge">پرداخت آنلاین</button>
      <p v-if="message" class="mt-2 text-center text-xs text-brand">{{ message }}</p>
    </div>

    <h2 class="mb-2 section-title">تراکنش‌های اخیر</h2>
    <div class="space-y-2">
      <div v-for="tx in transactions" :key="tx.id" class="card-soft p-3 text-sm">
        <div class="flex items-center justify-between gap-2">
          <div>
            <p class="font-medium">{{ tx.description || tx.type }}</p>
            <p class="text-xs text-ink-muted">{{ statusLabel(tx.status) }}</p>
            <p v-if="tx.invoice_number" class="mt-0.5 text-xs text-ink-muted" dir="ltr">{{ tx.invoice_number }}</p>
          </div>
          <span class="shrink-0 font-bold" :class="tx.type === 'deposit' ? 'text-green-600' : 'text-brand'">
            {{ formatPrice(tx.amount) }}
          </span>
        </div>
        <button
          v-if="tx.status === 'success' && tx.type === 'purchase'"
          type="button"
          class="mt-2 text-xs font-bold text-brand underline"
          @click="downloadInvoice(tx)"
        >
          دانلود فاکتور
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import api from '../../api/client';

const balance = ref(0);
const transactions = ref([]);
const amount = ref(100000);
const charging = ref(false);
const message = ref('');
const gateways = ref([{ name: 'zarinpal', display_name: 'زرین‌پال' }]);
const gateway = ref('zarinpal');

function formatPrice(v) {
  return new Intl.NumberFormat('fa-IR').format(Number(v || 0)) + ' ریال';
}

function statusLabel(s) {
  return { success: 'موفق', pending: 'در انتظار', failed: 'ناموفق' }[s] || s;
}

async function loadGateways() {
  try {
    const { data } = await api.get('/payment-gateways');
    const list = data.data || [];
    if (list.length) {
      gateways.value = list;
      const def = list.find((g) => g.is_default) || list[0];
      gateway.value = def.name;
    }
  } catch {
    // keep default zarinpal
  }
}

async function load() {
  const { data } = await api.get('/wallet');
  balance.value = data.data?.balance || 0;
  transactions.value = data.data?.transactions || [];
}

onMounted(async () => {
  await Promise.all([load(), loadGateways()]);
});

async function charge() {
  charging.value = true;
  message.value = '';
  try {
    const { data } = await api.post('/wallet/charge', {
      amount: amount.value,
      gateway: gateway.value,
    });
    if (data.data?.payment_url) {
      window.location.href = data.data.payment_url;
    }
  } catch (e) {
    message.value = e.response?.data?.message || 'خطا در ایجاد پرداخت.';
  } finally {
    charging.value = false;
  }
}

async function downloadInvoice(tx) {
  try {
    const { data } = await api.get(`/transactions/${tx.id}/invoice`, { responseType: 'blob' });
    const url = URL.createObjectURL(data);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${tx.invoice_number || 'invoice-' + tx.id}.pdf`;
    a.click();
    URL.revokeObjectURL(url);
  } catch {
    message.value = 'دانلود فاکتور ناموفق بود.';
  }
}
</script>
