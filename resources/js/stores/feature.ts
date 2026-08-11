import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/api'

export interface FeatureFlag {
  enabled: boolean
  config: Record<string, unknown> | null
  description?: string | null
}

export const useFeatureStore = defineStore('feature', () => {
  const flags = ref<Record<string, FeatureFlag>>({})
  const loaded = ref(false)
  const loading = ref(false)

  async function fetch(): Promise<void> {
    if (loading.value) return
    loading.value = true
    try {
      const { data } = await api.get('/features')
      flags.value = (data.data || {}) as Record<string, FeatureFlag>
      loaded.value = true
    } catch {
      flags.value = {}
      loaded.value = false
    } finally {
      loading.value = false
    }
  }

  function isEnabled(name: string): boolean {
    return flags.value[name]?.enabled ?? false
  }

  function config(name: string): Record<string, unknown> {
    return (flags.value[name]?.config || {}) as Record<string, unknown>
  }

  return { flags, loaded, loading, fetch, isEnabled, config }
})
