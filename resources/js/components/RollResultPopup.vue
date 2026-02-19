<template>
    <div v-if="visible" class="fixed inset-0 z-[200] flex items-center justify-center p-6 bg-cpr-black/80 backdrop-blur-sm" @click="close">
        <div class="w-full max-w-sm bg-cpr-black-deep border-2" :class="borderColor" @click.stop>
            <div class="flex items-center justify-between px-4 py-2 border-b" :class="headerBorderColor + ' ' + headerBgColor">
                <span class="text-xs font-black uppercase tracking-[0.2em] text-white">{{ title }}</span>
                <button class="text-white/70 hover:text-white transition-colors" @click="close">
                    <i v-icon data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="p-6 text-center space-y-6">
                <div v-if="flavor" class="text-[10px] text-cpr-text-dim uppercase font-bold tracking-[0.3em] leading-tight">
                    {{ flavor }}
                </div>

                <div class="relative inline-block">
                    <div class="text-6xl font-black font-mono tracking-tighter" :class="resultColor">
                        {{ result }}
                    </div>
                    <div v-if="isCriticalSuccess" class="absolute -top-4 -right-8 bg-green-600 text-black text-[8px] font-black px-2 py-0.5 uppercase tracking-tighter rotate-12 shadow-lg">
                        Critical
                    </div>
                    <div v-if="isCriticalFailure" class="absolute -top-4 -right-8 bg-cpr-red text-black text-[8px] font-black px-2 py-0.5 uppercase tracking-tighter rotate-12 shadow-lg">
                        Fail
                    </div>
                </div>

                <!-- Apply Damage Button (for damage rolls) -->
                <div v-if="type==='damage'" class="flex justify-center mb-2">
                    <button @click="applyDamage" class="flex items-center gap-2 px-4 py-2 border-2 border-cpr-red text-cpr-red hover:bg-cpr-red hover:text-black transition-all font-black uppercase text-xs tracking-[0.2em]">
                        <i v-icon data-lucide="droplet" class="w-5 h-5"></i>
                        Apply Damage
                    </button>
                </div>

                <!-- Dice Breakdown -->
                <div v-if="diceResults.length" class="flex justify-center gap-2">
                    <div v-for="(die, idx) in diceResults" :key="idx"
                         class="w-10 h-10 border-2 flex items-center justify-center font-mono font-bold text-lg"
                         :class="[
                             isCriticalSuccess ? 'border-green-600/50 text-green-400' :
                             isCriticalFailure ? 'border-cpr-red/50 text-cpr-red' :
                             'border-cpr-yellow/30 text-cpr-yellow/70'
                         ]">
                        {{ die }}
                    </div>
                </div>

                <!-- Modifiers Breakdown -->
                <div v-if="modifiers.length || luck > 0 || statValue > 0 || skillValue > 0" class="text-[10px] space-y-1 font-mono uppercase tracking-wider text-cpr-text-dim border-t border-cpr-border/20 pt-4">
                    <div v-if="statValue" class="flex justify-between">
                        <span>Base Stat</span>
                        <span class="text-white">+{{ statValue }}</span>
                    </div>
                    <div v-if="skillValue" class="flex justify-between">
                        <span>Skill Level</span>
                        <span class="text-white">+{{ skillValue }}</span>
                    </div>
                    <div v-for="(mod, idx) in modifiers" :key="idx" class="flex justify-between">
                        <span class="truncate pr-4">{{ mod.source || 'Modifier' }}</span>
                        <span :class="mod.value >= 0 ? 'text-green-500' : 'text-cpr-red'">
                            {{ mod.value >= 0 ? '+' : '' }}{{ mod.value }}
                        </span>
                    </div>
                    <div v-if="luck > 0" class="flex justify-between border-t border-cpr-border/10 pt-1 mt-1">
                        <span class="text-cpr-yellow">Luck Spent</span>
                        <span class="text-cpr-yellow">+{{ luck }}</span>
                    </div>
                </div>

                <div v-if="formula" class="font-mono text-xs text-cpr-text-dim bg-cpr-black/40 py-1 px-3 rounded-full inline-block">
                    {{ formula }}
                </div>

                <div v-if="details" class="text-xs text-cpr-text-main font-medium uppercase tracking-widest border-t border-cpr-border/30 pt-4">
                    {{ details }}
                </div>
            </div>

            <div class="px-4 py-3 bg-cpr-black/20 flex justify-center">
                <button
                    @click="close"
                    class="w-full py-2 bg-transparent border-2 font-black uppercase text-xs tracking-[0.2em] transition-all duration-200"
                    :class="buttonClasses">
                    Acknowledge
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { vIcon } from '../directives/icon';

