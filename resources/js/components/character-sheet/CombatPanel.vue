<template>
    <transition name="slide-up">
        <div v-if="show" class="fixed inset-x-0 bottom-0 z-[70] bg-black/95 border-t border-red-900 shadow-[0_-10px_30px_rgba(220,38,38,0.3)] pb-10">
            <!-- Header/Handle -->
            <div class="flex items-center justify-between p-3 border-b border-red-900/50 bg-red-950/10">
                <div class="flex items-center gap-2">
                    <i v-icon data-lucide="crosshair" class="w-4 h-4 text-red-500"></i>
                    <span class="text-xs font-black uppercase tracking-[0.2em] text-red-500">Combat Systems</span>
                </div>
                <button @click="$emit('close')" class="p-2 text-gray-400 hover:text-white transition-colors">
                    <i v-icon data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="p-4 space-y-6 max-h-[70vh] overflow-y-auto">
                <!-- Weapon & Fire Mode -->
                <div v-if="selectedWeapon" class="space-y-4">
                    <div class="flex items-center justify-between bg-black/40 border border-gray-800 p-3 rounded-sm">
                        <div class="flex-1">
                            <div class="text-[10px] text-gray-500 uppercase font-bold mb-2">Select Fire Mode</div>
                            <div class="grid grid-cols-2 gap-2">
                                <button v-for="mode in availableFireModes" :key="mode.id"
                                        @click="$emit('update:fireMode', mode.id)"
                                        class="px-4 py-3 text-xs font-black uppercase border transition-all flex items-center justify-center gap-2 rounded-sm"
                                        :class="fireMode === mode.id ? 'bg-orange-600 border-orange-600 text-black' : 'border-gray-800 text-gray-400 hover:text-gray-200 hover:border-gray-600'">
                                    <i v-icon :data-lucide="mode.icon" class="w-4 h-4"></i> {{ mode.label }}
                                </button>
                            </div>
                        </div>
                        <div class="text-right pl-4 border-l border-gray-800 ml-4">
                            <div class="text-[10px] text-gray-500 uppercase font-bold mb-1">Target DV</div>
                            <div v-if="dvLoading" class="text-orange-500 animate-pulse font-mono font-bold">CALC...</div>
                            <div v-else-if="dvError" class="text-red-500 font-mono text-xs">ERR</div>
                            <div v-else class="text-3xl font-black text-white font-mono leading-none">{{ dvInfo || '--' }}</div>
                        </div>
                    </div>

                    <!-- Attack Actions -->
                    <div class="grid grid-cols-2 gap-3">
                        <button @click="$emit('roll-hit')"
                                :disabled="reloadRequired"
                                class="py-5 bg-red-600 text-black font-black uppercase tracking-tighter flex flex-col items-center justify-center hover:bg-red-500 transition-all active:scale-95 disabled:opacity-50 disabled:grayscale disabled:cursor-not-allowed rounded-sm">
                            <span class="text-2xl">Execute Attack</span>
                            <span v-if="reloadRequired" class="text-[10px] animate-pulse mt-1">Out of Ammo!</span>
                        </button>
                        <button @click="$emit('roll-damage')"
                                class="py-5 border-2 border-red-600 text-red-500 font-black uppercase tracking-tighter flex flex-col items-center justify-center hover:bg-red-600 hover:text-black transition-all active:scale-95 rounded-sm">
                            <span class="text-2xl">Roll Damage</span>
                        </button>
                    </div>

                    <button v-if="selectedWeapon.system.magazine"
                            @click="$emit('reload')"
                            class="w-full py-3 bg-gray-900 border border-gray-800 text-gray-400 font-bold uppercase text-xs tracking-[0.2em] hover:bg-gray-800 hover:text-white transition-all rounded-sm flex items-center justify-center gap-2">
                        <i v-icon data-lucide="rotate-cw" class="w-4 h-4"></i> Reload Systems
                    </button>
                </div>

                <div v-else class="text-center py-6 border border-dashed border-gray-800 rounded-sm">
                    <span class="text-xs text-gray-500 uppercase font-bold tracking-widest">No Weapon Selected</span>
                </div>


            </div>
        </div>
    </transition>
</template>

<script>
import { vIcon } from '../../directives/icon';

export default {
    name: 'CombatPanel',
    props: [
        'show', 'selectedWeapon', 'availableFireModes', 'fireMode',
        'dvLoading', 'dvError', 'dvInfo', 'reloadRequired'
    ],
    directives: { icon: vIcon },
    emits: ['close', 'roll-hit', 'roll-damage', 'reload', 'update:fireMode']
}
</script>

<style scoped>
.slide-up-enter-active,
.slide-up-leave-active {
    transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.slide-up-enter-from,
.slide-up-leave-to {
    transform: translateY(100%);
}
</style>
