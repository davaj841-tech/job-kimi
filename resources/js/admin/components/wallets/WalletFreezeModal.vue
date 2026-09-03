<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
  >
    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
      <h3 class="mb-4 text-lg font-bold">
        {{ mode === 'freeze' ? 'مسدود کردن کیف پول' : 'فعال‌سازی کیف پول' }}
      </h3>
      <p v-if="user" class="mb-3 text-sm text-slate-500">
        {{ user.name }} — موجودی: {{ fa(user.balance) }} ریال
      </p>
      <p
        v-if="mode === 'freeze'"
        class="mb-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800"
      >
        پس از مسدود شدن، کاربر نمی‌تواند از کیف پول خرید یا شارژ کند.
      </p>
      <form class="space-y-3" @submit.prevent="submit">
        <textarea
          v-model="reason"
          required
          class="field min-h-[80px] py-2"
          placeholder="دلیل (اجباری) *"
        />
        <p v-if="error" class="text-sm text-red-500">{{ error }}</p>
        <div class="flex justify-end gap-2">
          <button
            type="button"
            class="btn-muted"
            :disabled="saving"
            @click="$emit('close')"
          >
            انصراف
          </button>
          <button
            type="submit"
            class="btn-orange"
            :class="mode === 'freeze' ? 'bg-red-600' : ''"
            :disabled="saving || !reason.trim()"
          >
            {{
              saving ? '...' : mode === 'freeze' ? 'مسدود کردن' : 'فعال‌سازی'
            }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  open: Boolean,
  mode: { type: String, default: 'freeze' },
  user: { type: Object, default: null },
})
const emit = defineEmits(['close', 'submit'])

const reason = ref('')
const saving = ref(false)
const error = ref('')

watch(
  () => props.open,
  (v) => {
    if (v) {
      reason.value = ''
      error.value = ''
      saving.value = false
    }
  }
)

function fa(n) {
  return new Intl.NumberFormat('fa-IR').format(Number(n || 0))
}

function submit() {
  if (!reason.value.trim() || saving.value) return
  saving.value = true
  error.value = ''
  emit('submit', { reason: reason.value.trim() })
}

defineExpose({
  setError(msg) {
    error.value = msg
    saving.value = false
  },
  setSaving(v) {
    saving.value = v
  },
})
</script>

<style scoped>
.field {
  @apply h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-orange-400;
}
.btn-muted {
  @apply rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold text-slate-700 disabled:opacity-50;
}
.btn-orange {
  @apply rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50;
}
</style>
