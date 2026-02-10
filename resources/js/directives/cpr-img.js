export const vCprImg = {
    mounted(el, binding) {
        if (binding.value) {
            el.src = binding.value;
        }
    },
    updated(el, binding) {
        if (binding.value !== binding.oldValue && binding.value) {
            el.src = binding.value;
        }
    }
};
