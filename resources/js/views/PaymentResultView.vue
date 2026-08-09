<template>
  <div class="flex min-h-[70dvh] flex-col items-center justify-center px-6 text-center">
    <LoadingSpinner v-if="loading" />
    <template v-else>
      <div
        class="mb-4 flex h-16 w-16 items-center justify-center rounded-full text-2xl"
        :class="ok ? 'bg-emerald-50 text-emerald-600' : 'bg-brand-soft text-brand'"
      >
        {{ ok ? '✓' : '!' }}
      </div>
      <h1 class="mb-2 text-lg font-black">{{ title }}</h1>
      <p class="mb-6 text-sm leading-6 text-ink-muted">{{ message }}</p>
      <div class="flex w-full max-w-xs flex-col gap-2">
        <RouterLink :to="primaryTo" class="btn-primary">{{ primaryLabel }}</RouterLink>
        <RouterLink to="/" class="btn-ghost border border-surface-line">خانه</RouterLink>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import api from '../api/client';
import LoadingSpinner from '../components/LoadingSpinner.vue';
import { apiErrorMessage } from '../utils/format';

const route = useRoute();
const loading = ref(true);
const ok = ref(false);
const message = ref('');

const type = computed(() => route.meta.paymentType || 'wallet');

const title = computed(() => (ok.value ? 'پرداخت موفق' : 'پرداخت ناموفق'));
const primaryTo = computed(() => {
  if (type.value === 'subscription') return '/subscription';
  if (type.value === 'pdf') return '/pdfs';
  return '/wallet';
});
const primaryLabel = computed(() => {
  if (type.value === 'subscription') return 'مشاهده اشتراک';
  if (type.value === 'pdf') return 'فروشگاه PDF';
  return 'کیف پول';
});

onMounted(async () => {
  const authority =
    route.query.Authority ||
    route.query.authority ||
    route.query.trans_id ||
    route.query.id ||
    '';
  const status = route.query.Status || route.query.status || '';

  if (!authority) {
    message.value = 'اطلاعات پرداخت ناقص است.';
    loading.value = false;
    return;
  }

  try {
    let endpoint = '/wallet/verify';
    if (type.value === 'subscription') endpoint = '/subscription/verify';
    if (type.value === 'pdf' && route.query.pdf_id) {
      endpoint = `/pdf-products/${route.query.pdf_id}/verify`;
    }

    const { data } = await api.post(endpoint, null, {
      params: {
        Authority: authority,
        Status: status,
        trans_id: route.query.trans_id,
        id: route.query.id,
        order_id: route.query.order_id,
        status: route.query.status,
      },
    });
    ok.value = Boolean(data.success);
    message.value = data.message || (ok.value ? 'تراکنش با موفقیت انجام شد.' : 'پرداخت تایید نشد.');
  } catch (e) {
    ok.value = false;
    message.value = apiErrorMessage(e, 'تایید پرداخت ناموفق بود.');
  } finally {
    loading.value = false;
  }
});
</script>
