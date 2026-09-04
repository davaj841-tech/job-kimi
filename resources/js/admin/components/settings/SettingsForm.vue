<template>
  <div class="space-y-4">
    <div v-for="field in fields" :key="field.key" class="space-y-1.5">
      <label class="label">{{ field.label }}</label>
      <p v-if="field.hint" class="text-[11px] leading-5 text-slate-400">
        {{ field.hint }}
      </p>

      <input
        v-if="
          field.type === 'text' ||
          field.type === 'number' ||
          field.type === 'email' ||
          field.type === 'url'
        "
        :type="
          field.type === 'number'
            ? 'number'
            : field.type === 'email'
              ? 'email'
              : 'text'
        "
        :dir="field.ltr ? 'ltr' : undefined"
        class="field"
        :class="field.ltr ? 'font-mono text-xs' : ''"
        :value="modelValue[field.key] ?? ''"
        :placeholder="
          field.placeholder ||
          (field.secret ? 'برای تغییر، کلید جدید را وارد کنید' : '')
        "
        @focus="onSecretFocus(field, $event)"
        @input="set(field.key, $event.target.value)"
      />

      <textarea
        v-else-if="field.type === 'textarea'"
        class="field min-h-[88px] py-2"
        rows="3"
        :value="modelValue[field.key] ?? ''"
        @input="set(field.key, $event.target.value)"
      />

      <select
        v-else-if="field.type === 'select'"
        class="field"
        :value="modelValue[field.key] ?? ''"
        @change="set(field.key, $event.target.value)"
      >
        <option
          v-for="opt in field.options || []"
          :key="opt.value"
          :value="opt.value"
        >
          {{ opt.label }}
        </option>
      </select>

      <label
        v-else-if="field.type === 'toggle'"
        class="flex items-center justify-between rounded-xl border border-slate-200 px-3 py-2.5 text-sm"
      >
        <span>{{ field.toggleLabel || 'فعال' }}</span>
        <StatusToggle
          :model-value="isTrue(modelValue[field.key])"
          @update:model-value="set(field.key, $event ? 'true' : 'false')"
        />
      </label>

      <ColorPicker
        v-else-if="field.type === 'color'"
        :model-value="modelValue[field.key] || '#f97316'"
        @update:model-value="set(field.key, $event)"
      />

      <ImageUpload
        v-else-if="field.type === 'image'"
        :model-value="modelValue[field.key] || ''"
        :label="field.label"
        @update:model-value="set(field.key, $event)"
        @file="
          (f) =>
            $emit('upload', {
              key: field.key,
              type: field.uploadType || 'logo',
              file: f,
            })
        "
      />

      <div v-else-if="field.type === 'file'" class="space-y-2">
        <FileUploader
          label="انتخاب فایل"
          :hint="field.hint || 'کشیدن و رها کردن یا کلیک'"
          :accept="field.accept || '*/*'"
          :max-size-mb="field.maxSizeMb || 20"
          @update:model-value="
            (f) =>
              $emit('upload', {
                key: field.key,
                type: field.uploadType || 'apk',
                file: f,
              })
          "
        />
        <a
          v-if="modelValue[field.key]"
          :href="modelValue[field.key]"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-block text-xs font-bold text-orange-600 hover:underline"
          dir="ltr"
        >
          فایل فعلی آپلود شده
        </a>
      </div>

      <HomepageLayoutPicker
        v-else-if="field.type === 'homepage-layout'"
        :model-value="modelValue[field.key] || 'atlas'"
        @update:model-value="set(field.key, $event)"
      />

      <FontPicker
        v-else-if="field.type === 'site-font'"
        :model-value="modelValue[field.key] || 'estedad'"
        @update:model-value="set(field.key, $event)"
      />

      <div
        v-else-if="field.type === 'site-font-size'"
        class="flex flex-wrap gap-2"
      >
        <button
          v-for="size in [13, 14, 15, 16, 17, 18, 20]"
          :key="size"
          type="button"
          class="rounded-xl border-2 px-3 py-2 text-sm font-bold"
          :class="
            Number(modelValue[field.key] || 16) === size
              ? 'border-orange-500 bg-orange-50 text-orange-700'
              : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'
          "
          @click="set(field.key, String(size))"
        >
          {{ size }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import ColorPicker from '../ui/ColorPicker.vue'
import FileUploader from '../ui/FileUploader.vue'
import FontPicker from './FontPicker.vue'
import HomepageLayoutPicker from './HomepageLayoutPicker.vue'
import ImageUpload from '../ui/ImageUpload.vue'
import StatusToggle from '../ui/StatusToggle.vue'

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  fields: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue', 'dirty', 'upload'])

function isMaskedSecret(value) {
  if (value == null || value === '') return false
  const v = String(value)
  if (v === '********' || v === '****') return true
  if (/^[0-9a-f]{8}-\*{4}-\*{4}-\*{4}-[0-9a-f]{12}$/i.test(v)) return true
  return /^[^*]{1,8}\*{4,}[^*]{0,8}$/.test(v)
}

function onSecretFocus(field, event) {
  if (!field.secret) return
  const current = props.modelValue?.[field.key]
  if (!isMaskedSecret(current)) return
  set(field.key, '')
  event.target.value = ''
}

function set(key, value) {
  const current =
    props.modelValue && typeof props.modelValue === 'object'
      ? props.modelValue
      : {}
  // Mutate reactive parent in place so selection + live preview update immediately
  if (current === props.modelValue) {
    current[key] = value
  }
  emit('update:modelValue', { ...current, [key]: value })
  emit('dirty')
}

function isTrue(v) {
  return v === true || v === 'true' || v === 1 || v === '1'
}
</script>

<style scoped>
.label {
  @apply block text-xs font-bold text-slate-500;
}
.field {
  @apply h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-orange-400;
}
</style>
