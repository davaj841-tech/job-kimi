import { defineStore } from 'pinia';
import adminApi from '../api/client';
import { unwrapList, unwrapMeta } from '../../utils/format';

export const usePdfProductsStore = defineStore('adminPdfProducts', {
  state: () => ({
    products: [],
    meta: {},
    filters: {
      search: '',
      category: '',
      is_active: '',
    },
    loading: false,
    selected: null,
  }),

  actions: {
    async fetchPDFProducts(page = 1) {
      this.loading = true;
      try {
        const { data } = await adminApi.get('/admin/pdf-products', {
          params: { ...this.filters, page, per_page: 20 },
        });
        this.products = unwrapList(data);
        this.meta = unwrapMeta(data) || {};
      } finally {
        this.loading = false;
      }
    },

    async fetchPDFProduct(id) {
      const { data } = await adminApi.get(`/admin/pdf-products/${id}`);
      this.selected = data.data || null;
      return this.selected;
    },

    async createPDFProduct(payload) {
      const form = toFormData(payload);
      const { data } = await adminApi.post('/admin/pdf-products', form);
      await this.fetchPDFProducts(this.meta.current_page || 1);
      return data.data;
    },

    async updatePDFProduct(id, payload) {
      const form = toFormData(payload);
      form.append('_method', 'PUT');
      const { data } = await adminApi.post(`/admin/pdf-products/${id}`, form);
      await this.fetchPDFProducts(this.meta.current_page || 1);
      return data.data;
    },

    async deletePDFProduct(id) {
      await adminApi.delete(`/admin/pdf-products/${id}`);
      await this.fetchPDFProducts(this.meta.current_page || 1);
    },

    async toggleActive(id, isActive) {
      const form = new FormData();
      form.append('is_active', isActive ? '1' : '0');
      form.append('_method', 'PUT');
      await adminApi.post(`/admin/pdf-products/${id}`, form);
      await this.fetchPDFProducts(this.meta.current_page || 1);
    },

    resetFilters() {
      this.filters = { search: '', category: '', is_active: '' };
    },
  },
});

function toFormData(payload) {
  const form = new FormData();
  Object.entries(payload || {}).forEach(([key, value]) => {
    if (value === null || value === undefined || value === '') return;
    if (value instanceof File) {
      form.append(key, value);
      return;
    }
    if (typeof value === 'boolean') {
      form.append(key, value ? '1' : '0');
      return;
    }
    form.append(key, value);
  });
  return form;
}
