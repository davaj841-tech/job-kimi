<template>
  <div class="mx-auto max-w-xl px-4 py-6">
    <h1 class="mb-4 text-xl font-black">تیکت جدید</h1>
    <form class="space-y-3" @submit.prevent="submit">
      <select v-model="form.category" required class="field">
        <option value="support">پشتیبانی فنی</option>
        <option value="pre_sale">سوال قبل از خرید</option>
        <option value="bug">گزارش مشکل</option>
        <option value="suggestion">پیشنهاد</option>
      </select>
      <input v-model="form.subject" required class="field" placeholder="موضوع *" />
      <select v-model="form.priority" class="field">
        <option value="low">اولویت کم</option>
        <option value="medium">متوسط</option>
        <option value="high">بالا</option>
      </select>
      <textarea v-model="form.message" required rows="6" class="field min-h-[140px] py-2" placeholder="پیام *" />
      <p v-if="error" class="text-sm text-red-500">{{ error }}</p>
      <button class="btn-primary" :disabled="saving">{{ saving ? '...' : 'ارسال' }}</button>
    </form>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../../api/client';
import { apiErrorMessage } from '../../utils/format';

const router = useRouter();
const saving = ref(false);
const error = ref('');
const form = reactive({ subject: '', message: '', category: 'support', priority: 'medium' });

async function submit() {
  saving.value = true;
  error.value = '';
  try {
    const { data } = await api.post('/tickets', form);
    router.replace(`/support/${data.data.id}`);
  } catch (e) {
    error.value = apiErrorMessage(e);
  } finally {
    saving.value = false;
  }
}
</script>

<style scoped>
.field { @apply h-11 w-full rounded-xl border border-surface-line px-3 text-sm outline-none focus:border-brand; }
</style>
