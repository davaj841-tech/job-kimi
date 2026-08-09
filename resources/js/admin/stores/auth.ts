import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import adminApi from '../api/client';

const STAFF_ROLES = ['admin', 'operator'] as const;

interface AdminUser {
  role?: string;
  [key: string]: unknown;
}

interface AuthSessionPayload {
  data?: {
    user?: AdminUser | null;
    token?: string;
    access_token?: string;
  };
}

export const useAdminAuthStore = defineStore('adminAuth', () => {
  const user = ref<AdminUser | null>(JSON.parse(localStorage.getItem('admin_user') || 'null'));
  const token = ref(localStorage.getItem('admin_token') || '');
  const loading = ref(false);

  const isAuthenticated = computed(() => Boolean(token.value));
  const isStaff = computed(() => STAFF_ROLES.includes(user.value?.role as (typeof STAFF_ROLES)[number]));

  function persist() {
    if (token.value) localStorage.setItem('admin_token', token.value);
    else localStorage.removeItem('admin_token');

    if (user.value) localStorage.setItem('admin_user', JSON.stringify(user.value));
    else localStorage.removeItem('admin_user');
  }

  function applySession(data: AuthSessionPayload) {
    const nextUser = data.data?.user || null;
    const nextToken = data.data?.token || data.data?.access_token || '';

    if (!STAFF_ROLES.includes(nextUser?.role as (typeof STAFF_ROLES)[number])) {
      throw { response: { data: { message: 'دسترسی پنل مدیریت فقط برای ادمین/اپراتور است.' } } };
    }

    token.value = nextToken;
    user.value = nextUser;
    persist();
  }

  async function sendOtp(mobile: string) {
    loading.value = true;
    try {
      const { data } = await adminApi.post('/auth/otp/send', { mobile });
      return data;
    } finally {
      loading.value = false;
    }
  }

  async function verifyOtp(mobile: string, code: string) {
    loading.value = true;
    try {
      const { data } = await adminApi.post('/auth/otp/verify', { mobile, code });
      applySession(data);
      return data;
    } finally {
      loading.value = false;
    }
  }

  async function loginWithPassword(username: string, password: string) {
    loading.value = true;
    try {
      const { data } = await adminApi.post('/admin/auth/login', { username, password });
      applySession(data);
      return data;
    } finally {
      loading.value = false;
    }
  }

  async function forgotPassword(email: string) {
    loading.value = true;
    try {
      const { data } = await adminApi.post('/admin/auth/forgot-password', { email });
      return data;
    } finally {
      loading.value = false;
    }
  }

  async function fetchMe() {
    if (!token.value) return null;
    const { data } = await adminApi.get('/auth/me');
    user.value = data.data?.user || data.data || null;
    persist();
    return user.value;
  }

  async function logout() {
    try {
      if (token.value) {
        await adminApi.post('/admin/auth/logout').catch(() => adminApi.post('/auth/logout'));
      }
    } catch (_) {
      /* ignore */
    }
    token.value = '';
    user.value = null;
    persist();
  }

  return {
    user,
    token,
    loading,
    isAuthenticated,
    isStaff,
    sendOtp,
    verifyOtp,
    loginWithPassword,
    forgotPassword,
    fetchMe,
    logout,
  };
});
