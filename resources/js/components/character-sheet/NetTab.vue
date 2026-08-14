<template>
    <div class="space-y-6 w-full p-3 animate-in fade-in slide-in-from-bottom-4 duration-300">
        <div v-for="pool in netPoolData" :key="pool.pageId" class="space-y-4">
            <h3 class="flex items-center justify-between mb-4 mt-2 bg-cpr-black-deep py-1 px-2 border-l-2 border-cpr-red transition-colors group">
                <span class="text-cpr-yellow font-bold text-sm uppercase">
                    {{ pool.pageName }}
                </span>
            </h3>

            <div class="grid grid-cols-3 sm:grid-cols-4 gap-4">
                <div v-for="entry in pool.entries" :key="entry.id"
                     @click="openEntry(entry, $event)"
                     class="flex flex-col items-center gap-2 p-2 group cursor-pointer transition-colors">
                    <!-- Simple-stat chip housing the icon -->
                    <div
                        class="relative w-14 h-14 flex items-center justify-center transition-colors group-hover:[--frame-colour-change:var(--cpr-yellow)]">
                        <simple-stat-frame/>
                        <img v-if="entry.icon" :src="getImageUrl(entry.icon)"
                             class="relative z-10 w-7 h-7 object-contain"/>
                        <i v-else v-icon :data-lucide="entry.type === 'pdf' ? 'file-digit' : 'file-text'"
                           class="relative z-10 w-6 h-6 text-cpr-text-muted group-hover:text-cpr-yellow transition-colors"></i>
                    </div>
                    <span
                        class="text-[10px] font-bold text-center leading-tight text-cpr-text-main group-hover:text-cpr-yellow transition-colors break-words w-full uppercase tracking-tighter">
                        {{ entry.title }}
                    </span>
                </div>
            </div>
        </div>

        <div v-if="!netPoolData || netPoolData.length === 0"
             class="flex flex-col items-center justify-center py-20 opacity-20">
            <i v-icon data-lucide="unplug" class="w-16 h-16 mb-4"></i>
            <span class="font-mono text-xs uppercase tracking-[0.3em]">No Data Pools found on this node.</span>
        </div>

        <!-- Entry Viewer Modal -->
        <div v-if="activeEntry"
             ref="backdropRef"
             class="fixed inset-0 z-[150] flex items-center justify-center p-2 bg-cpr-black/90 backdrop-blur-md"
             @click="closeEntry">
            <div ref="panelRef"
                 class="relative w-[96vw] max-w-none h-[92vh] flex flex-col will-change-transform"
                 :style="{ '--frame-accent': 'var(--cpr-yellow)' }"
                 @click.stop>
                <dropdown-info-frame class="absolute inset-0 pointer-events-none select-none"/>

                <div class="relative z-10 flex flex-col h-full m-3 overflow-hidden bg-cpr-black-deep/90">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-cpr-yellow/30 bg-cpr-yellow/10">
                        <div class="flex items-center gap-3 min-w-0">
                            <img v-if="activeEntry.icon" :src="getImageUrl(activeEntry.icon)"
                                 class="w-6 h-6 object-contain shrink-0"/>
                            <h2 class="text-cpr-yellow font-black uppercase tracking-tighter text-lg truncate">{{
                                    activeEntry.title
                                }}</h2>
                        </div>
                        <button @click="closeEntry"
                                class="text-cpr-text-muted hover:text-cpr-yellow transition-colors shrink-0">
                            <i v-icon data-lucide="x" class="w-6 h-6"></i>
                        </button>
                    </div>

                    <div
                        class="flex-1 overflow-y-auto p-6 text-cpr-text-main font-mono text-sm leading-relaxed custom-scrollbar bg-[radial-gradient(circle_at_50%_50%,rgba(234,179,8,0.03)_0%,transparent_100%)]">
                        <div v-if="activeEntry.type === 'html'" v-html="activeEntry.htmlContent" class="foundry-html editor-content prose prose-invert max-w-none prose-yellow prose-sm
                            prose-headings:uppercase prose-headings:tracking-widest prose-headings:text-cpr-yellow
                            prose-a:text-cpr-yellow prose-a:no-underline hover:prose-a:underline
                            prose-strong:text-cpr-yellow"></div>
                        <div v-else-if="activeEntry.type === 'pdf'" class="h-full flex flex-col">
                            <iframe :src="getPdfUrl(activeEntry.pdfPath)"
                                    class="w-full flex-1 border border-cpr-yellow/20 bg-black/40"></iframe>
                        </div>
                        <div v-else class="text-center py-10 opacity-50 italic">
                            No content available for this entry type.
                        </div>
                    </div>

                    <div
                        class="px-4 py-3 border-t border-cpr-yellow/30 bg-cpr-black-elevated flex justify-between items-center">
                        <span class="text-[10px] font-mono text-cpr-text-dim uppercase tracking-widest">Page View: {{
                                activeEntry.type === 'pdf' ? 'DOCUMENT_VIEWER' : 'HTML_RENDERER'
                            }}</span>
                        <button @click="closeEntry"
                                class="px-6 py-2 bg-cpr-yellow text-black font-black uppercase text-xs hover:bg-cpr-yellow-dark transition-colors shadow-[0_0_15px_rgba(234,179,8,0.2)]">
                            Close Terminal
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script @cspNonce>
import {nextTick, ref} from 'vue';
import gsap from 'gsap';
import {vIcon} from '../../directives/icon';
import SimpleStatFrame from './frames/SimpleStatFrame.vue';
import DropdownInfoFrame from './frames/DropdownInfoFrame.vue';

