<template>
    <transition name="fade">
        <div v-if="show"
             class="fixed inset-0 z-[130] flex items-center justify-center p-4 bg-cpr-black/80 backdrop-blur-md"
             @click.self="$emit('close')">
            <div class="w-full max-w-sm bg-cpr-black border-2 border-cpr-red shadow-[0_0_40px_rgba(220,38,38,0.35)] flex flex-col max-h-[85vh]">
                <!-- Header -->
                <div class="flex items-center justify-between p-3 border-b border-cpr-red-dark bg-cpr-black-deep/40 shrink-0">
                    <span class="text-[11px] font-black uppercase tracking-[0.2em] text-cpr-red">Install Cyberware</span>
                    <button @click="$emit('close')" class="p-1 text-cpr-text-muted hover:text-cpr-yellow transition-colors">
                        <i v-icon data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>

                <div class="p-4 overflow-y-auto space-y-4">
                    <div class="text-sm font-bold text-cpr-yellow uppercase tracking-wide">{{ options?.itemName || 'Cyberware' }}</div>

                    <!-- Blocked: no compatible foundational -->
                    <div v-if="options && !options.canInstall"
                         class="border border-cpr-red/40 bg-cpr-red-dark/10 p-3 text-xs text-cpr-text-main leading-relaxed">
                        {{ options.reason || 'This cyberware cannot be installed right now.' }}
                    </div>

                    <template v-else-if="options">
                        <!-- Install-to target (non-foundational only) -->
                        <div v-if="!options.isFoundational">
                            <div class="text-[10px] text-cpr-text-dim uppercase font-bold tracking-widest mb-1">Install Into</div>
                            <select v-model="foundationalId"
                                    class="w-full bg-cpr-black-deep border border-cpr-border text-cpr-text-main text-sm p-2 focus:border-cpr-yellow outline-none">
                                <option v-for="t in options.targets" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                        </div>
                        <div v-else class="text-[10px] text-cpr-text-dim uppercase tracking-widest">
                            Foundational cyberware, installs directly into the body.
                        </div>

                        <!-- Humanity loss option -->
                        <div>
                            <div class="text-[10px] text-cpr-text-dim uppercase font-bold tracking-widest mb-2">Humanity Loss</div>
                            <div class="grid grid-cols-3 gap-2">
                                <button v-for="opt in humanityOptions" :key="opt.key"
                                        @click="humanityChoice = opt.key"
                                        class="px-2 py-3 text-center border transition-colors"
                                        :class="humanityChoice === opt.key
                                            ? 'border-cpr-yellow text-cpr-yellow bg-cpr-yellow/10'
                                            : 'border-cpr-border text-cpr-text-muted hover:text-cpr-text-main'">
                                    <div class="text-[9px] uppercase font-bold tracking-widest">{{ opt.label }}</div>
                                    <div class="text-sm font-numbers font-black mt-1">{{ opt.value }}</div>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Footer -->
                <div class="flex gap-3 p-3 border-t border-cpr-red-dark/50 shrink-0">
                    <button @click="$emit('close')"
                            class="flex-1 py-2 border border-cpr-border text-cpr-text-muted font-bold uppercase text-xs hover:text-cpr-text-main transition-colors">
                        Cancel
                    </button>
                    <button v-if="options && options.canInstall"
                            @click="confirm"
                            class="flex-1 py-2 bg-cpr-red text-black font-black uppercase text-xs hover:bg-white transition-colors">
                        Install
                    </button>
                </div>
            </div>
        </div>
    </transition>
</template>

<script>
import {vIcon} from '../../directives/icon';

export default {
    name: 'CyberwareInstallModal',
    props: ['show', 'options'],
    directives: {icon: vIcon},
    emits: ['close', 'confirm'],
    data() {
        return {
            foundationalId: null,
            humanityChoice: 'roll',
        };
    },
    computed: {
        humanityOptions() {
            const hl = this.options?.humanityLoss || {};
            return [
                {key: 'roll', label: 'Roll', value: hl.roll ?? '--'},
                {key: 'static', label: 'Static', value: hl.static ?? '--'},
                {key: 'none', label: 'None', value: '0'},
            ];
        }
    },
    watch: {
        // Reset the defaults each time a fresh set of options opens the modal.
        options: {
            immediate: true,
            handler(opts) {
                this.foundationalId = opts?.targets?.[0]?.id ?? null;
                this.humanityChoice = 'roll';
            }
        }
    },
    methods: {
        confirm() {
            this.$emit('confirm', {
                foundationalId: this.options?.isFoundational ? null : this.foundationalId,
                humanityLossType: this.humanityChoice,
            });
        }
    }
}
</script>
