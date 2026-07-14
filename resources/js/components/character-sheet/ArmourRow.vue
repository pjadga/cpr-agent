<template>
    <div
        class="relative flex flex-col group min-h-[3.5rem]"
        :style="{ '--frame-accent': 'var(--cpr-border)' }">

        <ArmourRowFrame class="absolute inset-0 pointer-events-none select-none"/>

        <div v-if="statusLine" class="absolute top-0 left-0 h-[6px] transition-all duration-500 z-10"
             :class="statusLine.color"
             :style="{ width: statusLine.width }">
        </div>

        <div class="relative z-10 flex items-stretch min-h-[3.5rem]">
            <div class="flex-1 p-2 px-4 flex flex-col justify-center cursor-default transition-colors"
                 @click="console.log('Row clicked:', item.name); $emit('click', item)">
                <div class="flex justify-between items-center mt-[6px] ">
                    <div class="flex flex-col">
                    <span
                        class="text-sm text-cpr-text-main group-hover:text-cpr-teal transition-colors uppercase tracking-wider">
                        {{ item.name }}
                    </span
                    >
                    </div>

                    <div class="flex items-center gap-4 ">
                        <template v-if="category === 'armor' && armorDisplay">
                            <div v-for="stat in armorDisplay" :key="stat.label"
                                 class="flex items-center gap-2">
                                <div class="flex flex-col items-end">
                                    <div class="flex items-baseline gap-1">
                                        <span class="font-numbers font-bold text-2xl text-cpr-teal leading-none">{{
                                                stat.current
                                            }}</span>
                                        <span class="text-cpr-text-dim font-numbers text-sm">/</span>
                                        <span class="text-cpr-text-dim font-numbers text-sm">{{ stat.max }}</span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import {computed, ref} from 'vue';
import {vIcon} from '../../directives/icon';
import {useCharacterData} from '../../composables/useCharacterData';
import {findItemInList, formatCategoryName} from '../../utils/helpers';
import ArmourRowFrame from './frames/ArmourRowFrame.vue';

export default {
    name: 'ArmourRow',
    components: {ArmourRowFrame},
    props: ['item', 'category', 'allItems', 'activeTab', 'refreshAll'],
    directives: {icon: vIcon},
    emits: ['click', 'equip-click', 'consume-click'],
    setup(props, {emit}) {
        console.log(`ArmourRow setup: ${props.item.name} (${props.category})`);
        const {getImageUrl, fetchCharacterData, charData, isPlayer} = useCharacterData();
        const expanded = ref(false);
        const showReloadPopup = ref(false);

        const toggle = () => {
            expanded.value = !expanded.value;
        };

        const reloadWithAmmo = async (ammoId) => {
            try {
                // The /reloadweapon expects controlState which includes selectedWeapon
                await window.axios.post("/api/reloadweapon", {
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

        const equipIcon = computed(() => {
            if (!isPlayer.value) return 'hand';
            return getEquipIconForState(props.item.system.equipped);
        });

        const equipStateLabel = computed(() => {
            if (!isPlayer.value) return 'Equipped';
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
                    label: 'Shield',
                    current: sys.shieldHitPoints.value,
                    max: sys.shieldHitPoints.max,
                    icon: 'shield'
                });
            }

            if (sys.isHeadLocation && sys['headLocation'] && sys['headLocation'].sp > 0) {
                const head = sys['headLocation'];
                results.push({
                    label: 'Head',
                    current: head.sp - head.ablation,
                    max: head.sp,
                    icon: 'user'
                });
            }

            if (sys.isBodyLocation && sys['bodyLocation'] && sys['bodyLocation'].sp > 0) {
                const body = sys['bodyLocation'];
                results.push({
                    label: 'Body',
                    current: body.sp - body.ablation,
                    max: body.sp,
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

                    if (sys['headLocation'] && sys['headLocation'].sp > 0) {
                        totalSp += sys['headLocation'].sp;
                        currentSp += (sys['headLocation'].sp - sys['headLocation'].ablation);
                        count++;
                    }
                    if (sys['bodyLocation'] && sys['bodyLocation'].sp > 0) {
                        totalSp += sys['bodyLocation'].sp;
                        currentSp += (sys['bodyLocation'].sp - sys['bodyLocation'].ablation);
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
            categoryColor,
            isPlayer
        };
    }
};
</script>
