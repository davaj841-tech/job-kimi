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

    function applyAuthPayload(data) {
        token.value = data.data?.token || data.data?.access_token || '';
        user.value = data.data?.user || null;
        persist();
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

    async function verifyOtp(mobile, code, turnstile_token, province) {
        loading.value = true;
        try {
            const payload = { mobile, code };
            if (turnstile_token) payload.turnstile_token = turnstile_token;
            if (province) payload.province = province;
            const { data } = await api.post('/auth/otp/verify', payload);
            applyAuthPayload(data);
            return data;
        } finally {
            loading.value = false;
        }
    }

    async function loginPassword(login, password, turnstile_token) {
        loading.value = true;
        try {
            const payload = { login, password };
            if (turnstile_token) payload.turnstile_token = turnstile_token;
            const { data } = await api.post('/auth/login', payload);
            applyAuthPayload(data);
            return data;
        } finally {
            loading.value = false;
        }
    }

    async function register(payload, turnstile_token) {
        loading.value = true;
        try {
            const body = { ...payload };
            if (turnstile_token) body.turnstile_token = turnstile_token;
            const { data } = await api.post('/auth/register', body);
            applyAuthPayload(data);
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

    async function updateProfile(payload) {
        loading.value = true;
        try {
            const { data } = await api.put('/auth/profile', payload);
            user.value = data.data?.user || data.data || user.value;
            persist();
            return user.value;
        } finally {
            loading.value = false;
        }
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
        loginPassword,
        register,
        fetchMe,
        updateProfile,
        logout,
    };
});
