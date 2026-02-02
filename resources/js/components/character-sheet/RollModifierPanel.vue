<template>
    <transition name="slide-right">
        <aside v-if="show" class="fixed top-0 right-0 h-full w-[20rem] max-w-[85vw] z-[60] bg-cpr-black/95 border-l border-cpr-red-dark shadow-[0_0_30px_rgba(220,38,38,0.35)] flex flex-col">
            <div class="flex items-center justify-between p-3 pt-[calc(0.75rem+env(safe-area-inset-top))] border-b border-cpr-red-dark bg-cpr-black/70 sticky top-0 shrink-0">
                <div class="flex items-center gap-2">
                    <i v-icon data-lucide="dice-6" class="w-4 h-4 text-cpr-red"></i>
                    <span class="text-xs font-black uppercase tracking-widest text-cpr-red">Roll Modifiers</span>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="$emit('refresh')" class="p-1 text-cpr-text-main hover:text-white">
                        <i v-icon data-lucide="refresh-cw" class="w-4 h-4"></i>
                    </button>
                    <button @click="$emit('close')" class="p-1 text-cpr-text-main hover:text-white">
                        <i v-icon data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
            <div class="p-3 space-y-3 overflow-y-auto flex-1 pb-[calc(1.5rem+env(safe-area-inset-bottom))] pr-[calc(0.75rem+env(safe-area-inset-right))]">
                <div v-if="loading" class="text-center py-8 text-cpr-text-muted text-xs">Loading modifiers...</div>

                <div v-else-if="error" class="border border-cpr-red-dark bg-cpr-red-dark/20 text-cpr-red text-xs p-3">
                    {{ error }}
                    <div v-if="!hasWeapon" class="mt-2">
                        <button @click="$emit('go-to-control')" class="text-[10px] uppercase font-bold border border-cpr-red text-cpr-red px-2 py-1 hover:bg-cpr-red hover:text-black transition-colors">Go to Control</button>
                    </div>
                </div>

                <div v-else-if="data" class="space-y-4">
                    <div class="bg-cpr-black-deep border border-cpr-border p-3">
                        <div class="text-[10px] text-cpr-text-dim uppercase font-bold">Actor</div>
                        <div class="text-white font-bold">{{ data.actorName }}</div>
                    </div>

                    <div class="bg-cpr-black-deep border border-cpr-border p-3">
                        <div class="flex items-center justify-between">
                            <div class="text-[10px] text-cpr-text-dim uppercase font-bold">Base Bonus</div>
                            <div class="text-cpr-lime font-mono font-bold">+{{ data.baseBonus }}</div>
                        </div>
                        <div v-if="data.breakdown" class="mt-2 text-[11px] text-cpr-text-main font-mono space-y-1">
                            <div class="flex justify-between"><span>{{ (data.breakdown.statName || 'STAT').toUpperCase() }}</span><span>+{{ data.breakdown.statValue }}</span></div>
                            <div class="flex justify-between"><span>{{ data.breakdown.skillName }}</span><span>+{{ data.breakdown.skillLevel }}</span></div>
                            <div class="flex justify-between"><span>Weapon Acc</span><span>+{{ data.breakdown.weaponAcc }}</span></div>
                        </div>
                    </div>

                    <div class="bg-cpr-black-deep border border-cpr-border p-3">
                        <div class="flex items-center justify-between">
                            <div class="text-[10px] text-cpr-text-dim uppercase font-bold">Total Penalty</div>
                            <div :class="{'text-cpr-red': data.totalPenalty < 0, 'text-cpr-text-main': data.totalPenalty === 0, 'text-cpr-lime': data.totalPenalty > 0}" class="font-mono font-bold">{{ data.totalPenalty }}</div>
                        </div>
                    </div>

                    <div class="bg-cpr-black-deep border border-cpr-border p-3">
                        <div class="flex items-center justify-between">
                            <div class="text-[10px] text-cpr-text-dim uppercase font-bold">Final Roll Modifier</div>
                            <div :class="{'text-cpr-lime': data.finalModifier >= 0, 'text-cpr-red': data.finalModifier < 0}" class="font-mono font-bold">{{ data.finalModifier }}</div>
                        </div>
                    </div>

                    <div class="bg-cpr-black-deep border border-cpr-border p-3">
                        <div class="text-[10px] text-cpr-text-dim uppercase font-bold mb-2">Injuries & Effects</div>
                        <div v-if="!data.injuries || data.injuries.length === 0" class="text-xs text-cpr-text-dim italic">No injuries affecting rolls.</div>
                        <div v-else class="space-y-2">
                            <div v-for="(inj, idx) in data.injuries" :key="idx" class="border border-cpr-border p-2 bg-cpr-black/40">
                                <div class="flex items-center justify-between">
                                    <div class="text-xs font-bold text-white">{{ inj.title }}</div>
                                    <div :class="{'text-cpr-red': inj.penalty < 0, 'text-cpr-text-main': inj.penalty === 0, 'text-cpr-lime': inj.penalty > 0}" class="text-[11px] font-mono">{{ inj.penalty }}</div>
                                </div>
                                <div v-if="inj.description" class="text-[11px] text-cpr-text-muted mt-1">{{ inj.description }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="text-xs text-cpr-text-muted">No data available.</div>
            </div>
        </aside>
    </transition>
</template>

<script>
import { vIcon } from '../../directives/icon';

export default {
    name: 'RollModifierPanel',
    props: ['show', 'loading', 'error', 'data', 'hasWeapon'],
    directives: { icon: vIcon },
    emits: ['close', 'refresh', 'go-to-control']
}
</script>

<style scoped>
.slide-right-enter-active,
.slide-right-leave-active {
    transition: transform 0.2s ease, opacity 0.2s ease;
}
.slide-right-enter-from,
.slide-right-leave-to {
    transform: translateX(100%);
    opacity: 0.5;
}
</style>
