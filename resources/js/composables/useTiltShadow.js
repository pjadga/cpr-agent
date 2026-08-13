import gsap from 'gsap';

/**
 * Drives the frame drop-shadow layers from device tilt (gyro) with a desktop
 * pointer fallback. The generated frames read four CSS vars for their two
 * shadow layers:
 *   --shadow-s1-x/y  (front shadow, default 3px)
 *   --shadow-s2-x/y  (back shadow,  default 6px, moves further → parallax depth)
 * We set them on <html> so every frame on the page shares one light source.
 *
 * The shadow shifts opposite to the tilt (as if a fixed light stays put while
 * the device turns), and the deeper layer travels further, so the stacked
 * outline copies fan out into a sense of depth as the phone moves.
 *
 * Call once after mount: initTiltShadow().
 */

const BASE = { s1: 3, s2: 6 };   // rest offsets (px), match the CSS defaults
const RANGE = { s1: 5, s2: 10 }; // ± travel (px) at full deflection
const FULL_TILT = 25;            // degrees of tilt that maps to full deflection

// Smoothing time constants. quickTo re-eases toward each sensor reading, so the
// shadow trails the phone by roughly half the duration — this is the "lag". The
// front layer tracks tight (feels responsive); the back layer trails a touch, and
// that timing gap is what reads as parallax depth rather than the two in lockstep.
// Keep a little smoothing so raw sensor noise doesn't jitter through.
const EASE = { s1: 0.18, s2: 0.32 };

const clamp = (n, lo = -1, hi = 1) => Math.min(hi, Math.max(lo, n));

export function initTiltShadow(target = document.documentElement) {
    if (typeof window === 'undefined') return () => {};
    // Respect reduced-motion: leave the static 3px/6px defaults in place.
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return () => {};

    // Smooth numeric state, driven by GSAP's eased quickTo tweens. power3.out
    // settles crisply; the front/back layers use different durations (see EASE).
    const state = { s1x: BASE.s1, s1y: BASE.s1, s2x: BASE.s2, s2y: BASE.s2 };
    const s1Ease = { duration: EASE.s1, ease: 'power3.out' };
    const s2Ease = { duration: EASE.s2, ease: 'power3.out' };
    const to = {
        s1x: gsap.quickTo(state, 's1x', s1Ease), s1y: gsap.quickTo(state, 's1y', s1Ease),
        s2x: gsap.quickTo(state, 's2x', s2Ease), s2y: gsap.quickTo(state, 's2y', s2Ease),
    };

    // Mobile lag came from repainting every frame: the shadow layers use
    // filter: brightness(), so writing a fresh sub-pixel value (.toFixed(2))
    // 60×/sec forced a full shadow repaint on every visible row every frame —
    // even while the phone sat still. Two changes fix that:
    //   1. Snap to whole pixels — across the ±5/±10px travel that's a handful
    //      of distinct values, so most frames write nothing and skip the repaint.
    //   2. Idle the ticker once the eased motion settles, and resume it only on
    //      new input, so a steady phone costs zero repaints.
    const VARS = { s1x: '--shadow-s1-x', s1y: '--shadow-s1-y', s2x: '--shadow-s2-x', s2y: '--shadow-s2-y' };
    const written = { s1x: Math.round(BASE.s1), s1y: Math.round(BASE.s1), s2x: Math.round(BASE.s2), s2y: Math.round(BASE.s2) };
    let running = false;
    let idleFrames = 0;

    const render = () => {
        let changed = false;
        for (const k in VARS) {
            const v = Math.round(state[k]);
            if (v !== written[k]) {
                written[k] = v;
                target.style.setProperty(VARS[k], v + 'px');
                changed = true;
            }
        }
        // ~20 still frames (a third of a second) and we stop repainting entirely.
        if (changed) idleFrames = 0;
        else if (++idleFrames > 20) { gsap.ticker.remove(render); running = false; }
    };

    const wake = () => { if (!running) { running = true; idleFrames = 0; gsap.ticker.add(render); } };

    // nx / ny are normalised tilt in [-1, 1]; shadow moves the opposite way.
    const aim = (nx, ny) => {
        to.s1x(BASE.s1 - nx * RANGE.s1);
        to.s1y(BASE.s1 - ny * RANGE.s1);
        to.s2x(BASE.s2 - nx * RANGE.s2);
        to.s2y(BASE.s2 - ny * RANGE.s2);
        wake();
    };

    // Device orientation: first reading is the neutral pose, so it works upright
    // or flat. gamma = left/right tilt, beta = front/back tilt.
    let neutral = null;
    const onOrient = (e) => {
        if (e.gamma == null || e.beta == null) return;
        if (!neutral) neutral = { g: e.gamma, b: e.beta };
        aim(clamp((e.gamma - neutral.g) / FULL_TILT), clamp((e.beta - neutral.b) / FULL_TILT));
    };

    // Desktop fallback: cursor position relative to the viewport centre.
    const onPointer = (e) => {
        aim(clamp((e.clientX / window.innerWidth - 0.5) * 2),
            clamp((e.clientY / window.innerHeight - 0.5) * 2));
    };

    let usingGyro = false;
    const enableGyro = () => {
        usingGyro = true;
        window.removeEventListener('pointermove', onPointer);
        window.addEventListener('deviceorientation', onOrient, { passive: true });
    };

    // iOS 13+ needs an explicit permission grant from a user gesture; elsewhere
    // the event just fires. Until gyro is confirmed, drive from the pointer.
    window.addEventListener('pointermove', onPointer, { passive: true });
    const DOE = window.DeviceOrientationEvent;
    if (DOE && typeof DOE.requestPermission === 'function') {
        const ask = () => {
            DOE.requestPermission().then((r) => { if (r === 'granted') enableGyro(); }).catch(() => {});
            window.removeEventListener('touchend', ask);
            window.removeEventListener('click', ask);
        };
        window.addEventListener('touchend', ask, { once: true });
        window.addEventListener('click', ask, { once: true });
    } else if (DOE) {
        // Non-iOS: confirm the sensor actually emits before dropping the pointer.
        window.addEventListener('deviceorientation', function probe(e) {
            window.removeEventListener('deviceorientation', probe);
            if (e.gamma != null || e.beta != null) enableGyro();
        }, { passive: true });
    }

    return () => {
        if (running) { gsap.ticker.remove(render); running = false; }
        window.removeEventListener('pointermove', onPointer);
        window.removeEventListener('deviceorientation', onOrient);
    };
}
