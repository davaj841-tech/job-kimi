import { defineStore } from 'pinia';
import adminApi from '../api/client';
import { unwrapList, unwrapMeta } from '../../utils/format';

function toFormData(payload: Record<string, unknown> | null | undefined) {
  const form = new FormData();
  const data = payload || {};

  Object.entries(data).forEach(([key, value]) => {
    if (['attachments', 'attachment_titles', 'attachment_descriptions', 'remove_attachment_ids'].includes(key)) {
      return;
    }
    if (value === undefined || value === null || value === '') {
      if (key === 'provinces') form.append(key, JSON.stringify([]));
      return;
    }
    if (key === 'attachment' && value instanceof File) {
      form.append('attachment', value);
      return;
    }
    if (key === 'attachment') return;
    if (key === 'provinces') {
      form.append('provinces', JSON.stringify(Array.isArray(value) ? value : []));
      return;
    }
    if (typeof value === 'boolean') {
      form.append(key, value ? '1' : '0');
      return;
    }
    form.append(key, value as string | Blob);
  });

  const files = Array.isArray(data.attachments) ? data.attachments : [];
  const titles = Array.isArray(data.attachment_titles) ? data.attachment_titles : [];
  const descriptions = Array.isArray(data.attachment_descriptions) ? data.attachment_descriptions : [];
  files.forEach((file, i) => {
    if (file instanceof File) {
      form.append('attachments[]', file);
      form.append(`attachment_titles[${i}]`, (titles[i] as string) || '');
      form.append(`attachment_descriptions[${i}]`, (descriptions[i] as string) || '');
    }
  });

  if (Array.isArray(data.remove_attachment_ids)) {
    form.append('remove_attachment_ids', JSON.stringify(data.remove_attachment_ids));
  }

  return form;
}

interface JobPostsFilters {
  search: string;
  status: string;
  province: string;
  city: string;
  job_classification_id: string;
  deadline_from: string;
  deadline_to: string;
}

interface FilterOptions {
  provinces: unknown[];
  cities: unknown[];
  classifications: Record<string, unknown>[];
}

interface JobPostsState {
  posts: Record<string, unknown>[];
  meta: Record<string, unknown>;
  filterOptions: FilterOptions;
  classifications: Record<string, unknown>[];
  filters: JobPostsFilters;
  loading: boolean;
  selected: Record<string, unknown> | null;
}

export const useJobPostsStore = defineStore('adminJobPosts', {
  state: (): JobPostsState => ({
    posts: [],
    meta: {},
    filterOptions: { provinces: [], cities: [], classifications: [] },
    classifications: [],
    filters: {
      search: '',
      status: '',
      province: '',
      city: '',
      job_classification_id: '',
      deadline_from: '',
      deadline_to: '',
    },
    loading: false,
    selected: null,
  }),

  actions: {
    async fetchJobPosts(page = 1) {
      this.loading = true;
      try {
        const { data } = await adminApi.get('/admin/job-posts', {
          params: { ...this.filters, page, per_page: 20 },
        });
        this.posts = unwrapList(data) as Record<string, unknown>[];
        this.meta = unwrapMeta(data) || {};
      } finally {
        this.loading = false;
      }
    },

    async fetchFilterOptions() {
      const { data } = await adminApi.get('/admin/job-posts/filter-options');
      this.filterOptions = data.data || { provinces: [], cities: [], classifications: [] };
      this.classifications = this.filterOptions.classifications || [];
    },

    async fetchClassifications() {
      const { data } = await adminApi.get('/admin/job-classifications');
      this.classifications = data.data?.flat || data.data || [];
      return this.classifications;
    },

    async fetchJobPost(id: number | string) {
      const { data } = await adminApi.get(`/admin/job-posts/${id}`);
      this.selected = data.data || null;
      return this.selected;
    },

    async createJobPost(payload: Record<string, unknown>) {
      const form = toFormData(payload);
      const { data } = await adminApi.post('/admin/job-posts', form, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      await this.fetchJobPosts((this.meta.current_page as number) || 1);
      return data.data;
    },

    async updateJobPost(id: number | string, payload: Record<string, unknown>) {
      const form = toFormData(payload);
      form.append('_method', 'PUT');
      const { data } = await adminApi.post(`/admin/job-posts/${id}`, form, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      await this.fetchJobPosts((this.meta.current_page as number) || 1);
      return data.data;
    },

    async approveJobPost(id: number | string) {
      await adminApi.post(`/admin/job-posts/${id}/approve`);
      await this.fetchJobPosts((this.meta.current_page as number) || 1);
    },

    async rejectJobPost(id: number | string, reason: string | null = null) {
      await adminApi.post(`/admin/job-posts/${id}/reject`, { reason });
      await this.fetchJobPosts((this.meta.current_page as number) || 1);
    },

    async deleteJobPost(id: number | string) {
      await adminApi.delete(`/admin/job-posts/${id}`);
      await this.fetchJobPosts((this.meta.current_page as number) || 1);
    },

    async importFromExcel(file: File) {
      const form = new FormData();
      form.append('file', file);
      const { data } = await adminApi.post('/admin/job-posts/import', form, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      await this.fetchJobPosts(1);
      return data.data;
    },

    resetFilters() {
      this.filters = {
        search: '',
        status: '',
        province: '',
        city: '',
        job_classification_id: '',
        deadline_from: '',
        deadline_to: '',
      };
    },
  },
});
