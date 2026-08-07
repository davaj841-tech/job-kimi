<template>
  <div class="px-4 py-4">
    <LoadingSpinner v-if="loading" />
    <template v-else-if="pdf">
      <h1 class="mb-2 text-xl font-black">{{ pdf.title }}</h1>
      <p class="price mb-3 text-lg">
        <template v-if="coupon">{{ formatPrice(coupon.final_amount) }}</template>
        <template v-else>{{ formatPrice(pdf.price) }}</template>
      </p>
      <p v-if="coupon" class="mb-2 text-xs text-ink-muted line-through">{{ formatPrice(pdf.price) }}</p>
      <p class="mb-4 text-sm leading-7 text-ink-soft">{{ pdf.description }}</p>

      <CouponBox
        v-if="!pdf.is_purchased"
        class="mb-3"
        :amount="Number(pdf.price)"
        type="pdf"
        @update:coupon="coupon = $event"
      />

      <div v-if="!pdf.is_purchased && gateways.length" class="mb-3">
        <label class="mb-2 block text-sm font-medium">درگاه پرداخت آنلاین</label>
        <select v-model="gateway" class="input-field">
          <option v-for="g in gateways" :key="g.name" :value="g.name">{{ g.display_name }}</option>
        </select>
      </div>

      <button v-if="!pdf.is_purchased" class="btn-primary mb-2" :disabled="buying" @click="buy('wallet')">
        خرید با کیف پول
      </button>
      <button v-if="!pdf.is_purchased" class="btn-ghost w-full border border-surface-line" :disabled="buying" @click="buy(gateway)">
        پرداخت آنلاین
      </button>
      <a v-else :href="pdf.download_url" class="btn-primary">دانلود فایل</a>
      <p v-if="message" class="mt-3 text-center text-sm text-brand">{{ message }}</p>
    </template>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import api from '../../api/client';
import CouponBox from '../../components/CouponBox.vue';
import LoadingSpinner from '../../components/LoadingSpinner.vue';

const route = useRoute();
const pdf = ref(null);
const loading = ref(true);
const buying = ref(false);
const message = ref('');
const coupon = ref(null);
const gateways = ref([{ name: 'zarinpal', display_name: 'زرین‌پال' }]);
const gateway = ref('zarinpal');

function formatPrice(v) {
  return new Intl.NumberFormat('fa-IR').format(Number(v)) + ' ریال';
}

async function load() {
  const { data } = await api.get(`/pdf-products/${route.params.id}`);
  pdf.value = data.data;
}

onMounted(async () => {
  try {
    await load();
    const { data } = await api.get('/payment-gateways');
    const list = data.data || [];
    if (list.length) {
      gateways.value = list;
      gateway.value = (list.find((g) => g.is_default) || list[0]).name;
    }
  } finally {
    loading.value = false;
  }
});

async function buy(method) {
  buying.value = true;
  message.value = '';
  try {
    const payload = { payment_method: method };
    if (coupon.value?.code) payload.coupon_code = coupon.value.code;
    const { data } = await api.post(`/pdf-products/${route.params.id}/purchase`, payload);
    if (data.data?.payment_url) {
      window.location.href = data.data.payment_url;
      return;
    }
    message.value = data.message || 'خرید موفق بود.';
    await load();
  } catch (e) {
    message.value = e.response?.data?.message || 'خرید ناموفق بود.';
  } finally {
    buying.value = false;
  }
}
</script>
