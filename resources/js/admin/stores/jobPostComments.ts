import { defineStore } from 'pinia'
import adminApi from '../api/client'
import { unwrapList, unwrapMeta } from '../../utils/format'

interface Filters {
  status: string
  search: string
  job_post_id: string
}

interface State {
  items: Record<string, unknown>[]
  meta: Record<string, unknown>
  filters: Filters
  loading: boolean
}

export const useJobPostCommentsStore = defineStore('adminJobPostComments', {
  state: (): State => ({
    items: [],
    meta: {},
    filters: {
      status: 'pending',
      search: '',
      job_post_id: '',
    },
    loading: false,
  }),

  actions: {
    async fetchComments(page = 1) {
      this.loading = true
      try {
        const { data } = await adminApi.get('/admin/job-post-comments', {
          params: {
            status: this.filters.status || undefined,
            search: this.filters.search || undefined,
            job_post_id: this.filters.job_post_id || undefined,
            page,
            per_page: 20,
          },
        })
        this.items = unwrapList(data)
        this.meta = unwrapMeta(data)
      } finally {
        this.loading = false
      }
    },

    async approve(id: number | string) {
      await adminApi.post(`/admin/job-post-comments/${id}/approve`)
      await this.fetchComments(Number(this.meta.current_page || 1))
    },

    async reject(id: number | string) {
      await adminApi.post(`/admin/job-post-comments/${id}/reject`)
      await this.fetchComments(Number(this.meta.current_page || 1))
    },

    async remove(id: number | string) {
      await adminApi.delete(`/admin/job-post-comments/${id}`)
      await this.fetchComments(Number(this.meta.current_page || 1))
    },
  },
})