export default {
    name: 'NetTab',
    components: {SimpleStatFrame, DropdownInfoFrame},
    directives: {icon: vIcon},
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
        const panelRef = ref(null);
        const backdropRef = ref(null);
        let originRect = null;

        // Grow the viewer out of the tapped tile, native-app style.
        const openEntry = async (entry, event) => {
            originRect = event?.currentTarget?.getBoundingClientRect() || null;
            activeEntry.value = entry;
            await nextTick();
            const panel = panelRef.value;
            if (!panel) return;
            gsap.fromTo(backdropRef.value, {opacity: 0}, {opacity: 1, duration: 0.25, ease: 'power2.out'});
            if (!originRect) {
                gsap.fromTo(panel, {scale: 0.9, opacity: 0}, {scale: 1, opacity: 1, duration: 0.35, ease: 'power3.out'});
                return;
            }
            const r = panel.getBoundingClientRect();
            const scaleX = originRect.width / r.width;
            const scaleY = originRect.height / r.height;
            const dx = (originRect.left + originRect.width / 2) - (r.left + r.width / 2);
            const dy = (originRect.top + originRect.height / 2) - (r.top + r.height / 2);
            gsap.fromTo(panel,
                {x: dx, y: dy, scaleX, scaleY, opacity: 0.4},
                {x: 0, y: 0, scaleX: 1, scaleY: 1, opacity: 1, duration: 0.38, ease: 'power3.out'});
        };

        // Shrink back into the tile it came from.
        const closeEntry = () => {
            const panel = panelRef.value;
            if (!panel || !originRect) {
                activeEntry.value = null;
                return;
            }
            const r = panel.getBoundingClientRect();
            const scaleX = originRect.width / r.width;
            const scaleY = originRect.height / r.height;
            const dx = (originRect.left + originRect.width / 2) - (r.left + r.width / 2);
            const dy = (originRect.top + originRect.height / 2) - (r.top + r.height / 2);
            gsap.to(backdropRef.value, {opacity: 0, duration: 0.25, ease: 'power2.in'});
            gsap.to(panel, {
                x: dx, y: dy, scaleX, scaleY, opacity: 0.2, duration: 0.28, ease: 'power2.in',
                onComplete: () => {
                    activeEntry.value = null;
                }
            });
        };

        const getPdfUrl = (path) => {
            if (!path) return '';
            return props.getImageUrl(path);
        };

        return {
            activeEntry,
            panelRef,
            backdropRef,
            openEntry,
            closeEntry,
            getPdfUrl
        };
    }
}
</script>

<style @cspNonce scoped>
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
