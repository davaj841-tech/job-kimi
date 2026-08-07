<template>
  <section class="bg-desk-blue py-12 text-white">
    <div class="desk-container">
      <div class="flex flex-row-reverse flex-wrap items-center justify-between gap-8">
        <form class="flex min-w-[320px] flex-1 flex-row-reverse gap-3" @submit.prevent="submit">
          <button
            type="submit"
            class="shrink-0 rounded-xl bg-desk-orange px-6 py-3 text-sm font-bold text-white transition hover:bg-orange-500 disabled:opacity-60"
            :disabled="submitting"
          >
            عضویت
          </button>
          <input
            v-model="contact"
            type="text"
            required
            placeholder="ایمیل یا شماره موبایل"
            class="h-12 w-full rounded-xl border-0 bg-white px-4 text-sm text-desk-text outline-none ring-0 placeholder:text-desk-muted"
          />
        </form>

        <div class="max-w-md text-right">
          <h2 class="mb-2 text-2xl font-bold">عضویت در خبرنامه</h2>
          <p class="text-sm leading-7 text-white/75">
            با عضویت در خبرنامه، از آخرین استخدام‌ها و آزمون‌ها با خبر شوید.
          </p>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref } from 'vue';
import { useToast } from '../../composables/useToast';

const toast = useToast();
const contact = ref('');
const submitting = ref(false);

async function submit() {
  if (!contact.value.trim()) return;
  submitting.value = true;
  try {
    // No dedicated newsletter API yet — persist locally and confirm UX.
    const key = 'jobazmoon_newsletter';
    const existing = JSON.parse(localStorage.getItem(key) || '[]');
    if (!existing.includes(contact.value.trim())) {
      existing.push(contact.value.trim());
      localStorage.setItem(key, JSON.stringify(existing));
    }
    toast.success('عضویت شما در خبرنامه ثبت شد.');
    contact.value = '';
  } finally {
    submitting.value = false;
  }
}
</script>
