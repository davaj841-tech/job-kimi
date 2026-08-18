import { defineStore } from 'pinia'
import adminApi from '../api/client'
import { unwrapList, unwrapMeta } from '../../utils/format'

interface BlogPostsFilters {
  search: string
  status: string
  category: string
}

interface BlogPostsState {
  posts: Record<string, unknown>[]
  meta: Record<string, unknown>
  filters: BlogPostsFilters
  loading: boolean
  selected: Record<string, unknown> | null
}

export const useBlogPostsStore = defineStore('adminBlogPosts', {
  state: (): BlogPostsState => ({
    posts: [],
    meta: {},
    filters: {
      search: '',
      status: '',
      category: '',
    },
    loading: false,
    selected: null,
  }),

  actions: {
    async fetchBlogPosts(page = 1) {
      this.loading = true
      try {
        const { data } = await adminApi.get('/admin/blog-posts', {
          params: { ...this.filters, page, per_page: 20 },
        })
        this.posts = unwrapList(data) as Record<string, unknown>[]
        this.meta = unwrapMeta(data) || {}
      } finally {
        this.loading = false
      }
    },

    async fetchBlogPost(id: number | string) {
      const { data } = await adminApi.get(`/admin/blog-posts/${id}`)
      this.selected = data.data || null
      return this.selected
    },

    async createBlogPost(payload: Record<string, unknown>) {
      const form = toFormData(payload)
      const { data } = await adminApi.post('/admin/blog-posts', form)
      await this.fetchBlogPosts((this.meta.current_page as number) || 1)
      return data.data
    },

    async updateBlogPost(
      id: number | string,
      payload: Record<string, unknown>
    ) {
      const form = toFormData(payload)
      form.append('_method', 'PUT')
      const { data } = await adminApi.post(`/admin/blog-posts/${id}`, form)
      await this.fetchBlogPosts((this.meta.current_page as number) || 1)
      return data.data
    },

    async deleteBlogPost(id: number | string) {
      await adminApi.delete(`/admin/blog-posts/${id}`)
      await this.fetchBlogPosts((this.meta.current_page as number) || 1)
    },

    async publish(id: number | string) {
      await adminApi.post(`/admin/blog-posts/${id}/publish`)
      await this.fetchBlogPosts((this.meta.current_page as number) || 1)
    },

    async draft(id: number | string) {
      await adminApi.post(`/admin/blog-posts/${id}/draft`)
      await this.fetchBlogPosts((this.meta.current_page as number) || 1)
    },

    async generateWithAI(topic: string) {
      const { data } = await adminApi.post('/admin/ai/generate-blog', { topic })
      return data.data
    },

    resetFilters() {
      this.filters = { search: '', status: '', category: '' }
    },
  },
})

function toFormData(payload: Record<string, unknown> | null | undefined) {
  const form = new FormData()
  Object.entries(payload || {}).forEach(([key, value]) => {
    if (value === null || value === undefined || value === '') return
    if (key === 'featured_image' && value instanceof File) {
      form.append('featured_image', value)
      return
    }
    if (key === 'featured_image_file' && value instanceof File) {
      form.append('featured_image', value)
      return
    }
    if (key === 'exam_ids' || key === 'pdf_ids') {
      form.append(key, JSON.stringify(Array.isArray(value) ? value : []))
      return
    }
    if (typeof value === 'boolean') {
      form.append(key, value ? '1' : '0')
      return
    }
    form.append(key, value as string | Blob)
  })
  return form
}
