<template>
    <div class="fixed right-4 top-24 z-[100] flex flex-col gap-2 items-end pointer-events-none">
        <transition-group
            name="roll-fade"
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="translate-x-full opacity-0"
            enter-to-class="translate-x-0 opacity-100"
            leave-active-class="transition duration-300 ease-in absolute"
            leave-from-class="translate-x-0 opacity-100"
            leave-to-class="translate-x-full opacity-0"
            move-class="transition duration-300 ease-in-out"
        >
            <div v-for="roll in activeRolls" :key="roll.id"
                 class="bg-cpr-black-deep/95 border-r-4 border-cpr-red p-3 shadow-[0_0_20px_rgba(0,0,0,0.5)] min-w-[160px] pointer-events-auto backdrop-blur-md relative overflow-hidden group">
                <!-- Glitch background effect -->
                <div class="absolute inset-0 bg-cpr-red/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>

                <div class="flex flex-col">
                    <div
                        class="text-[9px] text-cpr-red font-black uppercase tracking-[0.2em] mb-1 flex items-center gap-2">
                        <span class="w-1.5 h-1.5 bg-cpr-red animate-pulse"></span>
                        {{ roll.playerName }}
                    </div>

                    <div class="flex items-end justify-between gap-4 border-t border-cpr-red/20 pt-2">
                        <div class="text-3xl font-black text-cpr-yellow font-numbers leading-none tracking-tighter">
                            {{ roll.total }}
                        </div>
                        <div v-if="roll.flavor"
                             class="text-[8px] text-cpr-text-dim uppercase font-bold tracking-widest max-w-[100px] truncate pb-0.5">
                            {{ roll.flavor }}
                        </div>
                    </div>

                    <!-- Progress bar for timeout -->
                    <div
                        class="absolute bottom-0 left-0 h-0.5 bg-cpr-red/40 animate-[timeout-bar_3s_linear_forwards]"></div>
                </div>
            </div>
        </transition-group>
    </div>
</template>

<script setup>
import {onMounted, onUnmounted, ref} from 'vue';

const activeRolls = ref([]);
let rollId = 0;

const handleRollEvent = (e) => {
    // Both echo event and direct emitter support
    const payload = e.payload || e;

    if (payload.type === 'DICE_ROLL_EVENT' || payload.type === 'ROLL_RESULT') {
        const id = rollId++;
        const newRoll = {
            id,
            playerName: payload.playerName || payload.rawRoll?.playerName || 'Unknown',
            total: payload.total || payload.result || 0,
            flavor: payload.flavor || payload.label || payload.rawRoll?.rollTitle || ''
        };

        activeRolls.value.push(newRoll);

        // Remove after 3 seconds
        setTimeout(() => {
            const index = activeRolls.value.findIndex(r => r.id === id);
            if (index !== -1) {
                activeRolls.value.splice(index, 1);
            }
        }, 3000);
    }
};

onMounted(() => {
    // Also listen for local events just in case
    if (window.emitter) {
        window.emitter.on('dice-roll-event', handleRollEvent);
    }
});

onUnmounted(() => {
    if (window.emitter) {
        window.emitter.off('dice-roll-event', handleRollEvent);
    }
});
</script>

<style @cspNonce scoped>
@keyframes timeout-bar {
    from {
        width: 100%;
    }
    to {
        width: 0%;
    }
}

.roll-fade-move {
    transition: transform 0.3s ease;
}
</style>
