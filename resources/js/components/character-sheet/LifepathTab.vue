<template>
    <div v-if="activeTab === 'lifepath'" key="lifepath"
         class="space-y-5 w-full p-3">

        <!-- Notes Section -->
        <div class="space-y-2">
            <h3 class="flex items-center justify-between mb-2 mt-2 bg-cpr-black-deep py-1 px-2 border-l-2 border-cpr-red transition-colors group">
                <span class="text-cpr-yellow font-bold text-sm uppercase">Notes</span>
            </h3>
            <div class="relative min-h-[170px]" :style="{ '--frame-accent': 'var(--cpr-border)' }">
                <DropdownInfoFrame class="absolute inset-0 pointer-events-none select-none"/>
                <textarea
                    v-model="localNotes"
                    @blur="updateNotes"
                    class="relative z-10 w-full bg-transparent text-cpr-text-main p-4 font-mono text-xs focus:outline-none transition-colors min-h-[150px] resize-none"
                    placeholder="Enter character notes here..."></textarea>
            </div>
        </div>

        <!-- Lifepath Section -->
        <div class="space-y-2">
            <h3 class="flex items-center justify-between mb-2 mt-4 bg-cpr-black-deep py-1 px-2 border-l-2 border-cpr-red transition-colors group">
                <span class="text-cpr-yellow font-bold text-sm uppercase">Lifepath</span>
            </h3>

            <div v-for="(value, key) in lifepathData" :key="key" class="flex flex-col">
                <!-- Field row -->
                <div class="relative flex items-stretch min-h-[3.25rem] group"
                     :style="{ '--frame-accent': 'var(--cpr-border)' }">
                    <BasicRowFrame class="absolute inset-0 pointer-events-none select-none"/>
                    <div class="relative z-10 flex-1 flex justify-between items-center p-2 pl-4 cursor-pointer gap-3"
                         @click="toggle(key)">
                        <span class="text-[10px] font-bold text-cpr-text-dim uppercase tracking-widest group-hover:text-cpr-yellow transition-colors">
                            {{ formatLabel(key) }}
                        </span>
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="text-xs text-cpr-text-main truncate max-w-[9rem] text-right">{{
                                    value || '---'
                                }}</span>
                            <i v-icon data-lucide="chevron-down"
                               class="w-4 h-4 shrink-0 text-cpr-text-dim transition-transform duration-300"
                               :class="{ 'rotate-180': expanded[key] }"></i>
                        </div>
                    </div>
                </div>

                <!-- Field detail dropdown -->
                <transition name="expand">
                    <div v-if="expanded[key]" class="relative mt-1"
                         :style="{ '--frame-accent': 'var(--cpr-border)' }">
                        <DropdownInfoFrame class="absolute inset-0 pointer-events-none select-none"/>
                        <div class="relative z-10 px-4 py-3 text-[11px] text-cpr-text-main leading-relaxed">
                            {{ value || '---' }}
                        </div>
                    </div>
                </transition>
            </div>
        </div>
    </div>
</template>

<script>
import {computed, ref, watch} from 'vue';
import {vIcon} from '../../directives/icon';
import BasicRowFrame from './frames/BasicRowFrame.vue';
import DropdownInfoFrame from './frames/DropdownInfoFrame.vue';

export default {
    name: 'LifepathTab',
    components: {BasicRowFrame, DropdownInfoFrame},
    props: ['activeTab', 'charData'],
    directives: {icon: vIcon},
    emits: ['update-notes'],
    setup(props, {emit}) {
        const localNotes = ref('');
        const expanded = ref({});

        // Strip HTML tags from Foundry notes if present, or just use as is
        const cleanNotes = (html) => {
            if (!html) return '';
            const doc = new DOMParser().parseFromString(html, 'text/html');
            return doc.body.textContent || "";
        };

        watch(() => props.charData?.data?.system?.information?.notes, (newNotes) => {
            localNotes.value = cleanNotes(newNotes);
        }, {immediate: true});

        const lifepathData = computed(() => props.charData?.data?.system?.lifepath || {});

        const formatLabel = (key) => {
            return key
                .replace(/([A-Z])/g, ' $1')
                .replace(/^./, (str) => str.toUpperCase());
        };

        const toggle = (key) => {
            expanded.value[key] = !expanded.value[key];
        };

        const updateNotes = () => {
            emit('update-notes', localNotes.value);
        };

        return {
            localNotes,
            expanded,
            lifepathData,
            formatLabel,
            toggle,
            updateNotes
        };
    }
}
</script>

<style @cspNonce scoped>
.expand-enter-active,
.expand-leave-active {
    transition: all 0.3s ease-in-out;
    max-height: 400px;
    overflow: hidden;
}

.expand-enter-from,
.expand-leave-to {
    max-height: 0;
    opacity: 0;
    padding-top: 0;
    padding-bottom: 0;
}
</style>
