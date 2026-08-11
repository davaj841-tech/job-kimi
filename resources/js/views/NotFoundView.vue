<template>
  <div
    class="flex min-h-[80dvh] flex-col items-center justify-center px-6 py-12 text-center"
  >
    <div
      class="mb-6 flex h-28 w-28 items-center justify-center rounded-full bg-brand-soft text-5xl"
    >
      🎓❓
    </div>
    <p class="mb-2 text-5xl font-black text-brand">۴۰۴</p>
    <h1 class="mb-2 text-xl font-black text-ink">
      صفحه‌ای که دنبالش بودید پیدا نشد!
    </h1>
    <p class="mb-8 max-w-md text-sm leading-7 text-ink-muted">
      شاید آدرس اشتباه باشد یا صفحه جابه‌جا شده باشد. از گزینه‌های زیر ادامه
      دهید.
    </p>

    <RouterLink to="/" class="btn-primary mb-8 max-w-xs"
      >برو به صفحه اصلی</RouterLink
    >

    <form class="mb-10 flex w-full max-w-md gap-2" @submit.prevent="search">
      <input
        v-model="q"
        class="input-field flex-1"
        placeholder="جستجو کنید..."
      />
      <button
        type="submit"
        class="rounded-xl bg-[#0a1c33] px-4 text-sm font-bold text-white"
      >
        جستجو
      </button>
    </form>

    <div class="w-full max-w-3xl">
      <p class="mb-3 text-sm font-bold text-ink">آزمون‌های محبوب</p>
      <div class="grid gap-3 sm:grid-cols-3">
        <RouterLink
          v-for="exam in exams"
          :key="exam.to"
          :to="exam.to"
          class="rounded-2xl border border-surface-line bg-white p-4 text-right transition hover:border-brand"
        >
          <p class="text-sm font-bold">{{ exam.title }}</p>
          <p class="mt-1 text-xs text-ink-muted">{{ exam.hint }}</p>
        </RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const q = ref('')

const exams = [
  { to: '/exams', title: 'آزمون‌های استخدامی', hint: 'لیست کامل آزمون‌ها' },
  { to: '/jobs', title: 'آگهی‌های شغلی', hint: 'فرصت‌های جدید' },
  { to: '/pdfs', title: 'فروشگاه فایل', hint: 'جزوه و نمونه سوال' },
]

function search() {
  if (!q.value.trim()) return
  router.push({ path: '/jobs', query: { search: q.value.trim() } })
}
</script>
