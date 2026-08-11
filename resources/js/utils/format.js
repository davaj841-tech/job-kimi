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

/** Relative time in Persian (e.g. «۲ روز پیش») */
export function formatDistanceToNow(value) {
    if (!value) return '—';
    const d = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(d.getTime())) return '—';
    const diffSec = Math.round((Date.now() - d.getTime()) / 1000);
    const rtf = new Intl.RelativeTimeFormat('fa', { numeric: 'auto' });
    const abs = Math.abs(diffSec);
    if (abs < 60) return rtf.format(-diffSec, 'second');
    const mins = Math.round(diffSec / 60);
    if (Math.abs(mins) < 60) return rtf.format(-mins, 'minute');
    const hours = Math.round(mins / 60);
    if (Math.abs(hours) < 24) return rtf.format(-hours, 'hour');
    const days = Math.round(hours / 24);
    if (Math.abs(days) < 30) return rtf.format(-days, 'day');
    const months = Math.round(days / 30);
    if (Math.abs(months) < 12) return rtf.format(-months, 'month');
    return rtf.format(-Math.round(months / 12), 'year');
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
