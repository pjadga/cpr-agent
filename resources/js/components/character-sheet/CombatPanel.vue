<template>
    <transition name="slide-up">
        <div v-if="show"
             class="fixed inset-x-0 bottom-0 z-[70] bg-cpr-black/95 border-t border-cpr-red-dark shadow-[0_-10px_30px_rgba(220,38,38,0.3)] pb-[calc(1rem+env(safe-area-inset-bottom))]">
            <!-- Header/Handle -->
            <div class="flex items-center justify-between p-3 border-b border-cpr-red-dark/50 bg-cpr-black-deep/10">
                <div class="flex items-center gap-2">
                    <i v-icon data-lucide="crosshair" class="w-4 h-4 text-cpr-red"></i>
                    <span class="text-xs font-black uppercase tracking-[0.2em] text-cpr-red">Combat Systems</span>
                </div>
                <button @click="$emit('close')" class="p-2 text-cpr-text-muted hover:text-cpr-yellow transition-colors">
                    <i v-icon data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="p-4 space-y-6 max-h-[70vh] overflow-y-auto">
                <!-- Weapon & Fire Mode -->
                <div v-if="selectedWeapon" class="space-y-4">
                    <div
                        class="flex items-center justify-between bg-cpr-black/40 border border-cpr-border p-3 rounded-sm">
                        <div class="flex-1">
                            <div class="text-[10px] text-cpr-text-dim uppercase font-bold mb-2">Select Fire Mode</div>
                            <div class="grid grid-cols-2 gap-2">
                                <button v-for="mode in availableFireModes" :key="mode.id"
                                        @click="$emit('update:fireMode', mode.id)"
                                        class="px-4 py-3 text-xs font-black uppercase border transition-all flex items-center justify-center gap-2 rounded-sm"
                                        :class="fireMode === mode.id ? 'bg-cpr-yellow-dark border-cpr-black-deep text-black' : 'border-cpr-border text-cpr-text-muted hover:text-cpr-text-main hover:border-cpr-border-muted'">
                                    <i v-icon :data-lucide="mode.icon" class="w-4 h-4"></i> {{ mode.label }}
                                </button>
                            </div>
                        </div>
                        <div class="text-right pl-4 border-l border-cpr-border ml-4">
                            <div class="text-[10px] text-cpr-text-dim uppercase font-bold mb-1">Target DV</div>
                            <div v-if="dvLoading" class="text-cpr-yellow-dark animate-pulse font-mono font-bold">
                                CALC...
                            </div>
                            <div v-else-if="dvError" class="text-cpr-red font-mono text-xs">ERR</div>
                            <div v-else class="text-3xl font-black text-cpr-yellow font-numbers leading-none">
                                {{ dvInfo || '--' }}
                            </div>
                        </div>
                    </div>

                    <!-- Attack Actions -->
                    <div class="grid grid-cols-2 gap-3">
                        <button @click="$emit('roll-hit')"
                                :disabled="reloadRequired"
                                class="py-4 bg-cpr-red text-black font-black uppercase tracking-tighter flex flex-col items-center justify-center hover:bg-cpr-red transition-all active:scale-95 disabled:opacity-50 disabled:grayscale disabled:cursor-not-allowed rounded-sm">
                            <span class="text-l">Execute Attack</span>
                            <span v-if="reloadRequired" class="text-[10px] animate-pulse mt-1">Out of Ammo!</span>
                        </button>
                        <button @click="$emit('roll-damage')"
                                class="py-4 border-2 border-cpr-red text-cpr-red font-black uppercase tracking-tighter flex flex-col items-center justify-center hover:bg-cpr-red hover:text-black transition-all active:scale-95 rounded-sm">
                            <span class="text-l">Roll Damage</span>
                        </button>
                    </div>

                    <button v-if="selectedWeapon.system.magazine"
                            @click="$emit('reload')"
                            class="w-full py-3 bg-cpr-black-deep border border-cpr-teal-dark text-cpr-text-muted font-bold uppercase text-xs tracking-[0.2em] hover:bg-cpr-black-elevated hover:text-cpr-yellow transition-all rounded-sm flex items-center justify-center gap-2">
                        <i v-icon data-lucide="rotate-cw" class="w-4 h-4"></i> Reload Systems
                    </button>
                </div>

                <div v-else class="text-center py-6 border border-dashed border-cpr-border rounded-sm">
                    <span
                        class="text-xs text-cpr-text-dim uppercase font-bold tracking-widest">No Weapon Selected</span>
                </div>

                <!-- Tactical Movement -->
                <div class="pt-4 border-t border-cpr-red-dark/30">
                    <div class="flex items-center justify-center gap-4 mb-3">
                        <div class="h-px bg-cpr-red-dark/30 flex-1"></div>
                        <span class="text-[10px] font-black text-cpr-red uppercase tracking-[0.3em]">Positioning</span>
                        <div class="h-px bg-cpr-red-dark/30 flex-1"></div>
                    </div>
                    <div class="grid grid-cols-3 gap-2 max-w-[150px] mx-auto pb-2">
                        <div></div>
                        <button @click="$emit('move', 'up')"
                                class="aspect-square bg-cpr-black-deep border border-cpr-red-dark/40 text-cpr-red flex items-center justify-center hover:bg-cpr-red hover:text-black active:scale-90 transition-all rounded-sm shadow-lg">
                            <i v-icon data-lucide="chevron-up" class="w-6 h-6"></i>
                        </button>
                        <div></div>
                        <button @click="$emit('move', 'left')"
                                class="aspect-square bg-cpr-black-deep border border-cpr-red-dark/40 text-cpr-red flex items-center justify-center hover:bg-cpr-red hover:text-black active:scale-90 transition-all rounded-sm shadow-lg">
                            <i v-icon data-lucide="chevron-left" class="w-6 h-6"></i>
                        </button>
                        <button @click="$emit('move', 'down')"
                                class="aspect-square bg-cpr-black-deep border border-cpr-red-dark/40 text-cpr-red flex items-center justify-center hover:bg-cpr-red hover:text-black active:scale-90 transition-all rounded-sm shadow-lg">
                            <i v-icon data-lucide="chevron-down" class="w-6 h-6"></i>
                        </button>
                        <button @click="$emit('move', 'right')"
                                class="aspect-square bg-cpr-black-deep border border-cpr-red-dark/40 text-cpr-red flex items-center justify-center hover:bg-cpr-red hover:text-black active:scale-90 transition-all rounded-sm shadow-lg">
                            <i v-icon data-lucide="chevron-right" class="w-6 h-6"></i>
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </transition>
</template>

<script>
import {vIcon} from '../../directives/icon';

export default {
    name: 'CombatPanel',
    props: [
        'show', 'selectedWeapon', 'availableFireModes', 'fireMode',
        'dvLoading', 'dvError', 'dvInfo', 'reloadRequired'
    ],
    directives: {icon: vIcon},
    emits: ['close', 'roll-hit', 'roll-damage', 'reload', 'move', 'update:fireMode']
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
