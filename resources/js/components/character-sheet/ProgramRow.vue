<template>
    <div class="group relative bg-cpr-black border transition-all duration-300"
         :class="isEffectivelyRezzed(prog) ? 'border-cpr-teal shadow-[0_0_15px_rgba(20,184,166,0.1)]' : 'border-cpr-border hover:border-cpr-teal/50'">

        <div class="absolute left-0 top-0 bottom-0 w-1 transition-colors duration-300"
             :class="isEffectivelyRezzed(prog) ? 'bg-cpr-teal' : 'bg-transparent group-hover:bg-cpr-teal-dark'"></div>

        <div class="p-3 pl-4 flex items-center justify-between">
            <div class="flex items-center gap-3 cursor-pointer flex-1" @click="$emit('toggle-details')">
                <div class="relative">
                    <img v-cpr-img:cpr-teal="getImageUrl(prog.img)"
                         class="w-9 h-9 object-cover border border-cpr-border bg-cpr-black"
                         :class="{'grayscale opacity-60': !isEffectivelyRezzed(prog)}">
                    <div v-if="isEffectivelyRezzed(prog)"
                         class="absolute -bottom-1 -right-1 w-3 h-3 bg-cpr-teal rounded-full border-2 border-black animate-pulse"></div>
                </div>
                <div>
                    <div
                        class="text-xs font-bold text-cpr-yellow uppercase tracking-tight group-hover:text-cpr-teal transition-colors flex items-center gap-2">
                        {{ prog.name }}
                        <span v-if="!isPlayer" class="text-cpr-teal/60 ml-1" title="Equipped (Locked)">
                            <i v-icon data-lucide="hand" class="w-3 h-3 inline"></i>
                        </span>
                        <i v-icon data-lucide="chevron-down" class="w-3 h-3 transition-transform duration-300"
                           :class="{'rotate-180': isExpanded}"></i>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 mt-0.5" v-if="isEffectivelyRezzed(prog)">
                        <span class="text-[9px] font-mono text-cpr-text-dim uppercase">{{
                                prog.system.class || 'Generic'
                            }}</span>
                        <span v-if="prog.system.rez && !['Anti-Personnel', 'Anti-Program'].includes(category)"
                              class="text-[9px]" :class="prog.system.rez.value < 5 ? 'text-cpr-red' : 'text-cpr-teal'">
                            REZ: <span class="font-numbers">{{ prog.system.rez.value }}/{{ prog.system.rez.max }}</span>
                        </span>
                        <span v-if="prog.system.atk" class="text-[9px] text-cpr-teal">
                            ATK: +<span class="font-numbers">{{ prog.system.atk }}</span>
                        </span>
                        <span v-if="prog.system.def" class="text-[9px] text-cpr-teal">
                            DEF: +<span class="font-numbers">{{ prog.system.def }}</span>
                        </span>
                        <span v-if="prog.system.per" class="text-[9px] text-cpr-yellow">
                            PER: +<span class="font-numbers">{{ prog.system.per }}</span>
                        </span>
                        <span v-if="prog.system.spd" class="text-[9px] text-cpr-yellow">
                            SPD: +<span class="font-numbers">{{ prog.system.spd }}</span>
                        </span>
                    </div>
                    <div v-else class="flex items-center gap-2 mt-0.5">
                        <span class="text-[9px] font-mono text-cpr-text-dim uppercase italic">De-rezzed</span>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-1 items-end">
                <button v-if="canBeRezzed(prog)"
                        @click.stop="$emit('toggle-rez')"
                        class="text-[9px] px-2 py-1 uppercase font-black border transition-all w-16 text-center"
                        :class="prog.system.isRezzed ? 'bg-cpr-teal-dark border-cpr-teal-dark text-black' : 'border-cpr-border text-cpr-text-dim hover:border-cpr-teal hover:text-cpr-teal'">
                    {{ prog.system.isRezzed ? 'DEREZ' : 'REZ' }}
                </button>

                <button @click.stop="$emit('execute')"
                        class="text-[9px] px-2 py-1 uppercase font-bold border border-cpr-border text-cpr-text-main hover:bg-white hover:text-black w-16 text-center transition-colors">
                    Execute
                </button>
            </div>
        </div>

        <!-- Expanded Details -->
        <div v-if="isExpanded" class="px-4 pb-3 border-t border-cpr-border/50 pt-2 bg-cpr-black/40">
            <div class="text-[10px] text-cpr-text-muted font-mono mb-3 leading-relaxed"
                 v-html="prog.system.description.value"></div>
            <div class="grid grid-cols-2 gap-2">
                <div v-if="prog.system.damage" class="space-y-1">
                    <div class="text-[8px] text-cpr-text-dim uppercase font-bold">Damage</div>
                    <div class="flex gap-2">
                        <div v-if="prog.system.damage.standard"
                             class="text-[9px] bg-cpr-red-dark/30 border border-cpr-red-dark/50 px-1.5 py-0.5 text-cpr-yellow">
                            STD: <span class="font-numbers">{{ prog.system.damage.standard }}</span>
                        </div>
                        <div v-if="prog.system.damage.blackIce"
                             class="text-[9px] bg-cpr-yellow-dark/30 border border-cpr-yellow-dark/50 px-1.5 py-0.5 text-cpr-text-main">
                            B.ICE: <span class="font-numbers">{{ prog.system.damage.blackIce }}</span>
                        </div>
                    </div>
                </div>
                <div class="space-y-1 text-right">
                    <div class="text-[8px] text-cpr-text-dim uppercase font-bold">Cost</div>
                    <div class="text-[10px] text-cpr-teal font-bold"><span
                        class="font-numbers">{{ prog.system.price?.market }}</span>eb
                    </div>
                </div>
            </div>
        </div>

        <div v-if="isEffectivelyRezzed(prog) && !isExpanded" class="px-3 pb-2 pt-0">
            <div class="text-[9px] text-cpr-teal/60 font-mono border-t border-cpr-teal-dark/30 pt-1 mt-1 truncate">
                Active Effect: {{ stripHtmlTags(prog.system.description.value) }}
            </div>
        </div>
    </div>
</template>

<script>
import {vIcon} from '../../directives/icon';
import {useCharacterData} from '../../composables/useCharacterData';
import {stripHtmlTags} from '../../utils/helpers';

export default {
    name: 'ProgramRow',
    props: ['prog', 'category', 'isExpanded'],
    directives: {icon: vIcon},
    emits: ['toggle-details', 'toggle-rez', 'execute'],
    setup() {
        const {getImageUrl, charData, isPlayer} = useCharacterData();

        const isEffectivelyRezzed = (prog) => {
            if (!isPlayer.value) return true;
            if (prog.system.isRezzed) return true;
            const cls = (prog.system.class || '').toLowerCase();
            return ['antipersonnel', 'antiprogram', 'attacker'].some(c => cls.includes(c));
        };

        const canBeRezzed = (prog) => {
            if (!isPlayer.value) return false;
            const cls = (prog.system.class || '').toLowerCase();
            const name = (prog.name || '').toLowerCase();
            return ['defender', 'booster', 'blackice'].some(c => cls.includes(c)) ||
                ['shield', 'armor'].some(n => name.includes(n));
        };

        return {getImageUrl, isEffectivelyRezzed, canBeRezzed, stripHtmlTags, isPlayer};
    }
}
</script>
