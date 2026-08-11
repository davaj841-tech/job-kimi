<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
  >
    <div
      class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl"
    >
      <div class="mb-4 flex items-center justify-between">
        <h3 class="text-lg font-bold">
          {{ plan?.id ? 'ویرایش پلن' : 'پلن جدید' }}
        </h3>
        <button type="button" @click="$emit('close')">✕</button>
      </div>

      <form class="space-y-3" @submit.prevent="submit">
        <input
          v-model="form.name"
          required
          class="field"
          placeholder="نام پلن *"
        />
        <select v-model.number="form.duration_days" required class="field">
          <option :value="0">۰ روز (رایگان)</option>
          <option :value="30">۳۰ روز</option>
          <option :value="90">۹۰ روز</option>
          <option :value="180">۱۸۰ روز</option>
          <option :value="365">۳۶۵ روز</option>
        </select>
        <input
          v-model.number="form.price"
          type="number"
          min="0"
          required
          class="field"
          placeholder="قیمت (تومان) *"
        />

        <div>
          <label class="label mb-2 block">ویژگی‌ها</label>
          <div v-for="(f, i) in form.features" :key="i" class="mb-2 flex gap-2">
            <input
              v-model="form.features[i]"
              class="field flex-1"
              placeholder="ویژگی"
            />
            <button
              type="button"
              class="btn-muted px-3"
              @click="form.features.splice(i, 1)"
            >
              حذف
            </button>
          </div>
          <button
            type="button"
            class="text-sm font-bold text-orange-600"
            @click="form.features.push('')"
          >
            + افزودن ویژگی
          </button>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <label
            class="flex items-center justify-between rounded-xl border border-slate-200 px-3 text-sm"
          >
            فعال
            <StatusToggle v-model="form.is_active" />
          </label>
          <div>
            <label class="label mb-1 block">رنگ برچسب</label>
            <select v-model="form.badge_color" class="field">
              <option value="">بدون رنگ</option>
              <option
                v-for="c in badgeColors"
                :key="c.value"
                :value="c.value"
              >
                {{ c.label }}
              </option>
            </select>
            <div
              v-if="form.badge_color"
              class="mt-2 h-2 rounded-full"
              :style="{ background: form.badge_color }"
            />
          </div>
        </div>

        <p class="text-left text-xs text-slate-500" dir="rtl">
          قیمت نهایی:
          <span class="font-bold text-desk-orange"
            >{{ Number(form.price || 0).toLocaleString('fa-IR') }} تومان</span
          >
        </p>

        <p v-if="error" class="text-sm text-red-500">{{ error }}</p>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="btn-muted" @click="$emit('close')">
            انصراف
          </button>
          <button type="submit" class="btn-orange" :disabled="saving">
            {{ saving ? '...' : 'ذخیره' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, watch } from 'vue'
import StatusToggle from '../ui/StatusToggle.vue'

const props = defineProps({
  open: Boolean,
  plan: { type: Object, default: null },
})
const emit = defineEmits(['close', 'saved'])

const saving = ref(false)
const error = ref('')
const form = reactive(empty())

const badgeColors = [
  { value: '#ef394e', label: 'قرمز برند' },
  { value: '#f97316', label: 'نارنجی' },
  { value: '#0f2744', label: 'سرمه‌ای' },
  { value: '#16a34a', label: 'سبز' },
  { value: '#0284c7', label: 'آبی' },
  { value: '#ca8a04', label: 'طلایی' },
  { value: '#7c3aed', label: 'بنفش' },
  { value: '#64748b', label: 'خاکستری' },
]

watch(
  () => [props.open, props.plan],
  () => {
    if (!props.open) return
    Object.assign(form, props.plan?.id ? map(props.plan) : empty())
    error.value = ''
  }
)

function empty() {
  return {
    name: '',
    duration_days: 30,
    price: 0,
    features: ['دسترسی به همه آزمون‌ها'],
    is_active: true,
    badge_color: '',
  }
}

function map(p) {
  return {
    name: p.name || '',
    duration_days: p.duration_days || 30,
    price: Number(p.price || 0),
    features:
      Array.isArray(p.features) && p.features.length ? [...p.features] : [''],
    is_active: !!p.is_active,
    badge_color: p.badge_color || '',
  }
}

async function submit() {
  saving.value = true
  error.value = ''
  try {
    emit('saved', {
      id: props.plan?.id,
      payload: {
        name: form.name,
        duration_days: form.duration_days,
        price: form.price,
        features: form.features.filter((f) => f && f.trim()),
        is_active: form.is_active,
        badge_color: form.badge_color || null,
      },
    })
  } catch (e) {
    error.value = e?.response?.data?.message || 'خطا در ذخیره'
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.label {
  @apply text-xs font-bold text-slate-500;
}
.field {
  @apply h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-orange-400;
}
.btn-muted {
  @apply rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold text-slate-700;
}
.btn-orange {
  @apply rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50;
}
</style>
