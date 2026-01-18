<template>
    <div v-if="activeTab === 'control'" key="control" class="space-y-6 absolute w-full top-0 left-0 p-3 pb-20">

        <div class="flex bg-[#0a0a0a] border border-gray-800 p-1 rounded-sm">
            <button @click="controlState.spaceMode = 'meatspace'"
                    class="flex-1 py-2 text-[10px] font-bold uppercase tracking-widest transition-all"
                    :class="controlState.spaceMode === 'meatspace' ? 'bg-red-600 text-black' : 'text-gray-500 hover:text-gray-300'">
                Meatspace
            </button>
            <button @click="controlState.spaceMode = 'netspace'"
                    class="flex-1 py-2 text-[10px] font-bold uppercase tracking-widest transition-all"
                    :class="controlState.spaceMode === 'netspace' ? 'bg-blue-600 text-black' : 'text-gray-500 hover:text-gray-300'">
                Netspace
            </button>
        </div>

        <div v-if="controlState.spaceMode === 'meatspace'" class="space-y-6 animate-in fade-in duration-300">
            <div>
                <h3 class="text-xs font-black text-gray-500 uppercase tracking-[0.2em] mb-3 flex items-center gap-2">
                    <i v-icon data-lucide="crosshair" class="w-3.5 h-3.5"></i> Target Acquisition
                </h3>
                <div class="grid grid-cols-1 gap-2">
                    <div v-for="enemy in mockEnemies" :key="enemy.id"
                         @click="controlState.selectedTarget = enemy"
                         class="cpr-panel p-3 flex items-center justify-between cursor-pointer border transition-all"
                         :class="controlState.selectedTarget?.id === enemy.id ? 'border-red-600 bg-red-900/10' : 'border-gray-800 bg-[#0a0a0a] hover:border-gray-600'">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-red-900/20 border border-red-900/40 flex items-center justify-center text-red-500 font-bold text-xs">
                                {{ enemy.name[0] }}
                            </div>
                            <div>
                                <div class="text-xs font-bold text-white uppercase">{{ enemy.name }}</div>
                                <div class="text-[9px] text-gray-500 font-mono">{{ enemy.distanceM }}m away</div>
                            </div>
                        </div>
                        <div v-if="controlState.selectedTarget?.id === enemy.id" class="text-red-500">
                            <i v-icon data-lucide="target" class="w-5 h-5 animate-pulse"></i>
                        </div>
                    </div>
                    <div v-if="mockEnemies.length === 0" class="p-6 border border-dashed border-gray-800 text-center">
                        <span class="text-[10px] text-gray-600 uppercase font-mono italic">No hostile signals detected</span>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-xs font-black text-gray-500 uppercase tracking-[0.2em] mb-3 flex items-center gap-2">
                    <i v-icon data-lucide="zap" class="w-3.5 h-3.5"></i> Weapon Systems
                </h3>
                <div class="space-y-2">
                    <div v-for="weapon in groupedEquipped.weapon" :key="weapon._id"
                         @click="controlState.selectedWeapon = weapon"
                         class="cpr-panel p-3 flex items-center justify-between cursor-pointer border transition-all"
                         :class="controlState.selectedWeapon?._id === weapon._id ? 'border-orange-600 bg-orange-900/10' : 'border-gray-800 bg-[#0a0a0a] hover:border-gray-600'">
                        <div class="flex items-center gap-3">
                            <img :src="getImageUrl(weapon.img)" class="w-10 h-10 object-cover border border-gray-800">
                            <div>
                                <div class="text-xs font-bold text-white uppercase">{{ weapon.name }}</div>
                                <div class="text-[9px] text-gray-500 font-mono">{{ weapon.system.weaponType }} | {{ weapon.system.damage }}</div>
                            </div>
                        </div>
                        <div v-if="controlState.selectedWeapon?._id === weapon._id" class="text-orange-500">
                            <i v-icon data-lucide="check-circle-2" class="w-5 h-5"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="controlState.selectedWeapon" class="space-y-4 pt-2 border-t border-gray-800/50 animate-in slide-in-from-bottom-4 duration-300">
                <div class="flex items-center justify-between bg-black/40 border border-gray-800 p-3">
                    <div>
                        <div class="text-[10px] text-gray-500 uppercase font-bold mb-1">Fire Mode</div>
                        <div class="flex gap-1">
                            <button v-for="mode in availableFireModes" :key="mode.id"
                                    @click="controlState.fireMode = mode.id"
                                    class="px-2 py-1 text-[9px] font-black uppercase border transition-all flex items-center gap-1"
                                    :class="controlState.fireMode === mode.id ? 'bg-orange-600 border-orange-600 text-black' : 'border-gray-800 text-gray-500 hover:text-gray-300'">
                                <i v-icon :data-lucide="mode.icon" class="w-3 h-3"></i> {{ mode.label }}
                            </button>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-[10px] text-gray-500 uppercase font-bold mb-1">Target DV</div>
                        <div v-if="dvLoading" class="text-orange-500 animate-pulse font-mono font-bold">CALC...</div>
                        <div v-else-if="dvError" class="text-red-500 font-mono text-xs">ERR</div>
                        <div v-else class="text-2xl font-black text-white font-mono leading-none">{{ dvInfo || '--' }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <button @click="$emit('roll-hit')"
                            :disabled="controlState.reloadRequired"
                            class="py-4 bg-red-600 text-black font-black uppercase tracking-tighter flex flex-col items-center justify-center hover:bg-red-500 transition-all active:scale-95 disabled:opacity-50 disabled:grayscale disabled:cursor-not-allowed">
                        <span class="text-xl">Execute Attack</span>
                        <span v-if="controlState.reloadRequired" class="text-[10px] animate-pulse">Out of Ammo!</span>
                    </button>
                    <button @click="$emit('roll-damage')"
                            class="py-4 border-2 border-red-600 text-red-500 font-black uppercase tracking-tighter flex flex-col items-center justify-center hover:bg-red-600 hover:text-black transition-all active:scale-95">
                        <span class="text-xl">Roll Damage</span>
                    </button>
                </div>

                <button v-if="controlState.selectedWeapon.system.magazine"
                        @click="$emit('reload')"
                        class="w-full py-2 bg-gray-900 border border-gray-800 text-gray-400 font-bold uppercase text-[10px] tracking-widest hover:bg-gray-800 hover:text-white transition-all">
                    Reload Systems
                </button>
            </div>

            <div>
                <h3 class="text-xs font-black text-gray-500 uppercase tracking-[0.2em] mb-3">Tactical Movement</h3>
                <div class="grid grid-cols-3 gap-2 max-w-[200px] mx-auto">
                    <div></div>
                    <button @click="$emit('move', 'up')" class="p-4 bg-gray-900 border border-gray-800 text-white hover:bg-gray-800 active:bg-red-900/40 transition-colors"><i v-icon data-lucide="chevron-up"></i></button>
                    <div></div>
                    <button @click="$emit('move', 'left')" class="p-4 bg-gray-900 border border-gray-800 text-white hover:bg-gray-800 active:bg-red-900/40 transition-colors"><i v-icon data-lucide="chevron-left"></i></button>
                    <button @click="$emit('move', 'down')" class="p-4 bg-gray-900 border border-gray-800 text-white hover:bg-gray-800 active:bg-red-900/40 transition-colors"><i v-icon data-lucide="chevron-down"></i></button>
                    <button @click="$emit('move', 'right')" class="p-4 bg-gray-900 border border-gray-800 text-white hover:bg-gray-800 active:bg-red-900/40 transition-colors"><i v-icon data-lucide="chevron-right"></i></button>
                </div>
            </div>
        </div>

        <div v-else-if="controlState.spaceMode === 'netspace'" class="space-y-6 animate-in fade-in duration-300">
            <div v-if="activeCyberdeck" class="relative">
                <div class="cpr-panel p-4 bg-blue-950/20 border border-blue-500/30 mb-4 overflow-hidden group">
                    <div class="absolute top-0 right-0 p-2 opacity-20 group-hover:opacity-100 transition-opacity">
                        <i v-icon data-lucide="cpu" class="w-16 h-16 text-blue-500"></i>
                    </div>
                    <div class="relative z-10 flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-black text-white uppercase tracking-tighter">{{ activeCyberdeck.name }}</h3>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-[9px] px-1.5 py-0.5 bg-blue-500 text-black font-black uppercase">Active Deck</span>
                                <span class="text-[9px] text-blue-400 font-mono font-bold uppercase">{{ activeCyberdeck.system.quality }} Quality</span>
                            </div>
                        </div>
                        <button @click="$emit('open-cyberdeck-config')" class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-900/40 border border-blue-500/50 text-[10px] font-bold text-blue-100 uppercase hover:bg-blue-500 hover:text-black transition-all shadow-[0_0_10px_rgba(59,130,246,0.2)]">
                            <i v-icon data-lucide="settings" class="w-3 h-3"></i> Config
                        </button>
                    </div>

                    <div class="grid grid-cols-2 gap-px bg-blue-900/30 border border-blue-500/30 mb-6 relative z-10">
                        <div class="bg-black/80 p-3 text-center flex flex-col items-center">
                            <div class="text-[9px] text-blue-400/80 uppercase font-bold tracking-widest mb-1">Defense</div>
                            <div class="text-3xl font-black text-white leading-none">{{ activeCyberdeck.system.quality === 'poor' ? '7' : activeCyberdeck.system.quality === 'excellent' ? '11' : '9' }}</div>
                        </div>
                        <div class="bg-black/80 p-3 text-center flex flex-col items-center">
                            <div class="text-[9px] text-blue-400/80 uppercase font-bold tracking-widest mb-1">Speed</div>
                            <div class="text-3xl font-black text-white leading-none">{{ activeCyberdeck.system.spd || '4' }}</div>
                        </div>
                    </div>

                    <h4 class="text-[10px] font-bold text-blue-500 uppercase tracking-[0.2em] mb-2 pl-1 relative z-10">Interface Actions</h4>
                    <div class="grid grid-cols-3 gap-2 relative z-10">
                        <button v-for="action in ['Scanner', 'Backdoor', 'Cloak', 'Control', 'Eye-Dee', 'Pathfinder', 'Slide', 'Virus', 'Zap']"
                                :key="action"
                                @click="$emit('program-roll-request', { label: action, type: 'interface' })"
                                class="py-3 bg-blue-900/20 border border-blue-500/30 text-[9px] font-bold text-blue-100 uppercase hover:bg-blue-500 hover:text-black hover:border-blue-400 transition-all">
                            {{ action }}
                        </button>
                    </div>
                </div>

                <div v-if="activeCyberdeck" class="space-y-6">
                    <div v-for="(programs, category) in programCategories" :key="category">
                        <div v-if="programs.length > 0">
                            <h3 class="text-xs font-black text-white uppercase tracking-widest border-l-4 border-blue-500 pl-2 mb-3 flex items-center justify-between">
                                {{ category }}
                                <span class="text-[9px] text-gray-500 font-mono font-normal">{{ programs.length }} Installed</span>
                            </h3>

                            <div class="space-y-3">
                                <program-row
                                    v-for="prog in programs" :key="prog._id"
                                    :prog="prog"
                                    :category="category"
                                    :is-expanded="expandedPrograms[prog._id]"
                                    @toggle-details="$emit('toggle-program-details', prog._id)"
                                    @toggle-rez="$emit('toggle-rez', prog)"
                                    @execute="$emit('roll-program', prog)">
                                </program-row>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="!activeCyberdeck" class="cpr-panel p-8 bg-red-900/10 border border-red-500/30 text-center space-y-4">
                    <i v-icon data-lucide="alert-triangle" class="w-12 h-12 text-red-500/40 mx-auto"></i>
                    <div>
                        <h4 class="text-red-400 font-black uppercase tracking-tighter text-lg">No Cyberdeck Detected</h4>
                        <p class="text-[10px] text-red-500/60 font-mono uppercase tracking-widest mt-2">Equip a Cyberdeck in Gear to access Netspace.</p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>

<script>
import { vIcon } from '../../directives/icon';
import { useCharacterData } from '../../composables/useCharacterData';
import ProgramRow from './ProgramRow.vue';

export default {
    name: 'ControlTab',
    props: [
        'activeTab', 'controlState', 'mockEnemies', 'groupedEquipped',
        'availableFireModes', 'dvLoading', 'dvError', 'dvInfo',
        'activeCyberdeck', 'programCategories', 'expandedPrograms'
    ],
    components: { ProgramRow },
    directives: { icon: vIcon },
    emits: [
        'roll-hit', 'roll-damage', 'reload', 'move',
        'open-cyberdeck-config', 'program-roll-request',
        'toggle-program-details', 'toggle-rez', 'roll-program'
    ],
    setup() {
        const { getImageUrl } = useCharacterData();
        return { getImageUrl };
    }
}
</script>
