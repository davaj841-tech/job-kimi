import { defineStore } from 'pinia';
import api from '../api/client';
import { unwrapList, unwrapMeta } from '../utils/format';

export const useNotificationsStore = defineStore('notifications', {
  state: () => ({
    items: [],
    meta: {},
    unreadCount: 0,
    loading: false,
    filter: 'all',
  }),

  actions: {
    async fetchUnreadCount() {
      try {
        const { data } = await api.get('/notifications/unread-count');
        this.unreadCount = data.data?.count ?? 0;
      } catch {
        this.unreadCount = 0;
      }
    },

    async fetchNotifications(page = 1) {
      this.loading = true;
      try {
        const { data } = await api.get('/notifications', {
          params: {
            page,
            per_page: 20,
            unread: this.filter === 'unread' ? 1 : undefined,
          },
        });
        this.items = unwrapList(data);
        this.meta = unwrapMeta(data) || {};
      } finally {
        this.loading = false;
      }
    },

    async markRead(id) {
      await api.post(`/notifications/${id}/read`);
      const item = this.items.find((n) => n.id === id);
      if (item) {
        item.is_read = true;
        item.read_at = new Date().toISOString();
      }
      await this.fetchUnreadCount();
    },

    async markAllRead() {
      await api.post('/notifications/read-all');
      this.items.forEach((n) => {
        n.is_read = true;
      });
      this.unreadCount = 0;
    },
  },
});
