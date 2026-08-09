import { defineStore } from 'pinia'
import api from '@/api'
import { unwrapList, unwrapMeta } from '../utils/format'

export interface AppNotification {
  id: number | string
  is_read?: boolean
  read_at?: string | null
  [key: string]: unknown
}

interface NotificationsState {
  items: AppNotification[]
  meta: Record<string, unknown>
  unreadCount: number
  loading: boolean
  filter: 'all' | 'unread' | string
}

export const useNotificationsStore = defineStore('notifications', {
  state: (): NotificationsState => ({
    items: [],
    meta: {},
    unreadCount: 0,
    loading: false,
    filter: 'all',
  }),

  actions: {
    async fetchUnreadCount(): Promise<void> {
      try {
        const { data } = await api.get('/notifications/unread-count')
        this.unreadCount = data.data?.count ?? 0
      } catch {
        this.unreadCount = 0
      }
    },

    async fetchNotifications(page = 1): Promise<void> {
      this.loading = true
      try {
        const { data } = await api.get('/notifications', {
          params: {
            page,
            per_page: 20,
            unread: this.filter === 'unread' ? 1 : undefined,
          },
        })
        this.items = unwrapList(data) as AppNotification[]
        this.meta = (unwrapMeta(data) || {}) as Record<string, unknown>
      } finally {
        this.loading = false
      }
    },

    async markRead(id: number | string): Promise<void> {
      await api.post(`/notifications/${id}/read`)
      const item = this.items.find((n) => n.id === id)
      if (item) {
        item.is_read = true
        item.read_at = new Date().toISOString()
      }
      await this.fetchUnreadCount()
    },

    async markAllRead(): Promise<void> {
      await api.post('/notifications/read-all')
      this.items.forEach((n) => {
        n.is_read = true
      })
      this.unreadCount = 0
    },
  },
})
