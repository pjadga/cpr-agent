<template>
    <div v-if="activeTab === 'control'" key="control" class="space-y-6 w-full p-3">

        <div v-if="hasNetspaceOptions" class="flex bg-cpr-black border border-cpr-border p-1 rounded-sm">
            <button @click="controlState.spaceMode = 'meatspace'"
                    class="flex-1 py-2 text-[10px] font-bold uppercase tracking-widest transition-all"
                    :class="controlState.spaceMode === 'meatspace' ? 'bg-cpr-red text-black' : 'text-cpr-text-dim hover:text-cpr-text-main'">
                Meatspace
            </button>
            <button @click="controlState.spaceMode = 'netspace'"
                    class="flex-1 py-2 text-[10px] font-bold uppercase tracking-widest transition-all"
                    :class="controlState.spaceMode === 'netspace' ? 'bg-cpr-teal-dark text-black' : 'text-cpr-text-dim hover:text-cpr-text-main'">
                Netspace
            </button>
        </div>

        <div v-if="(controlState.spaceMode === 'meatspace' || !hasNetspaceOptions)"
             class="space-y-6 animate-in fade-in duration-300">
            <div>
                <h3 class="flex items-center justify-between mb-3 mt-2 bg-cpr-black-deep py-1 px-2 border-l-2 border-cpr-red transition-colors group">
                    <span class="text-cpr-yellow font-bold text-sm uppercase">Target Acquisition</span>
                </h3>
                <div class="relative">
                    <!-- Trigger row -->
                    <div @click="showTargetDropdown = !showTargetDropdown"
                         class="relative flex items-stretch min-h-[3.25rem] cursor-pointer group"
                         :style="{ '--frame-accent': 'var(--cpr-border)' }">
                        <BasicRowFrame class="absolute inset-0 pointer-events-none select-none"/>
                        <div class="relative z-10 flex-1 flex items-center justify-between p-2 pl-4 gap-3">
                            <div v-if="controlState.selectedTarget" class="flex items-center gap-3">
                                <span class="text-xs font-bold text-cpr-yellow uppercase">{{
                                        controlState.selectedTarget.name
                                    }}</span>
                                <span class="text-[9px] text-cpr-text-dim">(<span
                                    class="font-numbers">{{ controlState.selectedTarget.distanceM }}</span>m)</span>
                            </div>
                            <span v-else class="text-xs text-cpr-text-dim uppercase italic font-mono">No Hostile Signal Locked</span>
                            <i v-icon data-lucide="chevron-down"
                               class="w-4 h-4 text-cpr-text-dim transition-transform duration-200"
                               :class="{'rotate-180': showTargetDropdown}"></i>
                        </div>
                    </div>

                    <!-- Target list dropdown -->
                    <transition name="expand">
                        <div v-if="showTargetDropdown" class="relative mt-1"
                             :style="{ '--frame-accent': 'var(--cpr-border)' }">
                            <DropdownInfoFrame class="absolute inset-0 pointer-events-none select-none"/>
                            <div class="relative z-10 max-h-64 overflow-y-auto p-2 space-y-1">
                                <div v-for="enemy in mockEnemies" :key="enemy.id"
                                     @click="controlState.selectedTarget = enemy; showTargetDropdown = false"
                                     class="p-2 flex items-center justify-between cursor-pointer hover:bg-cpr-red-dark/20 transition-colors">
                                    <div>
                                        <div class="text-xs font-bold text-cpr-yellow uppercase">{{ enemy.name }}</div>
                                        <div class="text-[9px] text-cpr-text-dim"><span
                                            class="font-numbers">{{ enemy.distanceM }}</span>m away
                                        </div>
                                    </div>
                                    <div v-if="controlState.selectedTarget?.id === enemy.id" class="text-cpr-red">
                                        <i v-icon data-lucide="target" class="w-4 h-4"></i>
                                    </div>
                                </div>
                                <div v-if="mockEnemies.length === 0" class="p-6 text-center">
                                    <span class="text-[10px] text-cpr-text-dim uppercase font-mono italic">No hostile signals detected</span>
                                </div>
                            </div>
                        </div>
                    </transition>
                </div>
            </div>

            <div>
                <h3 class="flex items-center justify-between mb-3 mt-2 bg-cpr-black-deep py-1 px-2 border-l-2 border-cpr-red transition-colors group">
                    <span class="text-cpr-yellow font-bold text-sm uppercase">Weapon Systems</span>
                </h3>
                <div class="space-y-2">
                    <div v-for="weapon in groupedEquipped.weapon" :key="weapon._id"
                         @click="controlState.selectedWeapon = weapon"
                         class="relative cursor-pointer transition-all"
                         :class="controlState.selectedWeapon?._id === weapon._id ? 'ring-2 ring-cpr-yellow' : 'opacity-80 hover:opacity-100'">
                        <item-row :item="weapon" category="weapon" active-tab="control"
                                  :all-items="charData?.data?.items"
                                  @click="controlState.selectedWeapon = weapon"/>
                        <div v-if="controlState.selectedWeapon?._id === weapon._id"
                             class="absolute top-2 right-2 z-20 text-cpr-yellow pointer-events-none">
                            <i v-icon data-lucide="check-circle-2" class="w-5 h-5"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Trigger for Combat Panel -->
            <div class="fixed bottom-24 right-6 z-[60]">
                <button @click="$emit('open-combat-panel')"
                        class="w-16 h-16 bg-cpr-red text-black rounded-full flex items-center justify-center shadow-[0_0_20px_rgba(220,38,38,0.5)] active:scale-95 transition-all border-4 border-black group">
                    <i v-icon data-lucide="crosshair"
                       class="w-8 h-8 group-hover:rotate-90 transition-transform duration-500"></i>
                    <div
                        class="absolute -top-1 -right-1 w-5 h-5 bg-white rounded-full flex items-center justify-center border-2 border-black"
                        v-if="controlState.selectedWeapon">
                        <div class="w-2 h-2 bg-cpr-red rounded-full animate-ping"></div>
                    </div>
                </button>
            </div>
        </div>

        <div v-else-if="controlState.spaceMode === 'netspace' && hasNetspaceOptions"
             class="space-y-6 animate-in fade-in duration-300">
            <div v-if="activeCyberdeck" class="relative">
                <div class="cpr-panel p-4 bg-cpr-black-deep/20 border border-cpr-teal/30 mb-4 overflow-hidden group">
                    <div class="relative z-10 flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-black text-cpr-yellow uppercase tracking-tighter">
                                {{ activeCyberdeck.name }}</h3>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-[9px] px-1.5 py-0.5 bg-cpr-teal text-black font-black uppercase">Active Deck</span>
                                <span class="text-[9px] text-cpr-teal font-mono font-bold uppercase">{{
                                        activeCyberdeck.system.quality
                                    }} Quality</span>
                            </div>
                        </div>
                        <button @click="$emit('open-cyberdeck-config')"
                                class="flex items-center gap-1.5 px-3 py-1.5 bg-cpr-teal-dark/40 border border-cpr-teal/50 text-[10px] font-bold text-cpr-text-bright uppercase hover:bg-cpr-teal hover:text-black transition-all shadow-[0_0_10px_rgba(20,184,166,0.2)]">
                            <i v-icon data-lucide="settings" class="w-3 h-3"></i>
                        </button>
                    </div>

                    <h4 class="text-[10px] font-bold text-cpr-teal uppercase tracking-[0.2em] mb-2 pl-1 relative z-10">
                        Quick Actions</h4>
                    <div class="grid grid-cols-3 gap-2 relative z-10 mb-2">
                        <button v-for="action in ['Jack In', 'Breach', 'Quickhack']"
                                :key="action"
                                @click="$emit('program-roll-request', { programName: action, type: 'interface', cyberdeckId: activeCyberdeck._id})"
                                class="relative py-3 px-1 flex items-center justify-center text-[9px] font-bold text-cpr-text-bright uppercase hover:text-cpr-teal hover:[--frame-colour-change:var(--cpr-teal)] transition-all group"
                                :style="{ '--frame-border': 'var(--cpr-teal)' }">
                            <med-stat-frame/>
                            <span class="relative z-10 text-center leading-tight">{{ action }}</span>
                        </button>
                    </div>

                    <h4 class="text-[10px] font-bold text-cpr-teal uppercase tracking-[0.2em] mb-2 pl-1 relative z-10">
                        Interface Actions</h4>
                    <div class="grid grid-cols-3 gap-2 relative z-10">
                        <button
                            v-for="action in ['Scanner', 'Backdoor', 'Cloak', 'Control', 'Eye-Dee', 'Pathfinder', 'Slide', 'Virus', 'Zap', 'Defense', 'Speed']"
                            :key="action"
                            @click="$emit('program-roll-request', { programName: action, type: 'interface', cyberdeckId: activeCyberdeck._id})"
                            class="relative py-3 px-1 flex items-center justify-center text-[9px] font-bold text-cpr-text-bright uppercase hover:text-cpr-teal hover:[--frame-colour-change:var(--cpr-teal)] transition-all group"
                            :style="{ '--frame-border': 'var(--cpr-teal)' }">
                            <med-stat-frame/>
                            <span class="relative z-10 text-center leading-tight">{{ action }}</span>
                        </button>
                    </div>
                </div>

                <div v-if="activeCyberdeck" class="space-y-6">
                    <div v-for="(programs, category) in programCategories" :key="category">
                        <div v-if="programs.length > 0">
                            <h3 class="flex items-center justify-between mb-3 mt-4 bg-cpr-black-deep py-1 px-2 border-l-2 border-cpr-red transition-colors group">
                                <span class="text-cpr-yellow font-bold text-sm uppercase">{{ category }}</span>
                                <span class="text-[9px] text-cpr-text-dim font-normal"><span
                                    class="font-numbers">{{ programs.length }}</span> Installed</span>
                            </h3>

                            <div class="space-y-3">
                                <program-row
                                    v-for="prog in programs" :key="prog._id"
                                    :prog="prog"
                                    :category="category"
                                    :is-expanded="expandedPrograms[prog._id]"
                                    @toggle-details="$emit('toggle-program-details', prog._id)"
                                    @toggle-rez="$emit('toggle-rez', prog)"
                                    @execute="$emit('roll-program', prog, activeCyberdeck)">
                                </program-row>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="!activeCyberdeck"
                     class="cpr-panel p-8 bg-cpr-red-dark/10 border border-cpr-red/30 text-center space-y-4">
                    <i v-icon data-lucide="alert-triangle" class="w-12 h-12 text-cpr-red/40 mx-auto"></i>
                    <div>
                        <h4 class="text-cpr-red font-black uppercase tracking-tighter text-lg">No Cyberdeck
                            Detected</h4>
                        <p class="text-[10px] text-cpr-red/60 font-mono uppercase tracking-widest mt-2">Equip a
                            Cyberdeck in Gear to access Netspace.</p>
                    </div>
                </div>
            </div>


        </div>

        <!-- Tactical Movement -->
        <div class="pt-4 border-t border-cpr-border/50">
            <div class="flex items-center justify-center gap-4 mb-3">
                <div class="h-px bg-cpr-border/30 flex-1"></div>
                <span class="text-[10px] font-black text-cpr-text-dim uppercase tracking-[0.3em]">Positioning</span>
                <div class="h-px bg-cpr-border/30 flex-1"></div>
            </div>
            <div class="grid grid-cols-3 gap-2 max-w-[250px] mx-auto pb-4">
                <div></div>
                <button @click="$emit('move', 'up')"
                        class="relative aspect-square flex items-center justify-center text-cpr-yellow hover:[--frame-colour-change:var(--cpr-red)] active:scale-90 transition-all group cursor-pointer">
                    <simple-stat-frame class="rotate-0"/>
                    <i v-icon data-lucide="chevron-up" class="relative z-10 w-6 h-6 pointer-events-none"></i>
                </button>
                <div></div>
                <button @click="$emit('move', 'left')"
                        class="relative aspect-square flex items-center justify-center text-cpr-yellow hover:[--frame-colour-change:var(--cpr-red)] active:scale-90 transition-all group cursor-pointer">
                    <simple-stat-frame class="-rotate-90"/>
                    <i v-icon data-lucide="chevron-left" class="relative z-10 w-6 h-6 pointer-events-none"></i>
                </button>
                <button @click="$emit('move', 'down')"
                        class="relative aspect-square flex items-center justify-center text-cpr-yellow hover:[--frame-colour-change:var(--cpr-red)] active:scale-90 transition-all group cursor-pointer">
                    <simple-stat-frame class="rotate-180"/>
                    <i v-icon data-lucide="chevron-down" class="relative z-10 w-6 h-6 pointer-events-none"></i>
                </button>
                <button @click="$emit('move', 'right')"
                        class="relative aspect-square flex items-center justify-center text-cpr-yellow hover:[--frame-colour-change:var(--cpr-red)] active:scale-90 transition-all group cursor-pointer">
                    <simple-stat-frame class="rotate-90"/>
                    <i v-icon data-lucide="chevron-right" class="relative z-10 w-6 h-6 pointer-events-none"></i>
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import {computed, ref} from 'vue';
import {vIcon} from '../../directives/icon';
import {useCharacterData} from '../../composables/useCharacterData';
import ProgramRow from './ProgramRow.vue';
import ItemRow from './ItemRow.vue';
import BasicRowFrame from './frames/BasicRowFrame.vue';
import DropdownInfoFrame from './frames/DropdownInfoFrame.vue';
import SimpleStatFrame from './frames/SimpleStatFrame.vue';
import MedStatFrame from './frames/MedStatFrame.vue';


