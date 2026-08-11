<template>
  <div class="rounded-xl border border-surface-line bg-white p-3">
    <p class="mb-2 text-sm font-medium text-ink-soft">کد تخفیف دارید؟</p>
    <div v-if="!applied" class="flex gap-2">
      <input
        v-model="code"
        class="input-field flex-1 text-left uppercase"
        dir="ltr"
        placeholder="کد تخفیف"
        @keyup.enter="apply"
      />
      <button
        type="button"
        class="btn-ghost shrink-0 border border-surface-line px-4"
        :disabled="loading || !code.trim()"
        @click="apply"
      >
        اعمال
      </button>
    </div>
    <div v-else class="space-y-1 text-sm">
      <p class="font-medium text-green-700">کد {{ applied.code }} اعمال شد</p>
      <p class="text-ink-muted">
        تخفیف: {{ formatPrice(applied.discount_amount) }}
      </p>
      <p class="font-bold">
        مبلغ نهایی: {{ formatPrice(applied.final_amount) }}
      </p>
      <button type="button" class="text-xs text-brand underline" @click="clear">
        حذف کد تخفیف
      </button>
    </div>
    <p v-if="error" class="mt-2 text-xs text-brand">{{ error }}</p>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import api from '../api/client'

const props = defineProps({
  amount: { type: Number, required: true },
  type: { type: String, required: true }, // subscription | pdf
})

const emit = defineEmits(['update:coupon'])

const code = ref('')
const applied = ref(null)
const loading = ref(false)
const error = ref('')

function formatPrice(v) {
  return new Intl.NumberFormat('fa-IR').format(Number(v || 0)) + ' ریال'
}

watch(
  () => props.amount,
  () => {
    if (applied.value) clear()
  }
)

async function apply() {
  error.value = ''
  loading.value = true
  try {
    const { data } = await api.post('/coupons/validate', {
      code: code.value.trim(),
      amount: Number(props.amount),
      type: props.type,
    })
    applied.value = {
      code: data.data.code,
      discount_amount: data.data.discount_amount,
      final_amount: data.data.final_amount,
    }
    emit('update:coupon', applied.value)
  } catch (e) {
    error.value =
      e.response?.data?.message || 'کد تخفیف نامعتبر یا منقضی شده است'
    applied.value = null
    emit('update:coupon', null)
  } finally {
    loading.value = false
  }
}

function clear() {
  applied.value = null
  code.value = ''
  error.value = ''
  emit('update:coupon', null)
}

defineExpose({ clear })
</script>
