import { computed } from 'vue'
import { useFeatureStore } from '../stores/feature'

export function useFeature(name: string) {
  const store = useFeatureStore()

  const enabled = computed(() => store.isEnabled(name))
  const config = computed(() => store.config(name))

  return { enabled, config }
}
