<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
    <div class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
      <div class="mb-4 flex items-center justify-between">
        <h3 class="text-lg font-bold text-slate-800">➕ افزودن کاربر جدید</h3>
        <button type="button" class="text-slate-400 hover:text-slate-600" @click="$emit('close')">✕</button>
      </div>

      <form class="space-y-4" @submit.prevent="submit">
        <div>
          <label class="label">نام و نام خانوادگی *</label>
          <input v-model="form.name" class="field" required placeholder="نام کامل" />
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="label">نام کاربری *</label>
            <input v-model="form.username" class="field text-left" dir="ltr" required placeholder="username" />
          </div>
          <div>
            <label class="label">رمز عبور *</label>
            <input v-model="form.password" type="password" class="field" required placeholder="حداقل ۸ کاراکتر" />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="label">موبایل</label>
            <input v-model="form.mobile" class="field text-left tracking-widest" dir="ltr" maxlength="11" placeholder="09123456789" />
          </div>
          <div>
            <label class="label">ایمیل</label>
            <input v-model="form.email" type="email" class="field text-left" dir="ltr" placeholder="you@example.com" />
          </div>
        </div>
        <p class="-mt-1 text-[11px] text-slate-400">حداقل یکی از موبایل یا ایمیل الزامی است.</p>

        <div>
          <label class="label">استان *</label>
          <select v-model="form.province" class="field" required>
            <option value="">انتخاب استان</option>
            <option v-for="p in IRAN_PROVINCES" :key="p" :value="p">{{ p }}</option>
          </select>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="label">نقش *</label>
            <select v-model="form.role" class="field" required>
              <option value="jobseeker">کارجو</option>
              <option value="employer">کارفرما</option>
              <option value="operator">اپراتور</option>
              <option value="admin">مدیر</option>
            </select>
          </div>
          <div>
            <label class="label">وضعیت</label>
            <select v-model="form.status" class="field">
              <option value="active">فعال</option>
              <option value="blocked">مسدود</option>
            </select>
          </div>
        </div>

        <p v-if="error" class="text-sm text-red-500">{{ error }}</p>

        <div class="flex justify-end gap-2">
          <button type="button" class="rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold" @click="$emit('close')">انصراف</button>
          <button type="submit" class="rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50" :disabled="saving">
            {{ saving ? '...' : 'ایجاد کاربر' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';
import { IRAN_PROVINCES } from '../../../utils/provinces';

const props = defineProps({ open: Boolean });
const emit = defineEmits(['close', 'created']);

const saving = ref(false);
const error = ref('');
const form = reactive(emptyForm());

function emptyForm() {
  return {
    name: '',
    username: '',
    password: '',
    mobile: '',
    email: '',
    province: '',
    role: 'jobseeker',
    status: 'active',
  };
}

watch(
  () => props.open,
  (v) => {
    if (v) {
      Object.assign(form, emptyForm());
      error.value = '';
    }
  }
);

async function submit() {
  error.value = '';
  if (!form.mobile.trim() && !form.email.trim()) {
    error.value = 'حداقل یکی از موبایل یا ایمیل الزامی است.';
    return;
  }
  if (!form.province) {
    error.value = 'انتخاب استان الزامی است.';
    return;
  }
  if (form.password.length < 8) {
    error.value = 'رمز عبور باید حداقل ۸ کاراکتر باشد.';
    return;
  }
  saving.value = true;
  try {
    const payload = {
      name: form.name.trim(),
      username: form.username.trim(),
      password: form.password,
      role: form.role,
      status: form.status,
    };
    if (form.mobile.trim()) payload.mobile = form.mobile.trim();
    if (form.email.trim()) payload.email = form.email.trim();
    if (form.province) payload.province = form.province;
    emit('created', payload);
  } finally {
    saving.value = false;
  }
}

function markFailed(message) {
  saving.value = false;
  error.value = message || 'ایجاد کاربر ناموفق بود.';
}

defineExpose({ markFailed });
</script>

<style scoped>
.label {
  @apply mb-1 block text-xs font-medium text-slate-600;
}
.field {
  @apply h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-orange-400;
}
</style>
