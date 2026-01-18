<template>
    <div class="group relative bg-[#0a0a0a] border transition-all duration-300"
         :class="isEffectivelyRezzed(prog) ? 'border-blue-400 shadow-[0_0_15px_rgba(59,130,246,0.2)]' : 'border-gray-800 hover:border-blue-500/50'">

        <div class="absolute left-0 top-0 bottom-0 w-1 transition-colors duration-300"
             :class="isEffectivelyRezzed(prog) ? 'bg-blue-500' : 'bg-transparent group-hover:bg-blue-900'"></div>

        <div class="p-3 pl-4 flex items-center justify-between">
            <div class="flex items-center gap-3 cursor-pointer flex-1" @click="$emit('toggle-details')">
                <div class="relative">
                    <img :src="getImageUrl(prog.img)" class="w-9 h-9 object-cover border border-gray-700 bg-black" :class="{'grayscale opacity-60': !isEffectivelyRezzed(prog)}">
                    <div v-if="isEffectivelyRezzed(prog)" class="absolute -bottom-1 -right-1 w-3 h-3 bg-blue-500 rounded-full border-2 border-black animate-pulse"></div>
                </div>
                <div>
                    <div class="text-xs font-bold text-white uppercase tracking-tight group-hover:text-blue-400 transition-colors flex items-center gap-2">
                        {{ prog.name }}
                        <i v-icon data-lucide="chevron-down" class="w-3 h-3 transition-transform duration-300" :class="{'rotate-180': isExpanded}"></i>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 mt-0.5" v-if="isEffectivelyRezzed(prog)">
                        <span class="text-[9px] font-mono text-gray-500 uppercase">{{ prog.system.class || 'Generic' }}</span>
                        <span v-if="prog.system.rez && !['Anti-Personnel', 'Anti-Program'].includes(category)" class="text-[9px] font-mono" :class="prog.system.rez.value < 5 ? 'text-red-500' : 'text-green-500'">
                            REZ: {{ prog.system.rez.value }}/{{ prog.system.rez.max }}
                        </span>
                        <span v-if="prog.system.atk" class="text-[9px] font-mono text-blue-400">
                            ATK: +{{ prog.system.atk }}
                        </span>
                        <span v-if="prog.system.def" class="text-[9px] font-mono text-green-400">
                            DEF: +{{ prog.system.def }}
                        </span>
                        <span v-if="prog.system.per" class="text-[9px] font-mono text-yellow-400">
                            PER: +{{ prog.system.per }}
                        </span>
                        <span v-if="prog.system.spd" class="text-[9px] font-mono text-purple-400">
                            SPD: +{{ prog.system.spd }}
                        </span>
                    </div>
                    <div v-else class="flex items-center gap-2 mt-0.5">
                        <span class="text-[9px] font-mono text-gray-600 uppercase italic">De-rezzed</span>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-1 items-end">
                <button v-if="canBeRezzed(prog)"
                        @click.stop="$emit('toggle-rez')"
                        class="text-[9px] px-2 py-1 uppercase font-black border transition-all w-16 text-center"
                        :class="prog.system.isRezzed ? 'bg-blue-600 border-blue-600 text-black' : 'border-gray-700 text-gray-500 hover:border-blue-500 hover:text-blue-400'">
                    {{ prog.system.isRezzed ? 'DEREZ' : 'REZ' }}
                </button>

                <button @click.stop="$emit('execute')"
                        class="text-[9px] px-2 py-1 uppercase font-bold border border-gray-700 text-gray-300 hover:bg-white hover:text-black w-16 text-center transition-colors">
                    Execute
                </button>
            </div>
        </div>

        <!-- Expanded Details -->
        <div v-if="isExpanded" class="px-4 pb-3 border-t border-gray-800/50 pt-2 bg-black/40">
            <div class="text-[10px] text-gray-400 font-mono mb-3 leading-relaxed" v-html="prog.system.description.value"></div>
            <div class="grid grid-cols-2 gap-2">
                <div v-if="prog.system.damage" class="space-y-1">
                    <div class="text-[8px] text-gray-500 uppercase font-bold">Damage</div>
                    <div class="flex gap-2">
                        <div v-if="prog.system.damage.standard" class="text-[9px] bg-red-900/30 border border-red-900/50 px-1.5 py-0.5 text-red-400">
                            STD: {{ prog.system.damage.standard }}
                        </div>
                        <div v-if="prog.system.damage.blackIce" class="text-[9px] bg-purple-900/30 border border-purple-900/50 px-1.5 py-0.5 text-purple-400">
                            B.ICE: {{ prog.system.damage.blackIce }}
                        </div>
                    </div>
                </div>
                <div class="space-y-1 text-right">
                    <div class="text-[8px] text-gray-500 uppercase font-bold">Cost</div>
                    <div class="text-[10px] text-blue-400 font-bold">{{ prog.system.price?.market }}eb</div>
                </div>
            </div>
        </div>

        <div v-if="isEffectivelyRezzed(prog) && !isExpanded" class="px-3 pb-2 pt-0">
            <div class="text-[9px] text-blue-300/60 font-mono border-t border-blue-900/30 pt-1 mt-1 truncate">
                Active Effect: {{ stripHtmlTags(prog.system.description.value) }}
            </div>
        </div>
    </div>
</template>

<script>
import { vIcon } from '../../directives/icon';
import { useCharacterData } from '../../composables/useCharacterData';
import { stripHtmlTags } from '../../utils/helpers';

export default {
    name: 'ProgramRow',
    props: ['prog', 'category', 'isExpanded'],
    directives: { icon: vIcon },
    emits: ['toggle-details', 'toggle-rez', 'execute'],
    setup() {
        const { getImageUrl } = useCharacterData();

        const isEffectivelyRezzed = (prog) => {
            if (prog.system.isRezzed) return true;
            const cls = (prog.system.class || '').toLowerCase();
            return ['antipersonnel', 'antiprogram', 'attacker'].some(c => cls.includes(c));
        };

        const canBeRezzed = (prog) => {
            const cls = (prog.system.class || '').toLowerCase();
            const name = (prog.name || '').toLowerCase();
            return ['defender', 'booster', 'blackice'].some(c => cls.includes(c)) ||
                   ['shield', 'armor'].some(n => name.includes(n));
        };

        return { getImageUrl, isEffectivelyRezzed, canBeRezzed, stripHtmlTags };
    }
}
</script>
