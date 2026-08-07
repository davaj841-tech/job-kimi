import { defineStore } from 'pinia';
import adminApi from '../api/client';

export const useUsersStore = defineStore('users', {
  state: () => ({
    users: [],
    meta: {},
    filters: {
      search: '',
      role: '',
      status: '',
      sort: 'desc',
    },
    loading: false,
    selectedUser: null,
    detailLoading: false,
  }),

  actions: {
    async fetchUsers(page = 1) {
      this.loading = true;
      try {
        const { data } = await adminApi.get('/admin/users', {
          params: { ...this.filters, page, per_page: 20 },
        });
        this.users = data.data || [];
        this.meta = data.meta || {};
      } finally {
        this.loading = false;
      }
    },

    async fetchUser(id) {
      this.detailLoading = true;
      try {
        const { data } = await adminApi.get(`/admin/users/${id}`);
        this.selectedUser = data.data || null;
        return this.selectedUser;
      } finally {
        this.detailLoading = false;
      }
    },

    async createUser(payload) {
      const { data } = await adminApi.post('/admin/users', payload);
      await this.fetchUsers(1);
      return data.data;
    },

    async updateUser(userId, payload) {
      const { data } = await adminApi.put(`/admin/users/${userId}`, payload);
      await this.fetchUsers(this.meta.current_page || 1);
      if (this.selectedUser?.id === userId) {
        await this.fetchUser(userId);
      }
      return data.data;
    },

    async updateRole(userId, role) {
      await adminApi.put(`/admin/users/${userId}/role`, { role });
      await this.fetchUsers(this.meta.current_page || 1);
      if (this.selectedUser?.id === userId) {
        await this.fetchUser(userId);
      }
    },

    async updateStatus(userId, status) {
      await adminApi.put(`/admin/users/${userId}/status`, { status });
      await this.fetchUsers(this.meta.current_page || 1);
      if (this.selectedUser?.id === userId) {
        await this.fetchUser(userId);
      }
    },

    async deleteUser(userId) {
      await adminApi.delete(`/admin/users/${userId}`);
      this.selectedUser = null;
      await this.fetchUsers(this.meta.current_page || 1);
    },

    resetFilters() {
      this.filters = {
        search: '',
        role: '',
        status: '',
        sort: 'desc',
      };
    },
  },
});
