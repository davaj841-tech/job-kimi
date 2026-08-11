import { defineStore } from 'pinia'
import adminApi from '../api/client'
import { unwrapList, unwrapMeta } from '../../utils/format'

interface AiContentsFilters {
  type: string
  status: string
}

interface AiContentsStats {
  generated_today: number
  daily_limit: number
  pending: number
  by_type: Record<string, unknown>
}

interface AiContentsState {
  items: Record<string, unknown>[]
  meta: Record<string, unknown>
  selected: Record<string, unknown> | null
  stats: AiContentsStats
  filters: AiContentsFilters
  loading: boolean
}

export const useAiContentsStore = defineStore('adminAiContents', {
  state: (): AiContentsState => ({
    items: [],
    meta: {},
    selected: null,
    stats: {
      generated_today: 0,
      daily_limit: 50,
      pending: 0,
      by_type: {},
    },
    filters: {
      type: 'exam_question',
      status: '',
    },
    loading: false,
  }),

  actions: {
    async fetchStats() {
      const { data } = await adminApi.get('/admin/ai/stats')
      this.stats = data.data || this.stats
    },

    async fetchContents(page = 1) {
      this.loading = true
      try {
        const { data } = await adminApi.get('/admin/ai/contents', {
          params: {
            type: this.filters.type || undefined,
            status: this.filters.status || undefined,
            page,
            per_page: 20,
          },
        })
        this.items = unwrapList(data) as Record<string, unknown>[]
        this.meta = unwrapMeta(data) || {}
      } finally {
        this.loading = false
      }
    },

    async fetchContent(id: number | string) {
      const { data } = await adminApi.get(`/admin/ai/contents/${id}`)
      this.selected = data.data || null
      return this.selected
    },

    async approve(id: number | string) {
      await adminApi.post(`/admin/ai/contents/${id}/approve`)
      await this.fetchContents((this.meta.current_page as number) || 1)
      await this.fetchStats()
    },

    async reject(id: number | string, reason: string | null = null) {
      await adminApi.post(`/admin/ai/contents/${id}/reject`, { reason })
      await this.fetchContents((this.meta.current_page as number) || 1)
      await this.fetchStats()
    },

    async remove(id: number | string) {
      await adminApi.delete(`/admin/ai/contents/${id}`)
      await this.fetchContents((this.meta.current_page as number) || 1)
      await this.fetchStats()
    },
  },
})
