import { defineStore } from 'pinia';
import adminApi from '../api/client';

interface UsersFilters {
  search: string;
  role: string;
  status: string;
  sort: string;
}

interface AdminUser {
  id?: number;
  [key: string]: unknown;
}

interface UsersState {
  users: Record<string, unknown>[];
  meta: Record<string, unknown>;
  filters: UsersFilters;
  loading: boolean;
  selectedUser: AdminUser | null;
  detailLoading: boolean;
}

export const useUsersStore = defineStore('users', {
  state: (): UsersState => ({
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

    async fetchUser(id: number | string) {
      this.detailLoading = true;
      try {
        const { data } = await adminApi.get(`/admin/users/${id}`);
        this.selectedUser = data.data || null;
        return this.selectedUser;
      } finally {
        this.detailLoading = false;
      }
    },

    async createUser(payload: Record<string, unknown>) {
      const { data } = await adminApi.post('/admin/users', payload);
      await this.fetchUsers(1);
      return data.data;
    },

    async updateUser(userId: number | string, payload: Record<string, unknown>) {
      const { data } = await adminApi.put(`/admin/users/${userId}`, payload);
      await this.fetchUsers((this.meta.current_page as number) || 1);
      if (this.selectedUser?.id === userId) {
        await this.fetchUser(userId);
      }
      return data.data;
    },

    async updateRole(userId: number | string, role: string) {
      await adminApi.put(`/admin/users/${userId}/role`, { role });
      await this.fetchUsers((this.meta.current_page as number) || 1);
      if (this.selectedUser?.id === userId) {
        await this.fetchUser(userId);
      }
    },

    async updateStatus(userId: number | string, status: string) {
      await adminApi.put(`/admin/users/${userId}/status`, { status });
      await this.fetchUsers((this.meta.current_page as number) || 1);
      if (this.selectedUser?.id === userId) {
        await this.fetchUser(userId);
      }
    },

    async deleteUser(userId: number | string) {
      await adminApi.delete(`/admin/users/${userId}`);
      this.selectedUser = null;
      await this.fetchUsers((this.meta.current_page as number) || 1);
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
