import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import api from '../api/client';

export const useAuthStore = defineStore('auth', () => {
    const user = ref(JSON.parse(localStorage.getItem('user') || 'null'));
    const token = ref(localStorage.getItem('token') || '');
    const loading = ref(false);

    const isAuthenticated = computed(() => Boolean(token.value));

    function persist() {
        if (token.value) {
            localStorage.setItem('token', token.value);
        } else {
            localStorage.removeItem('token');
        }
        if (user.value) {
            localStorage.setItem('user', JSON.stringify(user.value));
        } else {
            localStorage.removeItem('user');
        }
    }

    async function sendOtp(mobile, turnstile_token) {
        loading.value = true;
        try {
            const payload = { mobile };
            if (turnstile_token) payload.turnstile_token = turnstile_token;
            const { data } = await api.post('/auth/otp/send', payload);
            return data;
        } finally {
            loading.value = false;
        }
    }

    async function verifyOtp(mobile, code, turnstile_token) {
        loading.value = true;
        try {
            const payload = { mobile, code };
            if (turnstile_token) payload.turnstile_token = turnstile_token;
            const { data } = await api.post('/auth/otp/verify', payload);
            token.value = data.data?.token || data.data?.access_token || '';
            user.value = data.data?.user || null;
            persist();
            return data;
        } finally {
            loading.value = false;
        }
    }

    async function fetchMe() {
        if (!token.value) return null;
        const { data } = await api.get('/auth/me');
        // successResponse wraps UserResource directly in data
        user.value = data.data?.user || data.data || null;
        persist();
        return user.value;
    }

    async function logout() {
        try {
            if (token.value) {
                await api.post('/auth/logout');
            }
        } catch (_) {
            // ignore
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
        sendOtp,
        verifyOtp,
        fetchMe,
        logout,
    };
});
