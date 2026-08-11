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
        label="موبایل"
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
      <FormInput
        v-model="local.personal.address"
        label="آدرس / شهر"
        placeholder="تهران"
      />
      <FormInput
        v-model="local.personal.photo"
        label="آدرس عکس (اختیاری)"
        placeholder="https://..."
      />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import FormInput from '../FormInput.vue'

const props = defineProps({
  modelValue: { type: Object, required: true },
})
const emit = defineEmits(['update:modelValue', 'fill-profile'])

const local = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})
</script>
