import { formatJalaliDate, formatJalaliDateTime } from './jalali';

export function formatPrice(value, { freeLabel = 'رایگان' } = {}) {
    const n = Number(value || 0);
    if (n === 0) return freeLabel;
    return `${new Intl.NumberFormat('fa-IR').format(n)} ریال`;
}

export function formatDate(value) {
    return formatJalaliDate(value);
}

export function formatDateTime(value) {
    return formatJalaliDateTime(value);
}

export function toFaDigits(input) {
    return String(input ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
}

/** Unwrap Laravel successResponse + ResourceCollection shapes */
export function unwrapList(payload) {
    const data = payload?.data ?? payload;
    if (Array.isArray(data)) return data;
    if (Array.isArray(data?.data)) return data.data;
    if (Array.isArray(data?.data?.data)) return data.data.data;
    if (Array.isArray(data?.items)) return data.items;
    return [];
}

export function unwrapMeta(payload) {
    const data = payload?.data ?? payload;
    return data?.meta || null;
}

export function unwrapItem(payload) {
    return payload?.data ?? payload;
}

export function apiErrorMessage(error, fallback = 'خطایی رخ داد.') {
    const errors = error?.response?.data?.errors;
    if (errors && typeof errors === 'object') {
        const first = Object.values(errors).flat()[0];
        if (first) return first;
    }
    return error?.response?.data?.message || error?.message || fallback;
}