export default {
    name: 'ControlTab',
    props: [
        'activeTab', 'controlState', 'mockEnemies', 'groupedEquipped',
        'availableFireModes', 'dvLoading', 'dvError', 'dvInfo',
        'activeCyberdeck', 'programCategories', 'expandedPrograms', 'refreshAll'
    ],
    components: {ProgramRow, ItemRow, BasicRowFrame, DropdownInfoFrame, SimpleStatFrame, MedStatFrame},
    directives: {icon: vIcon},
    emits: [
        'roll-hit', 'roll-damage', 'reload', 'move',
        'open-cyberdeck-config', 'open-combat-panel', 'program-roll-request',
        'toggle-program-details', 'toggle-rez', 'roll-program'
    ],
    setup(props) {
        const {getImageUrl, charData, isPlayer} = useCharacterData();
        const showTargetDropdown = ref(false);

        const isMeleeOrUnarmed = (item) => {
            const type = item.system.weaponType || '';
            return type.toLowerCase().includes('melee') || type.toLowerCase().includes('martial') || type === 'unarmed' || item.system.isMelee;
        };

        const getAmmoTotal = (weapon) => {
            if (!charData.value) return 0;
            const allItems = charData.value.data.items;

            // First, try to find the installed ammo
            if (weapon.system.installedItems && weapon.system.installedItems.list) {
                const ammoId = weapon.system.installedItems.list[0];
                if (ammoId) {
                    const ammoItem = allItems.find(i => i._id === ammoId);
                    if (ammoItem) {
                        return ammoItem.system.amount || ammoItem.system.count || 0;
                    }
                }
            }

            // Fallback to searching by weapon type if no specific ammo is installed
            const wType = weapon.system.weaponType;
            if (!wType) return weapon.system.magazine?.max || 0;

            const total = allItems
                .filter(i => i.type === 'ammo' && i.system.ammoType === wType)
                .reduce((sum, i) => sum + (i.system.amount || i.system.count || 0), 0);

            return total || weapon.system.magazine?.max || 0;
        };

        const getAmmoName = (weapon) => {
            if (!charData.value || !weapon.system.installedItems || !weapon.system.installedItems.list) return null;
            const ammoId = weapon.system.installedItems.list[0];
            if (!ammoId) return null;
            const ammoItem = charData.value.data.items.find(i => i._id === ammoId);
            return ammoItem ? ammoItem.name : null;
        };

        const getStatusLine = (weapon) => {
            const sys = weapon.system;
            if (isMeleeOrUnarmed(weapon) || !sys.magazine || sys.magazine.max <= 0) return null;

            let percent = (sys.magazine.value / sys.magazine.max) * 100;
            percent = Math.min(100, Math.max(0, percent));

            let color = 'bg-green-500';
            if (percent < 25) color = 'bg-cpr-red';
            else if (percent < 60) color = 'bg-amber-500';

            return {
                width: `${percent}%`,
                color: color
            };
        };

        const hasNetspaceOptions = computed(() => {
            return !!props.activeCyberdeck;
        });

        return {
            getImageUrl,
            showTargetDropdown,
            isMeleeOrUnarmed,
            getAmmoTotal,
            getAmmoName,
            getStatusLine,
            hasNetspaceOptions,
            isPlayer,
            charData
        };
    }
}
</script>

<style @cspNonce scoped>
.expand-enter-active,
.expand-leave-active {
    transition: all 0.2s ease-out;
    max-height: 500px;
    opacity: 1;
}

.expand-enter-from,
.expand-leave-to {
    max-height: 0;
    opacity: 0;
    overflow: hidden;
}
</style>
