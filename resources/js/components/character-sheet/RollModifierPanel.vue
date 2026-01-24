<template>
    <transition name="slide-right">
        <aside v-if="show" class="fixed top-0 right-0 h-full w-[20rem] max-w-[85vw] z-[60] bg-black/95 border-l border-red-900 shadow-[0_0_30px_rgba(220,38,38,0.35)] flex flex-col">
            <div class="flex items-center justify-between p-3 pt-[calc(0.75rem+env(safe-area-inset-top))] border-b border-red-900 bg-black/70 sticky top-0 shrink-0">
                <div class="flex items-center gap-2">
                    <i v-icon data-lucide="dice-6" class="w-4 h-4 text-red-500"></i>
                    <span class="text-xs font-black uppercase tracking-widest text-red-500">Roll Modifiers</span>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="$emit('refresh')" class="p-1 text-gray-300 hover:text-white">
                        <i v-icon data-lucide="refresh-cw" class="w-4 h-4"></i>
                    </button>
                    <button @click="$emit('close')" class="p-1 text-gray-300 hover:text-white">
                        <i v-icon data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
            <div class="p-3 space-y-3 overflow-y-auto flex-1 pb-[calc(1.5rem+env(safe-area-inset-bottom))] pr-[calc(0.75rem+env(safe-area-inset-right))]">
                <div v-if="loading" class="text-center py-8 text-gray-400 text-xs">Loading modifiers...</div>

                <div v-else-if="error" class="border border-red-700 bg-red-900/20 text-red-400 text-xs p-3">
                    {{ error }}
                    <div v-if="!hasWeapon" class="mt-2">
                        <button @click="$emit('go-to-control')" class="text-[10px] uppercase font-bold border border-red-600 text-red-500 px-2 py-1 hover:bg-red-600 hover:text-black transition-colors">Go to Control</button>
                    </div>
                </div>

                <div v-else-if="data" class="space-y-4">
                    <div class="bg-[#0b0b0b] border border-gray-800 p-3">
                        <div class="text-[10px] text-gray-500 uppercase font-bold">Actor</div>
                        <div class="text-white font-bold">{{ data.actorName }}</div>
                    </div>

                    <div class="bg-[#0b0b0b] border border-gray-800 p-3">
                        <div class="flex items-center justify-between">
                            <div class="text-[10px] text-gray-500 uppercase font-bold">Base Bonus</div>
                            <div class="text-green-400 font-mono font-bold">+{{ data.baseBonus }}</div>
                        </div>
                        <div v-if="data.breakdown" class="mt-2 text-[11px] text-gray-300 font-mono space-y-1">
                            <div class="flex justify-between"><span>{{ (data.breakdown.statName || 'STAT').toUpperCase() }}</span><span>+{{ data.breakdown.statValue }}</span></div>
                            <div class="flex justify-between"><span>{{ data.breakdown.skillName }}</span><span>+{{ data.breakdown.skillLevel }}</span></div>
                            <div class="flex justify-between"><span>Weapon Acc</span><span>+{{ data.breakdown.weaponAcc }}</span></div>
                        </div>
                    </div>

                    <div class="bg-[#0b0b0b] border border-gray-800 p-3">
                        <div class="flex items-center justify-between">
                            <div class="text-[10px] text-gray-500 uppercase font-bold">Total Penalty</div>
                            <div :class="{'text-red-400': data.totalPenalty < 0, 'text-gray-300': data.totalPenalty === 0, 'text-green-400': data.totalPenalty > 0}" class="font-mono font-bold">{{ data.totalPenalty }}</div>
                        </div>
                    </div>

                    <div class="bg-[#0b0b0b] border border-gray-800 p-3">
                        <div class="flex items-center justify-between">
                            <div class="text-[10px] text-gray-500 uppercase font-bold">Final Roll Modifier</div>
                            <div :class="{'text-green-400': data.finalModifier >= 0, 'text-red-400': data.finalModifier < 0}" class="font-mono font-bold">{{ data.finalModifier }}</div>
                        </div>
                    </div>

                    <div class="bg-[#0b0b0b] border border-gray-800 p-3">
                        <div class="text-[10px] text-gray-500 uppercase font-bold mb-2">Injuries & Effects</div>
                        <div v-if="!data.injuries || data.injuries.length === 0" class="text-xs text-gray-500 italic">No injuries affecting rolls.</div>
                        <div v-else class="space-y-2">
                            <div v-for="(inj, idx) in data.injuries" :key="idx" class="border border-gray-800 p-2 bg-black/40">
                                <div class="flex items-center justify-between">
                                    <div class="text-xs font-bold text-white">{{ inj.title }}</div>
                                    <div :class="{'text-red-400': inj.penalty < 0, 'text-gray-300': inj.penalty === 0, 'text-green-400': inj.penalty > 0}" class="text-[11px] font-mono">{{ inj.penalty }}</div>
                                </div>
                                <div v-if="inj.description" class="text-[11px] text-gray-400 mt-1">{{ inj.description }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="text-xs text-gray-400">No data available.</div>
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
