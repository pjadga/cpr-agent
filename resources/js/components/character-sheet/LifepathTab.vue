<template>
    <div v-if="activeTab === 'lifepath'" key="lifepath"
         class="space-y-5 absolute w-full top-0 left-0 p-3 pb-[calc(5rem+env(safe-area-inset-bottom))]">

        <!-- Notes Section -->
        <div class="cpr-panel bg-[var(--cpr-black-elevated)] p-4 border border-cpr-border relative">
            <h3 class="flex items-center justify-between mb-3 mt-2 bg-cpr-black-deep py-1 px-2 border-l-2 border-cpr-red transition-colors group">
                <span class="text-cpr-yellow font-bold text-sm uppercase">Notes</span>
            </h3>
            <textarea
                v-model="localNotes"
                @blur="updateNotes"
                class="w-full bg-cpr-black border border-cpr-border text-cpr-text-main p-3 font-mono text-xs focus:border-cpr-yellow outline-none transition-colors min-h-[150px] resize-none"
                placeholder="Enter character notes here..."></textarea>
            <div class="cpr-corner-square text-cpr-border"></div>
        </div>

        <!-- Lifepath Section -->
        <div class="space-y-4">
            <h3 class="flex items-center justify-between mb-4 mt-2 bg-cpr-black-deep py-1 px-2 border-l-2 border-cpr-red transition-colors group">
                <span class="text-cpr-yellow font-bold text-sm uppercase">Lifepath</span>
            </h3>

            <div v-for="(value, key) in lifepathData" :key="key" class="border border-cpr-border/30 p-3 relative group">
                <div class="text-[10px] font-bold text-cpr-text-dim uppercase tracking-widest mb-1">{{
                        formatLabel(key)
                    }}
                </div>
                <div class="text-sm text-cpr-text-main leading-relaxed">
                    {{ value || '---' }}
                </div>
                <div
                    class="absolute top-0 right-0 w-1 h-full bg-cpr-red/10 group-hover:bg-cpr-red/30 transition-colors"></div>
            </div>
        </div>
    </div>
</template>

<script>
import {computed, ref, watch} from 'vue';
import {vIcon} from '../../directives/icon';

export default {
    name: 'LifepathTab',
    props: ['activeTab', 'charData'],
    directives: {icon: vIcon},
    emits: ['update-notes'],
    setup(props, {emit}) {
        const localNotes = ref('');

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

        const updateNotes = () => {
            emit('update-notes', localNotes.value);
        };

        return {
            localNotes,
            lifepathData,
            formatLabel,
            updateNotes
        };
    }
}
</script>
