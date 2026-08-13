<template>
  <div class="space-y-4">
    <div v-for="field in fields" :key="field.key" class="space-y-1.5">
      <label class="label">{{ field.label }}</label>

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
        :value="modelValue[field.key] ?? ''"
        :placeholder="field.placeholder || ''"
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
    </div>
  </div>
</template>

<script setup>
import ColorPicker from '../ui/ColorPicker.vue'
import FontPicker from './FontPicker.vue'
import HomepageLayoutPicker from './HomepageLayoutPicker.vue'
import ImageUpload from '../ui/ImageUpload.vue'
import StatusToggle from '../ui/StatusToggle.vue'

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  fields: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue', 'dirty', 'upload'])

function set(key, value) {
  if (props.modelValue && typeof props.modelValue === 'object') {
    props.modelValue[key] = value
  }
  emit('update:modelValue', props.modelValue)
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
