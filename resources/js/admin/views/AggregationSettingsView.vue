<template>
  <AdminLayout>
    <div class="space-y-5">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-2xl font-bold text-gray-800">زمان‌بندی تجمیع</h1>
          <p class="mt-1 text-sm text-slate-500">
            زمان‌های اجرا از پنل تنظیم می‌شوند؛ خزش سنگین فقط در صف
            <span dir="ltr" class="font-mono text-xs">{{ meta.queue || 'crawlers' }}</span>
            اجرا می‌شود.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <button class="btn-muted" :disabled="busy" @click="runDispatch(true)">Dry-run</button>
          <button class="btn-orange" :disabled="busy" @click="runDispatch(false)">دیسپاچ دستی</button>
        </div>
      </div>

      <div v-if="store.loading" class="rounded-xl bg-white p-6 text-sm text-slate-500 shadow-sm">در حال بارگذاری…</div>

      <template v-else>
        <div class="rounded-xl bg-white p-5 shadow-sm">
          <h2 class="mb-3 text-lg font-bold">عمومی</h2>
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <label class="flex items-center gap-2 text-sm">
              <input v-model="form.enabled" type="checkbox" />
              تجمیع زمان‌بندی‌شده فعال باشد
            </label>
            <div>
              <label class="mb-1 block text-xs text-slate-500">منطقه زمانی زمان‌بندی</label>
              <input v-model="form.timezone" class="field" dir="ltr" placeholder="Asia/Tehran" />
              <p class="mt-1 text-xs text-slate-400">
                مستقل از APP_TIMEZONE ({{ meta.app_timezone }}) — الان اسلات:
                <span dir="ltr">{{ meta.current_slot }}</span>
              </p>
            </div>
            <div>
              <label class="mb-1 block text-xs text-slate-500">حداکثر خزش همزمان</label>
              <input v-model.number="form.max_concurrent" type="number" min="1" max="50" class="field" />
            </div>
            <div>
              <label class="mb-1 block text-xs text-slate-500">تأخیر بین منابع (ثانیه)</label>
              <input v-model.number="form.dispatch_delay_seconds" type="number" min="0" max="300" class="field" />
            </div>
            <div>
              <label class="mb-1 block text-xs text-slate-500">تلاش مجدد صف (tries)</label>
              <input v-model.number="form.retry_tries" type="number" min="1" max="10" class="field" />
            </div>
          </div>
          <p v-if="formError" class="mt-3 text-sm text-red-600">{{ formError }}</p>
          <p v-if="formOk" class="mt-3 text-sm text-emerald-700">{{ formOk }}</p>
          <div class="mt-4 flex justify-end">
            <button class="btn-orange" :disabled="store.saving" @click="saveGeneral">ذخیره تنظیمات</button>
          </div>
        </div>

        <div class="rounded-xl bg-white p-5 shadow-sm">
          <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-bold">زمان‌های اجرا</h2>
            <span class="text-xs text-slate-400">حداکثر {{ meta.max_times || 24 }} زمان</span>
          </div>

          <div class="mb-4 grid grid-cols-1 gap-2 rounded-xl border border-dashed p-3 md:grid-cols-4">
            <input v-model="newTime.time" class="field" dir="ltr" placeholder="HH:MM مثلاً 08:00" />
            <input v-model="newTime.label" class="field" placeholder="برچسب اختیاری" />
            <label class="flex items-center gap-2 text-sm"><input v-model="newTime.enabled" type="checkbox" /> فعال</label>
            <button class="btn-dark" :disabled="busy" @click="addTime">افزودن زمان</button>
          </div>

          <div v-if="!form.times.length" class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500">
            هنوز زمانی تعریف نشده است. بدون زمان فعال، تجمیع خودکار اجرا نمی‌شود.
          </div>

          <div v-for="row in form.times" :key="row.id" class="mb-2 grid grid-cols-1 items-center gap-2 rounded-xl border p-3 md:grid-cols-5">
            <input v-model="row.time" class="field" dir="ltr" />
            <input v-model="row.label" class="field md:col-span-2" placeholder="برچسب" />
            <label class="flex items-center gap-2 text-sm"><input v-model="row.enabled" type="checkbox" /> فعال</label>
            <div class="flex justify-end gap-2">
              <button class="act" :disabled="busy" @click="saveTime(row)">ذخیره</button>
              <button class="act text-red-600" :disabled="busy" @click="removeTime(row)">حذف</button>
            </div>
          </div>
        </div>

        <div class="rounded-xl bg-slate-50 p-4 text-xs leading-6 text-slate-600">
          <p class="font-semibold text-slate-700">یادداشت سرور</p>
          <p>{{ meta.timezone_note }}</p>
          <p dir="ltr" class="font-mono">{{ meta.server_scheduler_requirement }}</p>
          <p>منابع می‌توانند «زمان‌بندی سراسری» یا «زمان‌بندی سفارشی» داشته باشند (در صفحه منابع تجمیع).</p>
        </div>

        <div v-if="dispatchOutput" class="rounded-xl bg-white p-4 shadow-sm">
          <h3 class="mb-2 font-bold">خروجی دیسپاچ</h3>
          <pre class="overflow-auto rounded-xl bg-slate-50 p-3 text-xs" dir="ltr">{{ dispatchOutput }}</pre>
        </div>
      </template>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import AdminLayout from '../components/layout/AdminLayout.vue';
