<template>
  <AdminLayout>
    <div class="mx-auto max-w-3xl">
      <div class="mb-4">
        <RouterLink
          to="/admin/exams"
          class="text-xs font-bold text-orange-600 hover:underline"
          >← بازگشت به آزمون‌ها</RouterLink
        >
        <h1 class="mt-1 text-xl font-black text-slate-800">نتیجه آزمون</h1>
        <p v-if="exam" class="mt-1 text-sm text-slate-500">{{ exam.title }}</p>
      </div>

      <div
        v-if="loading"
        class="rounded-2xl bg-white p-10 text-center text-sm text-slate-500 shadow-sm"
      >
        در حال بارگذاری نتیجه...
      </div>
      <div
        v-else-if="error"
        class="rounded-2xl bg-white p-8 text-center text-sm text-red-500 shadow-sm"
      >
        {{ error }}
      </div>
      <template v-else-if="attempt">
        <div class="mb-4 rounded-2xl bg-white p-6 text-center shadow-sm">
          <p class="text-sm text-slate-500">درصد موفقیت</p>
          <p class="mt-2 text-5xl font-black text-orange-600">
            {{ fa(Math.round(attempt.percentage || 0)) }}٪
          </p>
          <p class="mt-2 text-sm text-slate-600">
            نمره: {{ fa(attempt.score) }} از {{ fa(exam?.total_marks || 0) }}
          </p>
          <p
            class="mt-3 inline-block rounded-lg px-3 py-1 text-xs font-bold"
            :class="
              passed
                ? 'bg-emerald-50 text-emerald-700'
                : 'bg-red-50 text-red-600'
            "
          >
            {{ passed ? '🎉 قبول' : '📌 مردود' }}
          </p>
        </div>

        <div class="mb-4 grid grid-cols-3 gap-3 text-center">
          <div class="rounded-2xl bg-white p-4 shadow-sm">
            <p class="text-[11px] text-slate-500">صحیح</p>
            <p class="mt-1 text-xl font-black text-emerald-600">
              {{ fa(attempt.total_correct) }}
            </p>
          </div>
          <div class="rounded-2xl bg-white p-4 shadow-sm">
            <p class="text-[11px] text-slate-500">غلط</p>
            <p class="mt-1 text-xl font-black text-red-500">
              {{ fa(attempt.total_wrong) }}
            </p>
          </div>
          <div class="rounded-2xl bg-white p-4 shadow-sm">
            <p class="text-[11px] text-slate-500">بی‌پاسخ</p>
            <p class="mt-1 text-xl font-black text-slate-500">
              {{ fa(blankCount) }}
            </p>
          </div>
        </div>

        <div
          class="mb-4 rounded-2xl bg-white p-5 text-sm leading-7 text-slate-600 shadow-sm"
        >
          از مجموع {{ fa(analysis?.total_questions || 0) }} سوال،
          <b class="text-emerald-600">{{ fa(attempt.total_correct) }}</b> صحیح،
          <b class="text-red-500">{{ fa(attempt.total_wrong) }}</b> غلط
          <template v-if="blankCount">
            و <b>{{ fa(blankCount) }}</b> بی‌پاسخ</template
          >. نتیجه ذخیره شد و در آمار و تعداد شرکت‌کنندگان آزمون قابل مشاهده
          است.
        </div>

        <div
          v-if="subjects.length"
          class="mb-4 rounded-2xl bg-white p-5 shadow-sm"
        >
          <h2 class="mb-3 text-sm font-bold text-slate-800">تحلیل موضوعی</h2>
          <div class="space-y-3">
            <div v-for="row in subjects" :key="row.subject" class="text-xs">
              <div class="mb-1 flex items-center justify-between">
                <span class="font-bold">{{ row.subject_label }}</span>
                <span class="text-slate-500">
                  ✅ {{ fa(row.correct) }} · ❌ {{ fa(row.wrong) }} · ➖
                  {{ fa(row.blank) }} / {{ fa(row.total) }}
                </span>
              </div>
              <div class="h-1.5 overflow-hidden rounded bg-slate-100">
                <div
                  class="h-full rounded bg-orange-500"
                  :style="{ width: `${row.percentage || 0}%` }"
                />
              </div>
            </div>
          </div>
        </div>

        <div class="mb-4">
          <button
            type="button"
            class="mb-3 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold"
            @click="showSheet = !showSheet"
          >
            {{ showSheet ? 'بستن پاسخبرگ' : 'مشاهده پاسخبرگ' }}
          </button>
          <div v-if="showSheet" class="space-y-2">
            <div
              v-for="item in sheet"
              :key="item.id"
              class="rounded-xl border px-3 py-3 text-sm"
              :class="
                item.is_blank
                  ? 'border-slate-200 bg-slate-50'
                  : item.is_correct
                    ? 'border-emerald-200 bg-emerald-50'
                    : 'border-red-200 bg-red-50'
              "
            >
              <div class="mb-1 flex justify-between text-xs text-slate-500">
                <span>سوال {{ fa(item.number) }}</span>
                <span>{{
                  item.is_blank ? 'بدون پاسخ' : item.is_correct ? 'صحیح' : 'غلط'
                }}</span>
              </div>
              <div class="mb-2 leading-6" v-html="item.question_text" />
              <p class="text-xs">
                پاسخ شما:
                <b>{{
                  item.user_answer
                    ? String(item.user_answer).toUpperCase()
                    : '—'
                }}</b>
                — صحیح: <b>{{ String(item.correct_answer).toUpperCase() }}</b>
              </p>
            </div>
          </div>
        </div>

        <div class="flex gap-2">
          <RouterLink
            :to="{ name: 'admin-exam-take', params: { id: route.params.id } }"
            class="flex-1 rounded-xl bg-orange-500 px-4 py-3 text-center text-sm font-bold text-white"
          >
            آزمون مجدد
          </RouterLink>
          <RouterLink
            to="/admin/exams"
            class="flex-1 rounded-xl bg-slate-100 px-4 py-3 text-center text-sm font-bold text-slate-700"
          >
            لیست آزمون‌ها
          </RouterLink>
        </div>
      </template>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import adminApi from '../api/client'
import AdminLayout from '../components/layout/AdminLayout.vue'

const route = useRoute()
const loading = ref(true)
const error = ref('')
const attempt = ref(null)
const exam = ref(null)
const analysis = ref(null)
const sheet = ref([])
const showSheet = ref(false)

const passed = computed(() => Boolean(analysis.value?.passed))
const subjects = computed(() => analysis.value?.by_subject || [])
const blankCount = computed(() => {
  const total = analysis.value?.total_questions || 0
  return Math.max(
    0,
    total -
      (attempt.value?.total_correct || 0) -
      (attempt.value?.total_wrong || 0)
  )
})

function fa(n) {
  return new Intl.NumberFormat('fa-IR').format(Number(n || 0))
}

onMounted(async () => {
  try {
    const { data } = await adminApi.get(
      `/admin/exams/${route.params.id}/practice/result/${route.params.attemptId}`
    )
    const payload = data.data || data
    attempt.value = payload.attempt
    exam.value = payload.exam
    analysis.value = payload.analysis
    sheet.value = payload.sheet || []
  } catch (e) {
    error.value = e.response?.data?.message || 'بارگذاری نتیجه ممکن نشد.'
  } finally {
    loading.value = false
  }
})
</script>
