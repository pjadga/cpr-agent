<template>
    <div
        class="cpr-panel bg-[var(--cpr-black-soft)] flex flex-col transition-colors border-l-2 relative group border-gray-800"
        :class="{
            'border-cpr-red': category === 'weapon',
            'border-cpr-teal-dark': category === 'armor',
            'border-cpr-yellow-dark': category === 'ammo',
            'border-cpr-yellow': category === 'drug',
            'border-cpr-border-muted': category === 'gear',
            'border-cpr-teal': category === 'cyberware',
            'border-cpr-red border-dashed': category === 'criticalInjury',
            'z-20': showEquipPopup
         }">

        <div class="cpr-corner-square"
             :class="{
                'text-cpr-red': category === 'weapon',
                'text-cpr-teal-dark': category === 'armor',
                'text-cpr-yellow-dark': category === 'ammo',
                'border-cpr-yellow-dark': category === 'drug',
                'text-cpr-border-muted': category === 'gear',
                'text-cpr-teal': category === 'cyberware',
                'text-cpr-red': category === 'criticalInjury'
             }">
        </div>

        <div v-if="statusLine" class="absolute top-0 left-0 h-[3px] transition-all duration-500 z-10"
             :class="statusLine.color"
             :style="{ width: statusLine.width }">
        </div>

        <div class="flex items-stretch min-h-[3.5rem]">
            <div class="flex items-center pl-3 py-2 shrink-0">
                <img v-cpr-img:[categoryColor]="getImageUrl(item.img)"
                     class="w-10 h-10 object-cover border border-cpr-border bg-cpr-black/50"/>
            </div>
            <div class="flex-1 p-2 flex flex-col justify-center cursor-default transition-colors"
                 @click="console.log('Row clicked:', item.name); $emit('click', item)">
                <div class="flex justify-between items-start pr-2">
                    <div class="flex flex-col">
                    <span class="text-sm font-bold text-cpr-text-main group-hover:text-cpr-teal transition-colors">
                        {{ item.name }}
                        <span v-if="category === 'weapon' && ammoName"
                              class="text-cpr-yellow text-[10px] ml-2 font-normal">({{ ammoName }})</span>
                    </span>

                        <div
                            v-if="category === 'criticalInjury' && item.system.description && item.system.description.value"
                            class="text-cpr-text-dim text-[11px] leading-tight font-sans mt-1"
                            v-html="item.system.description.value">
                        </div>

                        <div class="flex flex-wrap gap-2 mt-1">
                            <template v-if="category === 'armor' && armorDisplay">
                                <div v-for="stat in armorDisplay" :key="stat.label"
                                     class="flex items-center gap-1 mr-2">
                                    <i v-icon :data-lucide="stat.icon" class="w-3 h-3 text-cpr-teal"></i>
                                    <span class="text-[10px] text-cpr-teal">
                                        <span class="text-[8px] uppercase text-cpr-teal-dark mr-0.5">{{
                                                stat.label
                                            }}:</span>
                                        <span class="font-numbers">{{ stat.value }}</span>
                                    </span>
                                </div>
                            </template>

                            <div v-else-if="category === 'ammo'" class="flex items-center gap-1">
                                <span class="text-md font-black text-cpr-yellow">
                                    x<span class="font-numbers">{{
                                        item.system.amount || item.system.count || 0
                                    }}</span>
                                </span>
                            </div>


                            <div v-if="category === 'weapon'" class="flex items-center gap-1">
                                <i v-icon data-lucide="logs" class="w-3 h-3 text-cpr-text-dim"
                                   v-if="!isMeleeOrUnarmed"></i>
                                <span class="text-[10px] text-cpr-text-muted">
                                    <template v-if="!isMeleeOrUnarmed && item.system.magazine">
                                        <span class="font-numbers">{{ item.system.magazine.value }}</span> <span
                                        class="text-cpr-text-dim">/</span> <span
                                        class="font-numbers">{{ item.system.magazine.max }}</span>
                                        <span v-if="ammoTotal !== null"
                                              class="text-cpr-yellow ml-1 font-numbers">[{{ ammoTotal }}]</span>
                                    </template>
                                    <span v-if="item.system.damage" :class="{'ml-2': !isMeleeOrUnarmed}"
                                          class="text-cpr-red font-bold font-numbers">{{ item.system.damage }}</span>
                                    <span v-if="item.system.rof > 0" class="ml-2 text-cpr-teal">ROF:<span
                                        class="font-numbers">{{ item.system.rof }}</span></span>
                                </span>
                            </div>


                            <!--                            <div v-else-if="(item.system.amount || item.system.count) && category !== 'armor'" class="flex items-center gap-1">-->
                            <!--                                <i v-icon data-lucide="hash" class="w-3 h-3 text-cpr-text-dim"></i>-->
                            <!--                                <span class="text-[10px] font-mono text-cpr-text-muted">-->
                            <!--                                    x{{ item.system.amount || item.system.count }}-->
                            <!--                                </span>-->
                            <!--                            </div>-->

                            <div v-if="category === 'cyberware'" class="flex items-center gap-1">
                                <i v-icon data-lucide="cpu" class="w-3 h-3 text-cpr-teal"></i>
                                <span class="text-[10px] font-numbers text-cpr-teal">
                                {{ item.system.installedItems.usedSlots }} / {{ item.system.installedItems.slots }}
                            </span>
                            </div>

                            <div v-if="category === 'criticalInjury'" class="flex items-center gap-1">
                                <i v-icon data-lucide="activity" class="w-3 h-3 text-cpr-red"></i>
                                <span class="text-[10px] font-numbers text-cpr-red font-bold uppercase">
                                    {{ injuryModifier }}
                                </span>
                            </div>

                            <!--                            <span v-if="item.system.installLocation" class="text-[9px] text-cpr-text-dim uppercase">-->
                            <!--                            [{{ item.system.installLocation }}]-->
                            <!--                            </span>-->


                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="category !== 'cyberware' && category !== 'skill' && category !== 'role' && category !== 'ammo' && category !== 'criticalInjury' && category !== 'drug' && category !== 'gear'"
                class="flex items-center gap-1 shrink-0 relative mr-1">
                <button
                    @click.stop="$emit('equip-click', item)"
                    class="p-2 text-cpr-text-dim hover:text-white transition-colors flex items-center justify-center relative z-10"
                    :title="'Status: ' + equipStateLabel"
                >
                    <i v-icon :data-lucide="equipIcon" class="w-5 h-5 pointer-events-none"></i>
                </button>
            </div>

            <div v-if="category === 'drug' || category === 'gear'"
                 class="flex items-center gap-1 shrink-0 relative mr-1">
                <button
                    v-if="category === 'drug'"
                    @click.stop="$emit('consume-click', item)"
                    class="p-2 text-cpr-text-dim hover:text-white transition-colors flex items-center justify-center relative z-10"
                    title="Consume Item"
                >
                    <i v-icon :data-lucide="consumeIcon" class="w-5 h-5 pointer-events-none"></i>
                </button>
                <button
                    @click.stop="$emit('equip-click', item)"
                    class="p-2 text-cpr-text-dim hover:text-white transition-colors flex items-center justify-center relative z-10"
                    :title="'Status: ' + equipStateLabel"
                >
                    <i v-icon :data-lucide="equipIcon" class="w-5 h-5 pointer-events-none"></i>
                </button>
            </div>

            <button
                @click.stop="toggle"
                class="w-12 shrink-0 flex items-center justify-center bg-cpr-black-deep/30 border-l border-white/5 active:bg-cpr-teal-dark/20 transition-colors hover:bg-cpr-teal-dark/40 cursor-pointer">
                <div class="transition-transform duration-300 flex items-center justify-center"
                     :class="{ 'rotate-180': expanded }">
                    <i v-icon data-lucide="chevron-down"
                       class="w-5 h-5 text-cpr-text-dim group-hover:text-cpr-teal">
                    </i>
                </div>
            </button>
        </div>

        <transition name="expand">
            <div v-if="expanded" class="border-t border-white/5 bg-cpr-black/40 p-3 shadow-inner">
                <template v-if="category !== 'criticalInjury'">
                    <div v-if="item.system.description && item.system.description.value"
                         class="text-cpr-text-main text-xs leading-relaxed font-sans cpr-description mb-3"
                         v-html="item.system.description.value">
                    </div>
                    <div v-else class="text-cpr-text-dim text-xs italic mb-3">No description available.</div>
                </template>

                <div v-if="category === 'criticalInjury'" class="grid grid-cols-2 gap-2 mb-3">
                    <div
                        v-if="item.system.quickFix && (item.system.quickFix.dvFirstAid || item.system.quickFix.dvParamedic)"
                        class="bg-cpr-black-deep/50 p-2 border-l-2 border-cpr-teal flex flex-col gap-1">
                        <span class="text-[9px] text-cpr-teal uppercase font-bold">Quick Fix</span>
                        <div class="flex justify-between items-center text-[10px]">
                            <span class="text-cpr-text-dim capitalize">{{ item.system.quickFix.type }}</span>
                            <span class="text-white font-numbers">DV{{
                                    item.system.quickFix.dvFirstAid || item.system.quickFix.dvParamedic
                                }}</span>
                        </div>
                    </div>
                    <div
                        v-if="item.system.treatment && (item.system.treatment.dvParamedic || item.system.treatment.dvSurgery)"
                        class="bg-cpr-black-deep/50 p-2 border-l-2 border-cpr-red flex flex-col gap-1">
                        <span class="text-[9px] text-cpr-red uppercase font-bold">Treatment</span>
                        <div class="flex justify-between items-center text-[10px]">
                            <span class="text-cpr-text-dim capitalize">{{ item.system.treatment.type }}</span>
                            <span class="text-white font-numbers">DV{{
                                    item.system.treatment.dvParamedic || item.system.treatment.dvSurgery
                                }}</span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 text-[10px] uppercase font-bold text-cpr-text-dim">
                    <div v-if="item.system.price"
                         class="bg-cpr-black-deep/50 p-1 px-2 border-l-2 border-cpr-yellow-dark flex justify-between items-center">
                        <span>Cost</span>
                        <span class="text-cpr-yellow font-numbers">
                            {{ item.system.price.market }}eb
                        </span>
                    </div>
                    <div v-if="item.system.concealable"
                         class="bg-cpr-black-deep/50 p-1 px-2 border-l-2 flex justify-between items-center"
                         :class="item.system.concealable.concealable ? 'border-cpr-teal' : 'border-cpr-red'">
                        <span>Concealable</span>
                        <span :class="item.system.concealable.concealable ? 'text-cpr-teal' : 'text-cpr-red'">
                            {{ (item.system.concealable.concealable ? "Yes" : "No") }}
                        </span>
                    </div>
                    <div v-if="item.system.source"
                         class="bg-cpr-black-deep/50 p-1 px-2 border-l-2 border-cpr-border-muted flex justify-between items-center">
                        <span>Source</span>
                        <span class="text-cpr-text-main normal-case truncate max-w-[100px] text-right">
                        {{
                                typeof item.system.source === 'object' ? item.system.source.book + ' p.' + item.system.source.page : item.system.source
                            }}
                    </span>
                    </div>
                </div>

                <div v-if="category === 'weapon' && item.system.ammoVariety"
                     class="bg-cpr-black-deep/50 p-2 border-l-2 border-cpr-border-muted flex flex-col gap-2 mt-2">
                    <div class="flex justify-between items-center">
                        <span class="text-cpr-text-dim uppercase text-[9px] font-bold">Ammo Type</span>
                        <span class="text-cpr-yellow font-bold text-[10px] normal-case max-w-[100px] text-right">{{
                                ammoName
                            }}</span>
                    </div>

                    <div v-if="ammoTotal !== null" class="flex justify-between items-center text-[10px]">
                        <span class="text-cpr-text-dim uppercase text-[9px] font-bold">In Inventory</span>
                        <span class="text-cpr-yellow font-bold font-numbers">{{ ammoTotal }}</span>
                    </div>

                    <button @click="showReloadPopup = !showReloadPopup"
                            class="w-full py-2 border border-cpr-black/50 text-cpr-teal font-black uppercase text-[10px] tracking-widest hover:bg-cpr-teal hover:text-black transition-all flex items-center justify-center gap-2">
                        <i v-icon data-lucide="rotate-cw" class="w-3 h-3"></i> Change Ammo
                    </button>

                    <div v-if="showReloadPopup" class="mt-2 space-y-2 border-t border-cpr-border-muted pt-2">
                        <div v-if="availableAmmoItems.length === 0"
                             class="text-[10px] italic text-cpr-text-dim text-center py-2">
                            No matching ammo in inventory.
                        </div>
                        <div v-else class="grid grid-cols-1 gap-1">
                            <button v-for="ammoItem in availableAmmoItems" :key="ammoItem._id"
                                    @click="reloadWithAmmo(ammoItem._id)"
                                    class="text-left px-2 py-1 bg-cpr-black border border-cpr-border hover:border-cpr-yellow text-[10px] flex justify-between items-center transition-all">
                                <span class="text-cpr-text-main truncate pr-2">{{ ammoItem.name }}</span>
                                <span class="text-cpr-yellow shrink-0 font-numbers">x{{
                                        ammoItem.system.amount || ammoItem.system.count || 0
                                    }}</span>
                            </button>
                        </div>
                        <button @click="showReloadPopup = false"
                                class="w-full py-1 mt-1 bg-cpr-black-elevated text-cpr-text-main text-[8px] uppercase font-bold tracking-tighter">
                            Change Ammo
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </div>


