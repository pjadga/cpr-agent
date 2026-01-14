import { nextTick } from 'vue';

export const vIcon = {
    mounted(el) {
        nextTick(() => {
            if (window.lucide && el.parentElement) {
                window.lucide.createIcons({ root: el.parentElement });
            }
        });
    },
    updated(el) {
        nextTick(() => {
            if (window.lucide && el.parentElement) {
                window.lucide.createIcons({ root: el.parentElement });
            }
        });
    }
};
