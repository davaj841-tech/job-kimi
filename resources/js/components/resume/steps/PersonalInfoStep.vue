<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between gap-2">
      <h2 class="text-lg font-bold text-desk-text dark:text-white">اطلاعات شخصی</h2>
      <button
        type="button"
        class="text-sm font-medium text-brand hover:underline"
        @click="$emit('fill-profile')"
      >
        پر کردن از پروفایل
      </button>
    </div>

    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
      <FormInput
        v-model="local.personal.full_name"
        label="نام و نام خانوادگی"
        placeholder="علی احمدی"
        required
      />
      <FormInput
        v-model="local.target_job"
        label="عنوان / شغل هدف"
        placeholder="برنامه‌نویس فرانت‌اند"
      />
      <FormInput
        v-model="local.personal.email"
        label="ایمیل"
        type="email"
        placeholder="ali@example.com"
        required
      />
      <FormInput
        v-model="local.personal.mobile"
        label="شماره موبایل"
        placeholder="09123456789"
        required
        maxlength="11"
      />
      <FormInput
        v-model="local.personal.national_code"
        label="کد ملی"
        placeholder="0012345678"
        required
        maxlength="10"
      />
      <FormInput
        v-model="local.personal.birth_date"
        label="تاریخ تولد"
        type="date"
        required
      />

      <label class="block">
        <span class="mb-1.5 block text-xs font-medium text-desk-muted"
          >استان محل تولد *</span
        >
        <select
          v-model="local.personal.birth_province"
          class="input-field"
          required
          @change="onProvinceChange"
        >
          <option value="">انتخاب استان</option>
          <option
            v-for="p in IRAN_PROVINCES"
            :key="p"
            :value="p"
          >
            {{ p }}
          </option>
        </select>
      </label>

      <label class="block">
        <span class="mb-1.5 block text-xs font-medium text-desk-muted"
          >شهرستان محل تولد *</span
        >
        <select
          v-model="local.personal.birth_city"
          class="input-field"
          required
          :disabled="!local.personal.birth_province"
        >
          <option value="">انتخاب شهرستان</option>
          <option
            v-for="c in cityOptions"
            :key="c"
            :value="c"
          >
            {{ c }}
          </option>
        </select>
      </label>

      <label class="block">
        <span class="mb-1.5 block text-xs font-medium text-desk-muted"
          >وضعیت تاهل *</span
        >
        <select
          v-model="local.personal.marital_status"
          class="input-field"
          required
        >
          <option value="">انتخاب کنید</option>
          <option value="single">مجرد</option>
          <option value="married">متاهل</option>
          <option value="divorced">مطلقه / متعلقه</option>
        </select>
      </label>

      <FormInput
        v-model="local.personal.field_of_study"
        label="رشته تحصیلی"
        placeholder="مهندسی کامپیوتر"
      />

      <FormInput
        v-model="local.personal.address"
        label="آدرس محل سکونت"
        placeholder="تهران، خیابان…"
        class="md:col-span-2"
      />
      <FormInput
        v-model="local.personal.photo"
        label="آدرس عکس (اختیاری)"
        placeholder="https://..."
        class="md:col-span-2"
      />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import FormInput from '../FormInput.vue'
import { IRAN_PROVINCES, citiesForProvince } from '../../../utils/iranCities'

const props = defineProps({
  modelValue: { type: Object, required: true },
})
const emit = defineEmits(['update:modelValue', 'fill-profile'])

const local = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

const cityOptions = computed(() =>
  citiesForProvince(local.value?.personal?.birth_province || '')
)

function onProvinceChange() {
  if (!local.value?.personal) return
  local.value.personal.birth_city = ''
}
</script>
