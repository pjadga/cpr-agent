import { nextTick } from 'vue';

export const vIcon = {
    mounted(el) {
        renderIcon(el);
    },
    updated(el) {
        renderIcon(el);
    }
};

function renderIcon(el) {
    const name = el.getAttribute('data-lucide') || el._lucideName;
    if (!name) return;

    // Store the name and remove the attribute from the tracked element.
    // This prevents Lucide from replacing 'el' itself if createIcons is called globally.
    el._lucideName = name;
    el.removeAttribute('data-lucide');

    nextTick(() => {
        if (!window.lucide) return;

        // If the icon hasn't changed, don't re-render.
        if (el._renderedIcon === name) return;

        // Manage the icon as a child of the element Vue is tracking.
        el.innerHTML = `<i data-lucide="${name}" style="width: 100%; height: 100%; display: block;"></i>`;
        window.lucide.createIcons({ root: el });
        el._renderedIcon = name;

        // Ensure the container element is visible and properly sized.
        const style = getComputedStyle(el);
        if (style.display === 'inline') {
            el.style.display = 'inline-flex';
            el.style.alignItems = 'center';
            el.style.verticalAlign = 'middle';
        }
    });
}
