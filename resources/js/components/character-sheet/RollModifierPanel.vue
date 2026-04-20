<template>
    <transition name="slide-right">
        <aside v-if="show" class="fixed top-0 right-0 h-full w-[18rem] max-w-[85vw] z-[60] bg-cpr-black/95 border-l border-cpr-red-dark shadow-[0_0_30px_rgba(220,38,38,0.35)] flex flex-col">
            <div class="flex items-center justify-between p-2 pt-[calc(0.5rem+env(safe-area-inset-top))] border-b border-cpr-red-dark bg-cpr-black/70 sticky top-0 shrink-0">
                <div class="flex items-center gap-2">
                    <i v-icon data-lucide="dice-6" class="w-3 h-3 text-cpr-red"></i>
                    <span class="text-[10px] font-black uppercase tracking-widest text-cpr-red">Dice Roller & Mods</span>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="$emit('refresh')" class="p-1 text-cpr-text-main hover:text-cpr-yellow">
                        <i v-icon data-lucide="refresh-cw" class="w-4 h-4"></i>
                    </button>
                    <button @click="$emit('close')" class="p-1 text-cpr-text-main hover:text-cpr-yellow">
                        <i v-icon data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
            <div class="p-2 space-y-2 overflow-y-auto flex-1 pb-[calc(1.5rem+env(safe-area-inset-bottom))] pr-[calc(0.5rem+env(safe-area-inset-right))] relative">
                <!-- Roll Result Popup -->
                <transition name="fade">
                    <div v-if="showResultPopup" class="absolute inset-0 z-[70] flex items-center justify-center p-4 bg-cpr-black/80 backdrop-blur-sm">
                        <div class="bg-cpr-black border-2 border-cpr-red shadow-[0_0_50px_rgba(220,38,38,0.5)] p-4 text-center relative overflow-hidden group">
                            <!-- Background Glitch Effect -->
                            <div class="absolute inset-0 bg-cpr-red/5 animate-pulse"></div>
                            <div class="absolute -inset-full bg-gradient-to-r from-transparent via-cpr-red/10 to-transparent rotate-45 translate-x-[-100%] animate-[shimmer_2s_infinite]"></div>

                            <div class="relative z-10">
                                <div class="text-[8px] text-cpr-red uppercase font-black tracking-[0.2em] mb-1 animate-bounce">Uplink Confirmed</div>
                                <div class="text-4xl font-black text-cpr-yellow font-numbers drop-shadow-[0_0_15px_rgba(255,255,255,0.5)] mb-2">{{ lastResult?.total }}</div>
                                <div class="text-[10px] text-cpr-lime font-numbers uppercase tracking-widest">{{ lastResult?.formula }}</div>

                                <button @click="showResultPopup = false" class="mt-4 px-4 py-1 bg-cpr-red text-black text-[10px] font-black uppercase hover:bg-white transition-colors">Acknowledge</button>
                            </div>
                        </div>
                    </div>
                </transition>

                <!-- Die Roller Section (Always Visible) -->
                <div class="bg-cpr-black-deep border border-cpr-red/30 p-2 space-y-2 mb-2">
                    <div class="text-[9px] text-cpr-red uppercase font-bold tracking-wider mb-1 flex items-center gap-2">
                        <i v-icon data-lucide="dice-5" class="w-2 h-2"></i> Quick Dice
                    </div>

                    <div class="grid grid-cols-4 gap-1">
                        <button v-for="die in ['d4', 'd6', 'd8', 'd10', 'd12', 'd20', 'd100']"
                                :key="die"
                                @click="addDie(die)"
                                class="flex flex-col items-center justify-center p-1.5 bg-cpr-black border border-cpr-border hover:border-cpr-red hover:bg-cpr-red/10 transition-all group">
                            <span class="text-[10px] font-bold text-cpr-text-main group-hover:text-cpr-yellow uppercase">{{ die }}</span>
                        </button>
                        <button @click="clearFormula"
                                class="flex items-center justify-center p-1.5 bg-cpr-black border border-cpr-border hover:border-cpr-red hover:bg-cpr-red/10 transition-all group">
                            <i v-icon data-lucide="trash-2" class="w-3 h-3 text-cpr-text-dim group-hover:text-cpr-red"></i>
                        </button>
                    </div>

                    <div v-if="formula" class="mt-2 space-y-1">
                        <div class="bg-cpr-black p-1.5 border border-cpr-border-dark flex items-center justify-between">
                            <span class="text-[11px] font-numbers text-cpr-lime">{{ formula }}</span>
                            <button @click="rollFormula"
                                    :disabled="rolling"
                                    class="bg-cpr-red text-black text-[9px] font-black uppercase px-2 py-0.5 hover:bg-cpr-red-light disabled:opacity-50 transition-colors">
                                {{ rolling ? '...' : 'Roll' }}
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="loading && !data" class="text-center py-8 text-cpr-text-muted text-xs font-mono animate-pulse">Initializing Modifiers...</div>

                <div v-else-if="error" class="border border-cpr-red-dark bg-cpr-red-dark/20 text-cpr-red text-xs p-3">
                    {{ error }}
                    <div class="mt-2">
                        <button @click="$emit('go-to-control')" class="text-[10px] uppercase font-bold border border-cpr-red text-cpr-red px-2 py-1 hover:bg-cpr-red hover:text-black transition-colors">Go to Control</button>
                    </div>
                </div>

                <div v-if="data" class="space-y-2">
                    <div class="bg-cpr-black-deep border border-cpr-border p-2">
                        <div class="text-[9px] text-cpr-text-dim uppercase font-bold">Actor</div>
                        <div class="text-cpr-yellow font-bold text-xs">{{ data.actorName || 'Character' }}</div>
                    </div>

                    <div v-if="hasWeapon" class="bg-cpr-black-deep border border-cpr-border p-2">
                        <div class="flex items-center justify-between">
                            <div class="text-[9px] text-cpr-text-dim uppercase font-bold">Base Bonus</div>
                            <div class="text-cpr-lime font-numbers font-bold text-xs">+{{ data.baseBonus }}</div>
                        </div>
                        <div v-if="data.breakdown" class="mt-1 text-[10px] text-cpr-text-main font-numbers space-y-0.5">
                            <div class="flex justify-between"><span>{{ (data.breakdown.statName || 'STAT').toUpperCase() }}</span><span>+{{ data.breakdown.statValue }}</span></div>
                            <div class="flex justify-between"><span>{{ data.breakdown.skillName }}</span><span>+{{ data.breakdown.skillLevel }}</span></div>
                            <div class="flex justify-between"><span>Weapon Acc</span><span>+{{ data.breakdown.weaponAcc }}</span></div>
                        </div>
                    </div>

                    <div class="bg-cpr-black-deep border border-cpr-border p-2">
                        <div class="text-[9px] text-cpr-text-dim uppercase font-bold mb-1">Injuries & Effects</div>
                        <div v-if="!data.injuries || data.injuries.length === 0" class="text-[10px] text-cpr-text-dim italic">No injuries.</div>
                        <div v-else class="space-y-1">
                            <div v-for="(inj, idx) in data.injuries" :key="idx" class="border border-cpr-border p-1.5 bg-cpr-black/40">
                                <div class="flex items-center justify-between">
                                    <div class="text-[10px] font-bold text-cpr-yellow">{{ inj.title }}</div>
                                    <div :class="{'text-cpr-red': inj.penalty < 0, 'text-cpr-text-main': inj.penalty === 0, 'text-cpr-lime': inj.penalty > 0}" class="text-[10px] font-numbers">{{ inj.penalty }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-cpr-black-deep border border-cpr-border p-2">
                        <div class="flex items-center justify-between">
                            <div class="text-[9px] text-cpr-text-dim uppercase font-bold">Total Penalty</div>
                            <div :class="{'text-cpr-red': data.totalPenalty < 0, 'text-cpr-text-main': data.totalPenalty === 0, 'text-cpr-lime': data.totalPenalty > 0}" class="font-numbers font-bold text-xs">{{ data.totalPenalty }}</div>
                        </div>
                    </div>

                    <div class="bg-cpr-black-deep border border-cpr-border p-2">
                        <div class="flex items-center justify-between">
                            <div class="text-[9px] text-cpr-text-dim uppercase font-bold">{{ hasWeapon ? 'Final Modifier' : 'Global Modifier' }}</div>
                            <div :class="{'text-cpr-lime': data.finalModifier >= 0, 'text-cpr-red': data.finalModifier < 0}" class="font-numbers font-bold text-xs">{{ data.finalModifier }}</div>
                        </div>
                    </div>
                </div>

                <div v-if="!data && !loading" class="text-xs text-cpr-text-muted">No data available.</div>
            </div>

            <!-- Privacy Setting -->
            <div class="p-3 border-t border-cpr-red-dark bg-cpr-black/90 sticky bottom-0 shrink-0 pb-[calc(1.5rem+env(safe-area-inset-bottom))]">
                <div class="text-[9px] text-cpr-text-dim uppercase font-bold mb-1.5 flex items-center gap-2">
                    <i v-icon data-lucide="eye" class="w-2.5 h-2.5"></i> Roll Privacy
                </div>
                <select v-model="rollMode" class="w-full bg-cpr-black-deep border border-cpr-border text-cpr-yellow text-[11px] p-2 focus:border-cpr-red outline-none appearance-none hover:border-cpr-red/50 transition-colors">
                    <option value="publicroll">Public Roll</option>
                    <option value="gmroll">Private GM Roll</option>
                    <option value="blindroll">Blind GM Roll</option>
                    <option value="selfroll">Self Roll</option>
                </select>
            </div>
        </aside>
    </transition>
</template>

<script>
import { ref, onMounted, watch } from 'vue';
import { vIcon } from '../../directives/icon';
import axios from 'axios';
import { useCharacterData } from '../../composables/useCharacterData';

export default {
    name: 'RollModifierPanel',
    props: ['show', 'loading', 'error', 'data', 'hasWeapon'],
    directives: { icon: vIcon },
    emits: ['close', 'refresh', 'go-to-control'],
    setup(props) {
        const { rollMode } = useCharacterData();
        const formula = ref('');
        const diceCounts = ref({});
        const rolling = ref(false);
        const lastResult = ref(null);
        const showResultPopup = ref(false);

        const addDie = (die) => {
            if (!diceCounts.value[die]) {
                diceCounts.value[die] = 0;
            }
            diceCounts.value[die]++;
            updateFormula();
        };

        const updateFormula = () => {
            const parts = Object.entries(diceCounts.value)
                .filter(([_, count]) => count > 0)
                .map(([die, count]) => `${count}${die}`);
            formula.value = parts.join(' + ');
        };

        const clearFormula = () => {
            formula.value = '';
            diceCounts.value = {};
        };

        const rollFormula = async () => {
            if (!formula.value || rolling.value) return;

            rolling.value = true;
            try {
                const response = await axios.post('/make-roll', {
                    formula: formula.value,
                    flavor: 'Manual Roll',
                    createChatMessage: true,
                    rollMode: rollMode.value
                });

                if (response.data.success) {
                    // The actual roll data is nested in data.data.data.roll based on user feedback
                    const rollData = response.data.data?.data?.roll || response.data.data?.roll;
                    if (rollData) {
                        lastResult.value = rollData;
                        showResultPopup.value = true;
                        // Auto-hide after 5 seconds if not acknowledged
                        setTimeout(() => {
                            showResultPopup.value = false;
                        }, 5000);
                    }
                }

                // Clear formula after successful roll
                clearFormula();
            } catch (e) {
                console.error('Failed to roll dice', e);
            } finally {
                rolling.value = false;
            }
        };

        return {
            formula,
            rolling,
            lastResult,
            showResultPopup,
            addDie,
            clearFormula,
            rollFormula,
            rollMode
        };
    }
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

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

@keyframes shimmer {
    0% { transform: translateX(-100%) rotate(45deg); }
    100% { transform: translateX(200%) rotate(45deg); }
}

.scrollbar-thin::-webkit-scrollbar {
    width: 4px;
}
.scrollbar-thumb-cpr-red\/30::-webkit-scrollbar-thumb {
    background: rgba(220, 38, 38, 0.3);
    border-radius: 2px;
}
.scrollbar-track-transparent::-webkit-scrollbar-track {
    background: transparent;
}
</style>
