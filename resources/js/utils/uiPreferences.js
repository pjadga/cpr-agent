/**
 * Client-side UI preferences, persisted in localStorage so they survive across
 * the Blade-rendered pages (no backend column needed — same pattern as rollMode).
 *
 * Keys are read live at the point of use (boot for reduce-motion, each
 * pointerdown for tap-and-hold) so a change on the Settings page takes effect on
 * the next page load without any extra wiring.
 */
export const UI_PREF_KEYS = {
    reduceMotion: 'cpr-reduce-motion',
    disableTapHold: 'cpr-disable-tap-hold',
};

function getBoolPref(key) {
    try {
        return localStorage.getItem(key) === '1';
    } catch (e) {
        return false;
    }
}

/** Reduce Motion: turns off gyro tilt and removes the shadow-depth effect. */
export function reduceMotionEnabled() {
    return getBoolPref(UI_PREF_KEYS.reduceMotion);
}

/** Disable Tap and Hold: suppresses the press-and-hold radial menu. */
export function tapHoldDisabled() {
    return getBoolPref(UI_PREF_KEYS.disableTapHold);
}