</template>

<script>
import {ref, computed} from 'vue';
import {vIcon} from '../../directives/icon';
import {useCharacterData} from '../../composables/useCharacterData';
import {findItemInList, formatCategoryName} from '../../utils/helpers';

export default {
    name: 'ItemRow',
    props: ['item', 'category', 'allItems', 'activeTab', 'refreshAll'],
    directives: {icon: vIcon},
    emits: ['click', 'equip-click', 'consume-click'],
    setup(props, {emit}) {
        console.log(`ItemRow setup: ${props.item.name} (${props.category})`);
        const {getImageUrl, fetchCharacterData} = useCharacterData();
        const expanded = ref(false);
        const showReloadPopup = ref(false);

        const toggle = () => {
            expanded.value = !expanded.value;
        };

        const reloadWithAmmo = async (ammoId) => {
            try {
                // The /reloadweapon expects controlState which includes selectedWeapon
                await window.axios.post("/reloadweapon", {
                    weaponId: props.item._id,
                    ammoId: ammoId // Passing ammoId as well
                });
                showReloadPopup.value = false;

                // Refresh character data to update ammo display
                const refresh = props.refreshAll || fetchCharacterData;
                refresh();
            } catch (err) {
                console.error("Error reloading weapon:", err);
            }
        };

        const availableAmmoItems = computed(() => {
            if (props.category !== 'weapon' || !props.item.system.ammoVariety || !props.allItems) return [];
            return props.allItems.filter(i =>
                i.type === 'ammo' &&
                props.item.system.ammoVariety.includes(i.system.variety)
            );
        });


        const consumeIcon = computed(() => {
            return 'syringe'; // 'syringe' is the Lucide icon for a needle/injector
        });

        const equipIcon = computed(() =>
            getEquipIconForState(props.item.system.equipped)
        );

        const equipStateLabel = computed(() => {
            switch (props.item.system.equipped) {
                case 'equipped':
                    return 'Equipped';
                case 'carried':
                    return 'Carried';
                case 'owned':
                    return 'Owned';
            }
        });

        const getEquipIconForState = (state) => {
            switch (state) {
                case 'equipped':
                    return 'hand';
                case 'carried':
                    return 'briefcase';
                case 'owned':
                    return 'circle';
                default:
                    return 'circle';
            }

        };

        const formatType = formatCategoryName;

        const injuryModifier = computed(() => {
            if (props.category !== 'criticalInjury' || !props.item.effects || !props.item.effects[0]) return '';
            const change = props.item.effects[0].changes[0];
            if (!change) return '';
            return change.value;
        });

        const findItem = (id) => findItemInList(id, props.allItems);

        const isMeleeOrUnarmed = computed(() => {
            const type = props.item.system.weaponType || '';
            return type.toLowerCase().includes('melee') || type.toLowerCase().includes('martial') || type === 'unarmed' || props.item.system.isMelee || props.category === 'melee';
        });

        const ammoTotal = computed(() => {
            if (props.category !== 'weapon' || !props.allItems) return null;
            if (isMeleeOrUnarmed.value) return null;

            if (props.item.system.installedItems && props.item.system.installedItems.list) {
                const ammoId = props.item.system.installedItems.list[0];
                if (ammoId) {
                    const ammoItem = findItem(ammoId);
                    if (ammoItem) {
                        return ammoItem.system.amount || ammoItem.system.count || 0;
                    }
                }
            }

            // Fallback to searching by weapon type if no specific ammo is installed
            const wType = props.item.system.weaponType;
            if (!wType) return props.item.system.magazine?.max || 0;

            const total = props.allItems
                .filter(i => i.type === 'ammo' && i.system.ammoType === wType)
                .reduce((sum, i) => sum + (i.system.amount || i.system.count || 0), 0);

            return total || props.item.system.magazine?.max || 0;
        });

        const ammoName = computed(() => {
            if (props.category !== 'weapon' || !props.item.system.installedItems || !props.item.system.installedItems.list) return null;
            const ammoId = props.item.system.installedItems.list[0];
            if (!ammoId) return null;
            const ammoItem = findItem(ammoId);
            return ammoItem ? ammoItem.name : null;
        });

        const armorDisplay = computed(() => {
            if (props.category !== 'armor') return null;
            const sys = props.item.system;
            const results = [];

            if (sys.isShield && sys.shieldHitPoints) {
                results.push({
                    label: 'Shield HP',
                    value: `${sys.shieldHitPoints.value} / ${sys.shieldHitPoints.max}`,
                    icon: 'shield'
                });
            }

            if (sys['armor-head'] && sys['armor-head'].sp > 0) {
                const head = sys['armor-head'];
                results.push({
                    label: 'Head',
                    value: `${head.sp - head.ablation} / ${head.sp}`,
                    icon: 'user'
                });
            }

            if (sys['armor-body'] && sys['armor-body'].sp > 0) {
                const body = sys['armor-body'];
                results.push({
                    label: 'Body',
                    value: `${body.sp - body.ablation} / ${body.sp}`,
                    icon: 'user'
                });
            }

            return results.length > 0 ? results : null;
        });

        const statusLine = computed(() => {
            let percent = null;
            const sys = props.item.system;

            if (props.category === 'weapon' && sys.magazine && sys.magazine.max > 0 && !isMeleeOrUnarmed.value) {
                percent = (sys.magazine.value / sys.magazine.max) * 100;
            } else if (props.category === 'armor') {
                if (sys.isShield && sys.shieldHitPoints && sys.shieldHitPoints.max > 0) {
                    percent = (sys.shieldHitPoints.value / sys.shieldHitPoints.max) * 100;
                } else {
                    let totalSp = 0;
                    let currentSp = 0;
                    let count = 0;

                    if (sys['armor-head'] && sys['armor-head'].sp > 0) {
                        totalSp += sys['armor-head'].sp;
                        currentSp += (sys['armor-head'].sp - sys['armor-head'].ablation);
                        count++;
                    }
                    if (sys['armor-body'] && sys['armor-body'].sp > 0) {
                        totalSp += sys['armor-body'].sp;
                        currentSp += (sys['armor-body'].sp - sys['armor-body'].ablation);
                        count++;
                    }

                    if (totalSp > 0) {
                        percent = (currentSp / totalSp) * 100;
                    }
                }
            }

            if (percent === null) return null;
            percent = Math.min(100, Math.max(0, percent));

            let color = 'bg-green-500';
            if (percent < 25) color = 'bg-cpr-red';
            else if (percent < 60) color = 'bg-amber-500';

            return {
                width: `${percent}%`,
                color: color
            };
        });

        const categoryColor = computed(() => {
            switch (props.category) {
                case 'weapon':
                    return 'cpr-red';
                case 'armor':
                    return 'cpr-teal-dark';
                case 'ammo':
                    return 'cpr-yellow-dark';
                case 'drug':
                    return 'cpr-yellow';
                case 'gear':
                    return 'cpr-border-muted';
                case 'cyberware':
                    return 'cpr-teal';
                case 'criticalInjury':
                    return 'cpr-red';
                default:
                    return 'cpr-red';
            }
        });

        return {
            expanded,
            toggle,
            formatType,
            findItem,
            ammoName,
            ammoTotal,
            isMeleeOrUnarmed,
            armorDisplay,
            injuryModifier,
            statusLine,
            getImageUrl,
            showReloadPopup,
            consumeIcon,
            reloadWithAmmo,
            availableAmmoItems,
            equipIcon,
            equipStateLabel,
            getEquipIconForState,
            categoryColor
        };
    }
};
</script>
