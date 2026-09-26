<template>
    <transition name="slide-up">
        <div v-if="show"
             class="fixed inset-x-0 bottom-0 z-[70] bg-cpr-black/95 border-t border-cpr-red-dark shadow-[0_-10px_30px_rgba(220,38,38,0.3)] pb-[calc(1rem+env(safe-area-inset-bottom))]">
            <!-- Header/Handle -->
            <div class="flex items-center justify-between p-3 border-b border-cpr-red-dark/50 bg-cpr-black-deep/10">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-black uppercase tracking-[0.2em] text-cpr-red">Combat Systems</span>
                </div>
                <button @click="$emit('close')" class="p-2 text-cpr-text-muted hover:text-cpr-yellow transition-colors">
                    <i v-icon data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="p-4 space-y-6 max-h-[70vh] overflow-y-auto">
                <!-- Weapon & Fire Mode -->
                <div v-if="selectedWeapon" class="space-y-4">
                    <div class="flex items-stretch justify-between gap-4">
                        <div class="flex-1">
                            <div class="text-[10px] text-cpr-text-dim uppercase font-bold mb-2">Select Fire Mode</div>
                            <div class="grid grid-cols-2 gap-2">
                                <button v-for="mode in availableFireModes" :key="mode.id"
                                        @click="$emit('update:fireMode', mode.id)"
                                        class="relative px-4 py-3 text-xs font-black uppercase transition-all flex items-center justify-center group"
                                        :style="{ '--frame-border': fireMode === mode.id ? 'var(--cpr-yellow)' : 'var(--cpr-border)' }"
                                        :class="fireMode === mode.id ? 'text-cpr-yellow' : 'text-cpr-text-muted hover:text-cpr-text-main hover:[--frame-colour-change:var(--cpr-border-muted)]'">
                                    <med-stat-frame/>
                                    <span class="relative z-10 flex items-center gap-2"><i v-icon :data-lucide="mode.icon" class="w-4 h-4"></i> {{ mode.label }}</span>
                                </button>
                            </div>
                        </div>
                        <div class="relative flex flex-col items-center justify-center px-5 min-w-[92px] transition-transform"
                             :class="selectedWeapon ? 'cursor-pointer active:scale-95' : ''"
                             :style="{ '--frame-border': 'var(--cpr-yellow-dark)' }"
                             :title="selectedWeapon ? 'Tap for the full range DV table' : ''"
                             @click="selectedWeapon && (showDvTable = true)">
                            <med-stat-frame/>
                            <i v-if="selectedWeapon" v-icon data-lucide="table"
                               class="absolute top-1 right-1 z-10 w-3 h-3 text-cpr-yellow-dark/70"></i>
                            <div class="relative z-10 text-[10px] text-cpr-text-dim uppercase font-bold mb-1">Target DV</div>
                            <div v-if="dvLoading" class="relative z-10 text-cpr-yellow-dark animate-pulse font-mono font-bold">
                                CALC...
                            </div>
                            <div v-else-if="dvError" class="relative z-10 text-cpr-red font-mono text-xs">ERR</div>
                            <div v-else class="relative z-10 text-3xl font-black text-cpr-yellow font-numbers leading-none">
                                {{ dvInfo || '--' }}
                            </div>
                        </div>
                    </div>

                    <!-- Attack Actions -->
                    <div class="grid grid-cols-2 gap-3">
                        <button @click="$emit('roll-hit')"
                                :disabled="reloadRequired"
                                class="relative py-4 text-cpr-red font-black uppercase tracking-tighter flex flex-col items-center justify-center transition-all active:scale-95 group hover:[--frame-colour-change:var(--cpr-red)] disabled:opacity-50 disabled:grayscale disabled:cursor-not-allowed"
                                :style="{ '--frame-border': 'var(--cpr-red)' }">
                            <med-stat-frame/>
                            <span class="relative z-10 text-l">Execute Attack</span>
                            <span v-if="reloadRequired" class="relative z-10 text-[10px] animate-pulse mt-1">Out of Ammo!</span>
                        </button>
                        <button @click="$emit('roll-damage')"
                                class="relative py-4 text-cpr-red font-black uppercase tracking-tighter flex flex-col items-center justify-center transition-all active:scale-95 group hover:[--frame-colour-change:var(--cpr-red)]"
                                :style="{ '--frame-border': 'var(--cpr-red)' }">
                            <med-stat-frame/>
                            <span class="relative z-10 text-l">Roll Damage</span>
                        </button>
                    </div>

                    <button v-if="selectedWeapon.system.magazine"
                            @click="$emit('reload')"
                            class="relative w-full py-3 text-cpr-text-muted font-bold uppercase text-xs tracking-[0.2em] hover:text-cpr-yellow hover:[--frame-colour-change:var(--cpr-teal)] transition-all flex items-center justify-center group"
                            :style="{ '--frame-border': 'var(--cpr-teal-dark)' }">
                        <med-stat-frame/>
                        <span class="relative z-10 flex items-center gap-2"><i v-icon data-lucide="rotate-cw" class="w-4 h-4"></i> Reload Systems</span>
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
                    <div class="grid grid-cols-3 gap-2 max-w-[250px] mx-auto pb-2"
                         :style="{ '--frame-border': 'var(--cpr-red-dark)' }">
                        <div></div>
                        <button @click="$emit('move', 'up')"
                                class="relative aspect-square flex items-center justify-center text-cpr-red hover:[--frame-colour-change:var(--cpr-red)] active:scale-90 transition-all group cursor-pointer">
                            <simple-stat-frame class="rotate-0"/>
                            <i v-icon data-lucide="chevron-up" class="relative z-10 w-6 h-6 pointer-events-none"></i>
                        </button>
                        <div></div>
                        <button @click="$emit('move', 'left')"
                                class="relative aspect-square flex items-center justify-center text-cpr-red hover:[--frame-colour-change:var(--cpr-red)] active:scale-90 transition-all group cursor-pointer">
                            <simple-stat-frame class="-rotate-90"/>
                            <i v-icon data-lucide="chevron-left" class="relative z-10 w-6 h-6 pointer-events-none"></i>
                        </button>
                        <button @click="$emit('move', 'down')"
                                class="relative aspect-square flex items-center justify-center text-cpr-red hover:[--frame-colour-change:var(--cpr-red)] active:scale-90 transition-all group cursor-pointer">
                            <simple-stat-frame class="rotate-180"/>
                            <i v-icon data-lucide="chevron-down" class="relative z-10 w-6 h-6 pointer-events-none"></i>
                        </button>
                        <button @click="$emit('move', 'right')"
                                class="relative aspect-square flex items-center justify-center text-cpr-red hover:[--frame-colour-change:var(--cpr-red)] active:scale-90 transition-all group cursor-pointer">
                            <simple-stat-frame class="rotate-90"/>
                            <i v-icon data-lucide="chevron-right" class="relative z-10 w-6 h-6 pointer-events-none"></i>
                        </button>
                    </div>
                </div>

            </div>

            <dv-table-modal :show="showDvTable" :weapon="selectedWeapon" :target-distance="targetDistance"
                            @close="showDvTable = false"></dv-table-modal>
        </div>
    </transition>
</template>

<script>
import {vIcon} from '../../directives/icon';
import MedStatFrame from './frames/MedStatFrame.vue';
import SimpleStatFrame from './frames/SimpleStatFrame.vue';
import DvTableModal from './DvTableModal.vue';

export default {
    name: 'CombatPanel',
    components: {MedStatFrame, SimpleStatFrame, DvTableModal},
    props: [
        'show', 'selectedWeapon', 'availableFireModes', 'fireMode',
        'dvLoading', 'dvError', 'dvInfo', 'reloadRequired', 'targetDistance'
    ],
    directives: {icon: vIcon},
    emits: ['close', 'roll-hit', 'roll-damage', 'reload', 'move', 'update:fireMode'],
    data() {
        return {showDvTable: false};
    }
}
</script>

<style @cspNonce scoped>
.slide-up-enter-active,
.slide-up-leave-active {
    transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.slide-up-enter-from,
.slide-up-leave-to {
    transform: translateY(100%);
}
</style>
