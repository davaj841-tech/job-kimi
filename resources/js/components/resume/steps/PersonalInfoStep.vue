<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between gap-2">
      <h2 class="text-lg font-bold text-desk-text dark:text-white">
        اطلاعات شخصی
      </h2>
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
        placeholder="مثلاً کارشناس اعتبارات"
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
        v-model="local.personal.home_phone"
        label="تلفن منزل"
        placeholder="02112345678"
        maxlength="11"
      />
      <label class="block">
        <span class="mb-1.5 block text-xs font-medium text-desk-muted"
          >وضعیت سربازی</span
        >
        <select v-model="local.personal.military_status" class="input-field">
          <option value="">انتخاب کنید</option>
          <option v-for="m in militaryOptions" :key="m" :value="m">
            {{ m }}
          </option>
        </select>
      </label>
      <FormInput
        v-model="local.personal.insurance_history"
        label="سابقه بیمه"
        placeholder="مثلاً ۵ سال تامین اجتماعی"
      />
      <label class="block">
        <span class="mb-1.5 block text-xs font-medium text-desk-muted">
          کد ملی <span class="text-brand">*</span>
        </span>
        <input
          :value="local.personal.national_code"
          class="input-field text-left"
          dir="ltr"
          inputmode="numeric"
          maxlength="10"
          placeholder="۱۰ رقم معتبر ایران"
          required
          @input="onNational"
        />
        <p
          v-if="
            String(local.personal.national_code || '').length === 10 &&
            !isValidNationalCode(local.personal.national_code)
          "
          class="mt-1 text-xs text-brand"
        >
          کد ملی معتبر نیست
        </p>
      </label>
      <JalaliBirthInput v-model="local.personal.birth_date" required />

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
          <option v-for="p in IRAN_PROVINCES" :key="p" :value="p">
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
          <option v-for="c in cityOptions" :key="c" :value="c">
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

      <SearchSelect
        v-model="local.personal.field_of_study"
        label="رشته تحصیلی"
        placeholder="جستجوی رشته…"
        :options="ACADEMIC_FIELDS"
      />

      <FormInput
        v-model="local.personal.address"
        label="آدرس محل سکونت"
        placeholder=""
        class="md:col-span-2"
      />
      <label class="block">
        <span class="mb-1.5 block text-xs font-medium text-desk-muted"
          >کد پستی</span
        >
        <input
          :value="local.personal.postal_code"
          class="input-field text-left"
          dir="ltr"
          inputmode="numeric"
          maxlength="10"
          placeholder="۱۰ رقم"
          @input="onPostal"
        />
      </label>
      <div class="md:col-span-2">
        <PhotoUpload v-model="local.personal.photo" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import FormInput from '../FormInput.vue'
import JalaliBirthInput from '../JalaliBirthInput.vue'
import PhotoUpload from '../PhotoUpload.vue'
import SearchSelect from '../SearchSelect.vue'
import { ACADEMIC_FIELDS } from '../../../data/academicFields'
import { IRAN_PROVINCES, citiesForProvince } from '../../../utils/iranCities'
import { isValidNationalCode } from '../../../utils/validators'

const militaryOptions = [
  'پایان خدمت',
  'معافیت دائم',
  'معافیت تحصیلی',
  'در حال خدمت',
  'مشمول',
  'غیرمشمول',
]

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

function onNational(e) {
  local.value.personal.national_code = String(e.target.value || '')
    .replace(/\D/g, '')
    .slice(0, 10)
}

function onPostal(e) {
  local.value.personal.postal_code = String(e.target.value || '')
    .replace(/\D/g, '')
    .slice(0, 10)
}

function onProvinceChange() {
  if (!local.value?.personal) return
  local.value.personal.birth_city = ''
}
</script>
