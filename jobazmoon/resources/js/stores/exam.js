import { defineStore } from 'pinia';
import { ref } from 'vue';

const KEY = 'offline_exam_attempt';

export const useExamStore = defineStore('exam', () => {
    const current = ref(null);
    const answers = ref({});
    const endsAt = ref(null);
    const dirty = ref(false);
    const lastSyncedAt = ref(null);
    const offline = ref(typeof navigator !== 'undefined' ? !navigator.onLine : false);
    const pageIndex = ref(0);

    function loadCache() {
        const raw = localStorage.getItem(KEY);
        if (!raw) return null;
        try {
            const parsed = JSON.parse(raw);
            current.value = parsed.current;
            answers.value = parsed.answers || {};
            endsAt.value = parsed.endsAt;
            dirty.value = Boolean(parsed.dirty);
            lastSyncedAt.value = parsed.lastSyncedAt || null;
            pageIndex.value = parsed.pageIndex || 0;
            return parsed;
        } catch {
            return null;
        }
    }

    function saveCache() {
        localStorage.setItem(
            KEY,
            JSON.stringify({
                current: current.value,
                answers: answers.value,
                endsAt: endsAt.value,
                dirty: dirty.value,
                lastSyncedAt: lastSyncedAt.value,
                pageIndex: pageIndex.value,
            })
        );
    }

    function clearCache() {
        current.value = null;
        answers.value = {};
        endsAt.value = null;
        dirty.value = false;
        lastSyncedAt.value = null;
        pageIndex.value = 0;
        localStorage.removeItem(KEY);
    }

    function setPage(index) {
        pageIndex.value = index;
        saveCache();
    }

    function setAnswer(questionId, value) {
        answers.value = { ...answers.value, [questionId]: value };
        dirty.value = true;
        saveCache();
    }

    function markSynced() {
        dirty.value = false;
        lastSyncedAt.value = new Date().toISOString();
        saveCache();
    }

    function setOffline(value) {
        offline.value = value;
    }

    return {
        current,
        answers,
        endsAt,
        dirty,
        lastSyncedAt,
        offline,
        pageIndex,
        loadCache,
        saveCache,
        clearCache,
        setAnswer,
        markSynced,
        setOffline,
        setPage,
    };
});