import { useAggregationScheduleStore } from '../stores/aggregationSchedule';

const store = useAggregationScheduleStore();
const form = reactive({
  enabled: false,
  timezone: 'Asia/Tehran',
  max_concurrent: 5,
  dispatch_delay_seconds: 0,
  retry_tries: 2,
  times: [],
});
const newTime = reactive({ time: '', label: '', enabled: true });
const formError = ref('');
const formOk = ref('');
const dispatchOutput = ref('');
const busy = ref(false);

const meta = computed(() => store.meta || {});

function syncForm(schedule) {
  form.enabled = !!schedule.enabled;
  form.timezone = schedule.timezone || 'Asia/Tehran';
  form.max_concurrent = schedule.max_concurrent ?? 5;
  form.dispatch_delay_seconds = schedule.dispatch_delay_seconds ?? 0;
  form.retry_tries = schedule.retry_tries ?? 2;
  form.times = (schedule.times || []).map((t) => ({ ...t }));
}

watch(
  () => store.schedule,
  (s) => syncForm(s || {}),
  { deep: true, immediate: true },
);

onMounted(async () => {
  await store.fetch();
});

async function saveGeneral() {
  formError.value = '';
  formOk.value = '';
  try {
    await store.save({
      enabled: form.enabled,
      timezone: form.timezone,
      max_concurrent: form.max_concurrent,
      dispatch_delay_seconds: form.dispatch_delay_seconds,
      retry_tries: form.retry_tries,
      times: form.times,
    });
    formOk.value = 'تنظیمات ذخیره شد.';
    await store.fetch();
  } catch (e) {
    formError.value = e.response?.data?.message || 'خطا در ذخیره تنظیمات';
  }
}

async function addTime() {
  formError.value = '';
  busy.value = true;
  try {
    await store.addTime({
      time: newTime.time,
      label: newTime.label || null,
      enabled: newTime.enabled,
    });
    newTime.time = '';
    newTime.label = '';
    newTime.enabled = true;
    await store.fetch();
  } catch (e) {
    formError.value = e.response?.data?.message || 'خطا در افزودن زمان';
  } finally {
    busy.value = false;
  }
}

async function saveTime(row) {
  formError.value = '';
  busy.value = true;
  try {
    await store.updateTime(row.id, {
      time: row.time,
      label: row.label || null,
      enabled: row.enabled,
    });
    await store.fetch();
  } catch (e) {
    formError.value = e.response?.data?.message || 'خطا در به‌روزرسانی زمان';
  } finally {
    busy.value = false;
  }
}

async function removeTime(row) {
  if (!confirm('این زمان حذف شود؟')) return;
  busy.value = true;
  try {
    await store.removeTime(row.id);
    await store.fetch();
  } catch (e) {
    formError.value = e.response?.data?.message || 'خطا در حذف زمان';
  } finally {
    busy.value = false;
  }
}

async function runDispatch(dryRun) {
  busy.value = true;
  dispatchOutput.value = '';
  try {
    const result = await store.dispatchNow(dryRun);
    dispatchOutput.value = result?.output || JSON.stringify(result, null, 2);
  } catch (e) {
    formError.value = e.response?.data?.message || 'خطا در دیسپاچ';
  } finally {
    busy.value = false;
  }
}
</script>
