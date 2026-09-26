import {openRadial} from './useRadialMenu';
import {tapHoldDisabled} from '../../utils/uiPreferences';

/**
 * v-long-press: press-and-hold to open the radial menu around the finger.
 *
 * Usage:
 *   v-long-press="() => actionsArray"     // lazy, built on trigger
 *   v-long-press="{ actions: () => [...], delay: 350 }"
 *
 * Moving past the threshold before the hold fires cancels it, so normal
 * scrolling still works. The opening pointer id is handed to the host so a
 * continuous drag can slide straight onto a petal and release to select.
 *
 * The current binding value is stashed on the element and refreshed in
 * `updated()`, so a changed action list (reactive edit, or HMR swapping the
 * component) takes effect without a full remount, the hold reads the live
 * value at trigger time rather than a stale mount-time snapshot.
 */
const DEFAULT_DELAY = 350;
const MOVE_CANCEL = 12; // px of movement that aborts the hold (treat as scroll)

function resolveActions(value) {
    const fn = typeof value === 'function' ? value : value?.actions;
    if (typeof fn === 'function') return fn() || [];
    return Array.isArray(value) ? value : [];
}

function resolveDelay(value) {
    return (typeof value === 'object' && value?.delay) || DEFAULT_DELAY;
}

export const longPress = {
    mounted(el, binding) {
        let timer = null;
        let startX = 0;
        let startY = 0;
        let startPointerId = null;

        // Live binding value; kept current by updated() below.
        el.__radialBinding = binding.value;

        const clear = () => {
            if (timer) {
                clearTimeout(timer);
                timer = null;
            }
        };

        const onDown = (e) => {
            // "Disable Tap and Hold" UI setting, read live so it needs no remount.
            if (tapHoldDisabled()) return;
            if (e.pointerType === 'mouse' && e.button !== 0) return;
            startX = e.clientX;
            startY = e.clientY;
            startPointerId = e.pointerId;
            clear();
            timer = setTimeout(() => {
                timer = null;
                const actions = resolveActions(el.__radialBinding);
                if (!actions.length) return;
                if (navigator.vibrate) navigator.vibrate(15);
                openRadial({x: startX, y: startY, actions, pointerId: startPointerId});
            }, resolveDelay(el.__radialBinding));
        };

        const onMove = (e) => {
            if (timer && (Math.abs(e.clientX - startX) > MOVE_CANCEL || Math.abs(e.clientY - startY) > MOVE_CANCEL)) {
                clear();
            }
        };

        const onUpOrCancel = () => clear();
        const onContextMenu = (e) => e.preventDefault();

        el.addEventListener('pointerdown', onDown);
        el.addEventListener('pointermove', onMove);
        el.addEventListener('pointerup', onUpOrCancel);
        el.addEventListener('pointercancel', onUpOrCancel);
        el.addEventListener('contextmenu', onContextMenu);

        el.__radialLongPressCleanup = () => {
            clear();
            el.removeEventListener('pointerdown', onDown);
            el.removeEventListener('pointermove', onMove);
            el.removeEventListener('pointerup', onUpOrCancel);
            el.removeEventListener('pointercancel', onUpOrCancel);
            el.removeEventListener('contextmenu', onContextMenu);
        };
    },
    updated(el, binding) {
        el.__radialBinding = binding.value;
    },
    unmounted(el) {
        el.__radialLongPressCleanup?.();
        delete el.__radialBinding;
    },
};
