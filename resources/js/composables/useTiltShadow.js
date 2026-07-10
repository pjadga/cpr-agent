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

const BASE = { s1: 3, s2: 6 };   // rest offsets (px) — match the CSS defaults
const RANGE = { s1: 5, s2: 10 }; // ± travel (px) at full deflection
const FULL_TILT = 25;            // degrees of tilt that maps to full deflection

const clamp = (n, lo = -1, hi = 1) => Math.min(hi, Math.max(lo, n));

export function initTiltShadow(target = document.documentElement) {
    if (typeof window === 'undefined') return () => {};
    // Respect reduced-motion: leave the static 3px/6px defaults in place.
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return () => {};

    // Smooth numeric state, then a ticker writes it to the CSS vars each frame.
    const state = { s1x: BASE.s1, s1y: BASE.s1, s2x: BASE.s2, s2y: BASE.s2 };
    const ease = { duration: 0.5, ease: 'power2.out' };
    const to = {
        s1x: gsap.quickTo(state, 's1x', ease), s1y: gsap.quickTo(state, 's1y', ease),
        s2x: gsap.quickTo(state, 's2x', ease), s2y: gsap.quickTo(state, 's2y', ease),
    };

    const render = () => {
        target.style.setProperty('--shadow-s1-x', state.s1x.toFixed(2) + 'px');
        target.style.setProperty('--shadow-s1-y', state.s1y.toFixed(2) + 'px');
        target.style.setProperty('--shadow-s2-x', state.s2x.toFixed(2) + 'px');
        target.style.setProperty('--shadow-s2-y', state.s2y.toFixed(2) + 'px');
    };
    gsap.ticker.add(render);

    // nx / ny are normalised tilt in [-1, 1]; shadow moves the opposite way.
    const aim = (nx, ny) => {
        to.s1x(BASE.s1 - nx * RANGE.s1);
        to.s1y(BASE.s1 - ny * RANGE.s1);
        to.s2x(BASE.s2 - nx * RANGE.s2);
        to.s2y(BASE.s2 - ny * RANGE.s2);
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
        gsap.ticker.remove(render);
        window.removeEventListener('pointermove', onPointer);
        window.removeEventListener('deviceorientation', onOrient);
    };
}
