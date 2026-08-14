import {reactive} from 'vue';

/**
 * Singleton state for the radial "Mobile Select" menu.
 *
 * One host (<radial-menu-host>) renders this; any number of triggers open it
 * with a data-driven action array. An action is:
 *   { id, icon, label, color?, disabled?, handler }
 * `icon` is a lucide name; `handler` runs on select; `color` (optional CSS
 * colour) tints the petal border + icon.
 */
export const radialState = reactive({
    open: false,
    x: 0,               // menu centre (viewport px), clamped so petals stay on-screen
    y: 0,
    originX: 0,         // raw touch point (petals bloom out from here)
    originY: 0,
    actions: [],
    highlightIndex: -1,
    pointerId: null,    // pointer that opened via long-press (drag-to-select), or null
    openedAt: 0,
});

export function openRadial({x, y, actions, pointerId = null}) {
    if (!actions || !actions.length) return;
    radialState.originX = x;
    radialState.originY = y;
    radialState.x = x;
    radialState.y = y;
    radialState.actions = actions;
    radialState.highlightIndex = -1;
    radialState.pointerId = pointerId;
    radialState.openedAt = Date.now();
    radialState.open = true;
}

export function closeRadial() {
    radialState.open = false;
    radialState.actions = [];
    radialState.highlightIndex = -1;
    radialState.pointerId = null;
}

export function selectRadial(index) {
    const action = radialState.actions[index];
    closeRadial();
    if (action && !action.disabled && typeof action.handler === 'function') {
        action.handler();
    }
}

export function useRadialMenu() {
    return {radialState, openRadial, closeRadial, selectRadial};
}
