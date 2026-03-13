export const vCprImg = {
    mounted(el, binding) {
        el.onerror = () => {
            el.src = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
            const color = binding.arg ? `var(--color-${binding.arg})` : 'var(--cpr-red)';
            el.style.backgroundColor = color;
        };
        if (binding.value) {
            el.src = binding.value;
        }
    },
    updated(el, binding) {
        if (binding.value !== binding.oldValue && binding.value) {
            el.src = binding.value;
            el.style.backgroundColor = '';
        }
    }
};