export default {
    name: 'RollResultPopup',
    directives: { icon: vIcon },
    data() {
        return {
            visible: false,
            title: 'Roll Result',
            result: 0,
            formula: '',
            flavor: '',
            details: '',
            type: 'default',
            isCriticalSuccess: false,
            isCriticalFailure: false,
            raw: null,
        };
    },
    computed: {
        diceResults() {
            if (!this.raw?._roll?.terms) return [];
            return this.raw._roll.terms
                .filter(t => t.class === 'Die')
                .flatMap(t => t.results.map(r => r.result));
        },
        modifiers() {
            return this.raw?.mods || [];
        },
        luck() {
            return this.raw?.luck || 0;
        },
        statValue() {
            return this.raw?.statValue || 0;
        },
        skillValue() {
            return this.raw?.skillValue || 0;
        },
        borderColor() {
            if (this.isCriticalSuccess) return 'border-green-600 shadow-[0_0_20px_rgba(22,163,74,0.3)]';
            if (this.isCriticalFailure) return 'border-cpr-red shadow-[0_0_20px_rgba(220,38,38,0.3)]';
            return 'border-cpr-yellow/50 shadow-[0_0_20px_rgba(234,179,8,0.2)]';
        },
        headerBorderColor() {
            if (this.isCriticalSuccess) return 'border-green-600';
            if (this.isCriticalFailure) return 'border-cpr-red';
            return 'border-cpr-yellow/50';
        },
        headerBgColor() {
            if (this.isCriticalSuccess) return 'bg-green-600/20';
            if (this.isCriticalFailure) return 'bg-cpr-red/20';
            return 'bg-cpr-yellow/20';
        },
        resultColor() {
            if (this.isCriticalSuccess) return 'text-green-500';
            if (this.isCriticalFailure) return 'text-cpr-red';
            return 'text-cpr-yellow';
        },
        buttonClasses() {
            if (this.isCriticalSuccess) return 'border-green-600 text-green-500 hover:bg-green-600 hover:text-black';
            if (this.isCriticalFailure) return 'border-cpr-red text-cpr-red hover:bg-cpr-red hover:text-black';
            return 'border-cpr-yellow text-cpr-yellow hover:bg-cpr-yellow hover:text-black';
        }
    },
    methods: {
        show(data) {
            this.title = data.title || 'Roll Result';
            this.result = data.result;
            this.formula = data.formula || '';
            this.flavor = data.flavor || '';
            this.details = data.details || '';
            this.type = data.type || 'default';
            this.isCriticalSuccess = !!data.isCriticalSuccess;
            this.isCriticalFailure = !!data.isCriticalFailure;
            this.raw = data.raw || null;
            this.visible = true;

            if (window.lucide) {
                this.$nextTick(() => {
                    window.lucide.createIcons();
                });
            }
        },
        close() {
            this.visible = false;
        },
        applyDamage() {
            this.$emit('apply-damage', this.result);
            if (window.emitter) {
                window.emitter.emit('apply-damage', this.result);
            }
        }
    },
    mounted() {
        window.emitter.on('show-roll-result', (data) => {
            this.show(data);
        });
    }
}
</script>
