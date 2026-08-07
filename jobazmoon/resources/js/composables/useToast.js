import { reactive } from 'vue';

const state = reactive({
    visible: false,
    message: '',
    type: 'info', // info | success | error
});

let timer;

export function useToast() {
    function show(message, type = 'info', ms = 2800) {
        state.message = message;
        state.type = type;
        state.visible = true;
        clearTimeout(timer);
        timer = setTimeout(() => {
            state.visible = false;
        }, ms);
    }

    return {
        state,
        show,
        success: (msg) => show(msg, 'success'),
        error: (msg) => show(msg, 'error'),
        info: (msg) => show(msg, 'info'),
    };
}
