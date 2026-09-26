<template>
    <transition name="fade">
        <div v-if="show"
             class="fixed inset-0 z-[130] flex items-center justify-center p-4 bg-cpr-black/80 backdrop-blur-md"
             @click.self="$emit('close')">
            <div class="w-full max-w-sm bg-cpr-black border-2 border-cpr-red shadow-[0_0_40px_rgba(220,38,38,0.35)] flex flex-col">
                <div class="flex items-center justify-between p-3 border-b border-cpr-red-dark bg-cpr-black-deep/40 shrink-0">
                    <span class="text-[11px] font-black uppercase tracking-[0.2em] text-cpr-red">Uninstall Cyberware</span>
                    <button @click="$emit('close')" class="p-1 text-cpr-text-muted hover:text-cpr-yellow transition-colors">
                        <i v-icon data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>

                <div class="p-4 space-y-3">
                    <div class="text-sm font-bold text-cpr-yellow uppercase tracking-wide">{{ target?.item?.name }}</div>
                    <p class="text-xs text-cpr-text-main leading-relaxed">
                        Remove this from the character. Anything installed inside it comes out with it.
                    </p>
                    <!-- Uninstalling never returns humanity in CPR, and players
                         reliably expect that it does. Say so up front. -->
                    <div class="border border-cpr-red/40 bg-cpr-red-dark/10 p-3 text-[11px] text-cpr-text-muted leading-relaxed">
                        Humanity lost on install is not recovered.
                    </div>
                </div>

                <div class="flex gap-3 p-3 border-t border-cpr-red-dark/50 shrink-0">
                    <button @click="$emit('close')"
                            class="flex-1 py-2 border border-cpr-border text-cpr-text-muted font-bold uppercase text-xs hover:text-cpr-text-main transition-colors">
                        Cancel
                    </button>
                    <button @click="$emit('confirm')"
                            class="flex-1 py-2 bg-cpr-red text-black font-black uppercase text-xs hover:bg-white transition-colors">
                        Uninstall
                    </button>
                </div>
            </div>
        </div>
    </transition>
</template>

<script>
import {vIcon} from '../../directives/icon';

export default {
    name: 'CyberwareUninstallModal',
    props: ['show', 'target'],
    directives: {icon: vIcon},
    emits: ['close', 'confirm'],
}
</script>
