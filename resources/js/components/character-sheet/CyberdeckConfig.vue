<template>
    <div v-if="activeCyberdeck"
         class="fixed inset-0 z-[130] flex items-center justify-center p-6 backdrop-blur-md bg-cpr-black/80 p-[env(safe-area-inset-top)_env(safe-area-inset-right)_env(safe-area-inset-bottom)_env(safe-area-inset-left)]">
        <div
            class="cpr-panel w-full max-w-2xl bg-cpr-black-deep border-2 border-cpr-teal shadow-[0_0_40px_rgba(20,184,166,0.2)] flex flex-col max-h-[85vh]">
            <div class="bg-cpr-teal-dark p-3 flex justify-between items-center shrink-0">
                <div class="flex items-center gap-2">
                    <i v-icon data-lucide="cpu" class="w-5 h-5 text-black"></i>
                    <span class="text-sm font-black text-black tracking-tighter uppercase italic">Cyberdeck Configuration</span>
                </div>
                <button @click="$emit('close')" class="text-black hover:text-cpr-yellow transition-colors">
                    <i v-icon data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="flex-1 overflow-hidden flex flex-col md:flex-row">
                <div class="flex-1 p-4 border-r border-cpr-teal-dark/30 overflow-y-auto">
                    <div class="flex items-center gap-4 mb-6">
                        <div
                            class="w-12 h-12 bg-cpr-teal/10 border border-cpr-teal/30 flex items-center justify-center">
                            <img v-cpr-img:cpr-teal="getImageUrl(activeCyberdeck.img)" class="w-8 h-8">
                        </div>
                        <div>
                            <h3 class="text-md font-black text-cpr-yellow uppercase">{{ activeCyberdeck.name }}</h3>
                            <div class="text-[10px] font-bold uppercase text-cpr-teal">
                                Memory Slots: <span
                                :class="activeCyberdeck.system.installedItems.usedSlots >= activeCyberdeck.system.installedItems.slots ? 'text-cpr-red' : 'text-cpr-yellow'"
                                class="font-numbers">
                                    {{
                                    activeCyberdeck.system.installedItems.usedSlots
                                }} / {{ activeCyberdeck.system.installedItems.slots }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <h4 class="text-[10px] font-black text-cpr-teal/60 uppercase tracking-[0.2em] mb-3">Installed
                        Memory</h4>
                    <div class="space-y-2">
                        <div v-for="program in activeCyberdeck.installedPrograms" :key="program._id"
                             class="bg-cpr-teal-dark/10 border border-cpr-teal/30 p-2 flex items-center justify-between group">
                            <div class="flex items-center gap-3">
                                <img v-cpr-img:cpr-teal="getImageUrl(program.img)"
                                     class="w-8 h-8 opacity-80 border border-cpr-teal/20 bg-cpr-black">
                                <div>
                                    <div class="text-xs font-bold text-cpr-text-bright uppercase">{{
                                            program.name
                                        }}
                                    </div>
                                    <div class="text-[9px] text-cpr-teal font-mono uppercase">
                                        {{ program.system.class || 'Program' }}
                                    </div>
                                </div>
                            </div>
                            <button @click="$emit('uninstall', program)"
                                    class="text-[9px] font-bold text-cpr-red hover:text-cpr-yellow hover:bg-cpr-red border border-cpr-red-dark/50 uppercase px-2 py-1 transition-all">
                                <i v-icon data-lucide="minus" class="w-3 h-3"></i>
                            </button>
                        </div>
                        <div
                            v-for="n in Math.max(0, activeCyberdeck.system.installedItems.slots - activeCyberdeck.system.installedItems.usedSlots)"
                            :key="n"
                            class="border border-dashed border-cpr-teal-dark/30 p-3 flex items-center justify-center opacity-50">
                            <span class="text-[9px] font-mono text-cpr-teal-dark uppercase">Empty Slot</span>
                        </div>
                    </div>
                </div>

                <div class="flex-1 p-4 bg-cpr-black/50 overflow-y-auto">
                    <h4 class="text-[10px] font-black text-cpr-text-dim uppercase tracking-[0.2em] mb-3">Available
                        Storage</h4>
                    <div class="space-y-2">
                        <div v-for="program in availablePrograms" :key="program._id"
                             class="bg-[var(--cpr-black-soft)] border border-cpr-border p-2 flex items-center justify-between group hover:border-cpr-border-muted transition-colors">
                            <div class="flex items-center gap-3">
                                <img v-cpr-img:cpr-teal="getImageUrl(program.img)"
                                     class="w-8 h-8 opacity-50 grayscale group-hover:grayscale-0 transition-all">
                                <div>
                                    <div
                                        class="text-xs font-bold text-cpr-text-muted group-hover:text-cpr-yellow uppercase">
                                        {{ program.name }}
                                    </div>
                                    <div class="text-[9px] text-cpr-text-dim font-mono uppercase">
                                        {{ program.system.class || 'Program' }}
                                    </div>
                                </div>
                            </div>
                            <button @click="$emit('install', program)"
                                    :disabled="activeCyberdeck.system.installedItems.usedSlots >= activeCyberdeck.system.installedItems.slots"
                                    class="text-[9px] font-bold uppercase px-2 py-1 transition-all flex items-center gap-1"
                                    :class="activeCyberdeck.system.installedItems.usedSlots >= activeCyberdeck.system.installedItems.slots ? 'text-cpr-text-dim border border-cpr-border cursor-not-allowed' : 'text-cpr-teal hover:text-black hover:bg-cpr-teal border border-cpr-teal-dark/50'">
                                <i v-icon data-lucide="plus" class="w-3 h-3"></i> Install
                            </button>
                        </div>
                        <div v-if="availablePrograms.length === 0"
                             class="text-xs text-cpr-text-dim italic text-center py-4">
                            No uninstalled programs found in Gear.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import {vIcon} from '../../directives/icon';
import {useCharacterData} from '../../composables/useCharacterData';

export default {
    name: 'CyberdeckConfig',
    props: ['activeCyberdeck', 'availablePrograms'],
    directives: {icon: vIcon},
    emits: ['close', 'install', 'uninstall'],
    setup() {
        const {getImageUrl} = useCharacterData();
        return {getImageUrl};
    }
}
</script>
