<template>
    <div class="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-300">
        <div v-for="pool in netPoolData" :key="pool.pageId" class="space-y-4">
            <div class="flex items-center gap-2 border-b border-cpr-yellow/30 pb-2">
                <i v-icon data-lucide="network" class="w-4 h-4 text-cpr-yellow"></i>
                <h3 class="text-cpr-yellow font-black uppercase tracking-widest text-xs">
                    {{ pool.pageName }}
                </h3>
            </div>

            <div class="grid grid-cols-3 sm:grid-cols-4 gap-4">
                <div v-for="entry in pool.entries" :key="entry.id"
                     @click="openEntry(entry)"
                     class="flex flex-col items-center gap-2 p-2 group cursor-pointer hover:bg-cpr-yellow/5 transition-colors border border-transparent hover:border-cpr-yellow/20 rounded">
                    <div class="w-12 h-12 flex items-center justify-center bg-cpr-black-elevated border border-cpr-border group-hover:border-cpr-yellow transition-colors shadow-sm relative overflow-hidden">
                        <!-- Desktop folder-ish look for 90s feel -->
                        <div class="absolute inset-0 bg-gradient-to-br from-white/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        <img v-if="entry.icon" :src="getImageUrl(entry.icon)" class="w-8 h-8 object-contain relative z-10" />
                        <i v-else v-icon :data-lucide="entry.type === 'pdf' ? 'file-digit' : 'file-text'" class="w-6 h-6 text-cpr-text-muted group-hover:text-cpr-yellow relative z-10"></i>
                    </div>
                    <span class="text-[10px] font-bold text-center leading-tight text-cpr-text-main group-hover:text-cpr-yellow transition-colors break-words w-full uppercase tracking-tighter">
                        {{ entry.title }}
                    </span>
                </div>
            </div>
        </div>

        <div v-if="!netPoolData || netPoolData.length === 0" class="flex flex-col items-center justify-center py-20 opacity-20">
            <i v-icon data-lucide="unplug" class="w-16 h-16 mb-4"></i>
            <span class="font-mono text-xs uppercase tracking-[0.3em]">No Data Pools found on this node.</span>
        </div>

        <!-- Entry Viewer Modal -->
        <div v-if="activeEntry" class="fixed inset-0 z-[150] flex items-center justify-center p-4 bg-cpr-black/90 backdrop-blur-md" @click="activeEntry = null">
            <div class="w-full max-w-2xl bg-cpr-black-deep border-2 border-cpr-yellow shadow-[0_0_50px_rgba(234,179,8,0.2)] flex flex-col max-h-[90vh] animate-in zoom-in-95 duration-200" @click.stop>
                <div class="flex items-center justify-between px-4 py-3 border-b border-cpr-yellow/30 bg-cpr-yellow/10">
                    <div class="flex items-center gap-3">
                        <img v-if="activeEntry.icon" :src="getImageUrl(activeEntry.icon)" class="w-6 h-6 object-contain" />
                        <h2 class="text-cpr-yellow font-black uppercase tracking-tighter text-lg">{{ activeEntry.title }}</h2>
                    </div>
                    <button @click="activeEntry = null" class="text-cpr-text-muted hover:text-cpr-yellow transition-colors">
                        <i v-icon data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-6 text-cpr-text-main font-mono text-sm leading-relaxed custom-scrollbar bg-[radial-gradient(circle_at_50%_50%,rgba(234,179,8,0.03)_0%,transparent_100%)]">
                    <div v-if="activeEntry.type === 'html'" v-html="activeEntry.htmlContent" class="foundry-html prose prose-invert max-w-none prose-yellow prose-sm
                        prose-headings:uppercase prose-headings:tracking-widest prose-headings:text-cpr-yellow
                        prose-a:text-cpr-yellow prose-a:no-underline hover:prose-a:underline
                        prose-strong:text-cpr-yellow"></div>
                    <div v-else-if="activeEntry.type === 'pdf'" class="h-[65vh] flex flex-col">
                        <iframe :src="getPdfUrl(activeEntry.pdfPath)" class="w-full flex-1 border border-cpr-yellow/20 bg-black/40"></iframe>
                    </div>
                    <div v-else class="text-center py-10 opacity-50 italic">
                        No content available for this entry type.
                    </div>
                </div>

                <div class="px-4 py-3 border-t border-cpr-yellow/30 bg-cpr-black-elevated flex justify-between items-center">
                    <span class="text-[10px] font-mono text-cpr-text-dim uppercase tracking-widest">Protocol: {{ activeEntry.type === 'pdf' ? 'DOCUMENT_VIEWER' : 'HTML_RENDERER' }}</span>
                    <button @click="activeEntry = null" class="px-6 py-2 bg-cpr-yellow text-black font-black uppercase text-xs hover:bg-cpr-yellow-dark transition-colors shadow-[0_0_15px_rgba(234,179,8,0.2)]">
                        Close Terminal
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref } from 'vue';
import { vIcon } from '../../directives/icon';

export default {
    name: 'NetTab',
    directives: { icon: vIcon },
    props: {
        netPoolData: {
            type: Array,
            required: true
        },
        getImageUrl: {
            type: Function,
            required: true
        }
    },
    setup(props) {
        const activeEntry = ref(null);

        const openEntry = (entry) => {
            activeEntry.value = entry;
        };

        const getPdfUrl = (path) => {
            if (!path) return '';
            return props.getImageUrl(path);
        };

        return {
            activeEntry,
            openEntry,
            getPdfUrl
        };
    }
}
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.4);
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #eab308;
    border-radius: 3px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #facc15;
}
</style>

<style>
@import "../../../css/components/foundry.css";
</style>
