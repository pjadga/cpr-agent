<template>
    <transition name="radial-fade">
        <div v-if="radialState.open"
             class="fixed inset-0 z-[300] select-none touch-none bg-cpr-black/65"
             @pointerdown.self="onBackdropDown"
             @click.self="closeRadial">

            <!-- Finger anchor pip -->
            <div class="radial-pip" :style="{ left: radialState.x + 'px', top: radialState.y + 'px' }"></div>

            <!-- Petals -->
            <button
                v-for="(a, i) in radialState.actions"
                :key="a.id || i"
                :ref="el => (petalEls[i] = el)"
                type="button"
                class="radial-petal group"
                :class="{
                    'radial-petal--on': highlightIndex === i,
                    'radial-petal--dim': highlightIndex !== -1 && highlightIndex !== i && !a.disabled,
                    'radial-petal--off': a.disabled,
                }"
                :style="petalStyle(i)"
                @pointerup.stop.prevent="onPetalUp(i)"
                @pointerenter="!isDragPointer && (highlightIndex = i)"
                @pointerleave="!isDragPointer && highlightIndex === i && (highlightIndex = -1)">
                <mobile-select-frame :style="{ '--frame-border': petalBorder(a, i) }"/>
                <span class="relative z-10 flex flex-col items-center justify-center gap-0.5 pointer-events-none px-1">
                    <i v-icon :data-lucide="a.icon || 'circle'"
                       class="w-5 h-5 transition-colors"
                       :style="{ color: highlightIndex === i ? 'var(--cpr-yellow)' : (a.color || 'var(--cpr-text-main)') }"></i>
                    <span v-if="a.label"
                          class="text-[8px] font-black uppercase tracking-tight leading-none text-center transition-colors"
                          :style="{ color: highlightIndex === i ? 'var(--cpr-yellow)' : 'var(--cpr-text-main)' }">{{
                            a.label
                        }}</span>
                </span>
            </button>
        </div>
    </transition>
</template>

<script>
import {nextTick, ref, watch} from 'vue';
import gsap from 'gsap';
import {vIcon} from '../../directives/icon';
import MobileSelectFrame from '../../components/character-sheet/frames/MobileSelectFrame.vue';
import {radialState, closeRadial, selectRadial} from './useRadialMenu';

const PETAL = 76;   // petal box (px)
const EDGE_PAD = 10;

