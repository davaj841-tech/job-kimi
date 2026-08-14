import { defineStore } from 'pinia'
import adminApi from '../api/client'

interface SettingsState {
  groups: Record<string, Record<string, unknown>>
  schema: Record<string, unknown>
  loading: boolean
  saving: boolean
  dirty: boolean
}

export const useSettingsStore = defineStore('adminSettings', {
  state: (): SettingsState => ({
    groups: {},
    schema: {},
    loading: false,
    saving: false,
    dirty: false,
  }),

  actions: {
    async fetchSettings(group: string | null = null) {
      this.loading = true
      try {
        const { data } = await adminApi.get('/admin/settings', {
          params: group ? { group } : {},
        })
        this.groups = data.data?.groups || {}
        this.schema = data.data?.schema || {}
        this.dirty = false
      } finally {
        this.loading = false
      }
    },

    async updateSettings(group: string, values: Record<string, unknown>) {
      this.saving = true
      try {
        const { data } = await adminApi.put('/admin/settings', {
          group,
          values,
        })
        if (data.data?.groups) {
          this.groups = { ...this.groups, ...data.data.groups }
        }
        this.dirty = false
        return data.data
      } finally {
        this.saving = false
      }
    },

    async uploadLogo(file: File, type = 'logo') {
      const form = new FormData()
      form.append('file', file)
      form.append('type', type)
      const { data } = await adminApi.post(
        '/admin/settings/upload-logo',
        form,
        {
          headers: { 'Content-Type': 'multipart/form-data' },
          timeout: type === 'apk' ? 180000 : 30000,
        }
      )
      const key = data.data?.key
      const url = data.data?.url
      const g = data.data?.group || 'general'
      if (key && this.groups[g]) {
        this.groups[g][key] = url
      }
      return data.data
    },

    markDirty() {
      this.dirty = true
    },
  },
})