export default {
    name: 'RadialMenuHost',
    components: {MobileSelectFrame},
    directives: {icon: vIcon},
    setup() {
        const petalEls = ref([]);
        const highlightIndex = ref(-1);
        const layout = ref([]);          // [{ left, top, angleDeg }]
        // True while the opening finger is still down (drag-to-select). Petal
        // hover events are ignored in that mode; angle tracking drives highlight.
        const isDragPointer = ref(false);

        const radius = () => {
            const n = radialState.actions.length || 1;
            return Math.min(150, Math.max(104, 66 + n * 9));
        };
        const deadzone = () => radius() * 0.45;

        const buildLayout = () => {
            const n = radialState.actions.length;
            const r = radius();
            const vw = window.innerWidth;
            const vh = window.innerHeight;
            const margin = r + PETAL / 2 + EDGE_PAD;
            const cx = Math.min(vw - margin, Math.max(margin, radialState.originX));
            const cy = Math.min(vh - margin, Math.max(margin, radialState.originY));
            radialState.x = cx;
            radialState.y = cy;
            const out = [];
            for (let i = 0; i < n; i++) {
                const angleDeg = -90 + (360 / n) * i;   // first at 12 o'clock, clockwise
                const rad = (angleDeg * Math.PI) / 180;
                const px = cx + r * Math.cos(rad);
                const py = cy + r * Math.sin(rad);
                out.push({left: px - PETAL / 2, top: py - PETAL / 2, angleDeg});
            }
            layout.value = out;
        };

        const petalStyle = (i) => {
            const l = layout.value[i];
            if (!l) return {display: 'none'};
            return {left: `${l.left}px`, top: `${l.top}px`, width: `${PETAL}px`, height: `${PETAL}px`};
        };

        const petalBorder = (a, i) => {
            if (highlightIndex.value === i) return 'var(--cpr-yellow)';
            if (a.color) return a.color;
            return 'var(--cpr-border)';
        };

        // --- bloom animation --------------------------------------------------
        const bloom = () => {
            const cx = radialState.x;
            const cy = radialState.y;
            petalEls.value.forEach((el, i) => {
                if (!el) return;
                const l = layout.value[i];
                const fromX = cx - PETAL / 2 - l.left;
                const fromY = cy - PETAL / 2 - l.top;
                gsap.fromTo(el,
                    {x: fromX, y: fromY, scale: 0.2, opacity: 0},
                    {x: 0, y: 0, scale: 1, opacity: 1, duration: 0.34, ease: 'back.out(1.5)', delay: i * 0.035});
            });
        };

        // --- pointer tracking (drag-to-select) --------------------------------
        const onWindowMove = (e) => {
            if (!radialState.open) return;
            const dx = e.clientX - radialState.x;
            const dy = e.clientY - radialState.y;
            const dist = Math.hypot(dx, dy);
            if (dist < deadzone()) {
                highlightIndex.value = -1;
                return;
            }
            isDragPointer.value = true;
            let best = -1;
            let bestDiff = Infinity;
            const ang = (Math.atan2(dy, dx) * 180) / Math.PI; // 0 = east
            layout.value.forEach((l, i) => {
                const diff = Math.abs(((ang - l.angleDeg + 540) % 360) - 180); // smaller diff = closer
                if (diff < bestDiff) {
                    bestDiff = diff;
                    best = i;
                }
            });
            highlightIndex.value = best;
        };

        const onWindowUp = () => {
            if (!radialState.open) return;
            const idx = highlightIndex.value;
            if (idx >= 0) {
                fireSelect(idx);
                return;
            }
            // Released with nothing highlighted: a quick release right after the
            // long-press = "sticky" open (wait for taps); a slow one = cancel.
            if (Date.now() - radialState.openedAt >= 260) {
                closeRadial();
            }
        };

        const onPetalUp = (i) => {
            if (radialState.actions[i]?.disabled) return;
            fireSelect(i);
        };

        const fireSelect = (i) => {
            // small confirm pop before firing
            const el = petalEls.value[i];
            if (el) gsap.to(el, {scale: 1.25, duration: 0.12, yoyo: true, repeat: 1});
            selectRadial(i);
        };

        const onBackdropDown = () => { /* swallow to keep pointer capture sane */ };

        // The finger that opened the menu is still owned by the source row, whose
        // touch-action allows vertical panning, so without this the browser scrolls
        // the page and fires pointercancel, killing the angle tracking. While the
        // menu is open we cancel the scroll so the drag keeps flowing to onWindowMove.
        const preventScroll = (e) => { if (radialState.open) e.preventDefault(); };

        watch(() => radialState.open, async (open) => {
            if (open) {
                highlightIndex.value = -1;
                isDragPointer.value = false;
                buildLayout();
                await nextTick();
                bloom();
                window.addEventListener('pointermove', onWindowMove);
                window.addEventListener('pointerup', onWindowUp);
                window.addEventListener('pointercancel', onWindowUp);
                document.addEventListener('touchmove', preventScroll, {passive: false});
            } else {
                petalEls.value = [];
                window.removeEventListener('pointermove', onWindowMove);
                window.removeEventListener('pointerup', onWindowUp);
                window.removeEventListener('pointercancel', onWindowUp);
                document.removeEventListener('touchmove', preventScroll, {passive: false});
            }
        });

        return {
            radialState,
            petalEls,
            highlightIndex,
            isDragPointer,
            petalStyle,
            petalBorder,
            onPetalUp,
            onBackdropDown,
            closeRadial,
            selectRadial,
        };
    },
};
</script>

<style scoped>
.radial-petal {
    position: fixed;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    background: transparent;
    border: 0;
    cursor: pointer;
    touch-action: none;
    transition: filter 0.15s ease, background-color 0.15s ease;
}

.radial-petal :deep(.frame__layer--s1),
.radial-petal :deep(.frame__layer--s2) {
    display: none;
}

.radial-petal--on {
    background-color: var(--cpr-panel-hover);
    border-radius: 10px;
    filter: drop-shadow(0 0 12px rgba(234, 179, 8, 0.55));
    z-index: 2;
}

/* Non-highlighted petals stay at full brightness, the yellow highlight already
   carries the selection; dimming the rest only hurt readability. */
.radial-petal--dim {
    /* intentionally empty */
}

.radial-petal--off {
    opacity: 0.4;
    pointer-events: none;
}

.radial-pip {
    position: fixed;
    width: 14px;
    height: 14px;
    margin: -7px 0 0 -7px;
    border-radius: 9999px;
    border: 2px solid var(--cpr-yellow);
    background: rgba(0, 0, 0, 0.4);
    box-shadow: 0 0 10px rgba(234, 179, 8, 0.5);
    pointer-events: none;
}

.radial-fade-enter-active,
.radial-fade-leave-active {
    transition: opacity 0.2s ease;
}

.radial-fade-enter-from,
.radial-fade-leave-to {
    opacity: 0;
}
</style>
