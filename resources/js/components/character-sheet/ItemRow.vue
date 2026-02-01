<template>
    <div class="cpr-panel bg-[var(--cpr-black-soft)] flex flex-col transition-colors border-l-2 relative group overflow-hidden"
         :class="{
            'border-red-600': category === 'weapon',
            'border-blue-600': category === 'armor',
            'border-yellow-600': category === 'ammo',
            'border-gray-600': category === 'gear',
            'border-blue-400': category === 'cyberware'
         }">

        <div v-if="statusLine" class="absolute top-0 left-0 h-[3px] transition-all duration-500 z-10"
             :class="statusLine.color"
             :style="{ width: statusLine.width }">
        </div>

        <div class="flex items-stretch min-h-[3.5rem]">
            <div class="flex items-center pl-3 py-2 shrink-0">
                <img :src="getImageUrl(item.img)" class="w-10 h-10 object-cover border border-gray-700 bg-black/50" />
            </div>
            <div class="flex-1 p-2 flex flex-col justify-center cursor-default transition-colors" @click="$emit('click', item)">
                <div class="flex justify-between items-start pr-2">
                    <div class="flex flex-col">
                    <span class="text-sm font-bold text-gray-200 group-hover:text-cyan-400 transition-colors">
                        {{ item.name }}
                    </span>

                        <div class="flex flex-wrap gap-2 mt-1">
                            <template v-if="category === 'armor' && armorDisplay">
                                <div v-for="stat in armorDisplay" :key="stat.label" class="flex items-center gap-1 mr-2">
                                    <i v-icon :data-lucide="stat.icon" class="w-3 h-3 text-blue-500"></i>
                                    <span class="text-[10px] font-mono text-gray-400">
                                        <span class="text-[8px] uppercase text-gray-600 mr-0.5">{{ stat.label }}:</span>
                                        {{ stat.value }}
                                    </span>
                                </div>
                            </template>

                            <div v-if="category === 'weapon'" class="flex items-center gap-1">
                                <i v-icon data-lucide="logs" class="w-3 h-3 text-gray-500" v-if="!isMeleeOrUnarmed"></i>
                                <span class="text-[10px] font-mono text-gray-400">
                                    <template v-if="!isMeleeOrUnarmed && item.system.magazine">
                                        {{ item.system.magazine.value }} <span class="text-gray-600">/</span> {{ ammoTotal }}
                                        <span v-if="ammoName" class="ml-1 text-yellow-600">[{{ ammoName }}]</span>
                                    </template>
                                    <span v-if="item.system.damage" :class="{'ml-2': !isMeleeOrUnarmed}" class="text-red-400 font-bold">{{ item.system.damage }}</span>
                                    <span v-if="item.system.rof > 0" class="ml-2 text-orange-400">ROF:{{ item.system.rof }}</span>
                                </span>
                            </div>

                            <div v-else-if="category === 'ammo'" class="flex items-center gap-1">
                                <i v-icon data-lucide="hash" class="w-3 h-3 text-yellow-500"></i>
                                <span class="text-lg font-black font-mono text-yellow-500">
                                    {{ item.system.amount || item.system.count || 0 }}
                                </span>
                            </div>

                            <div v-else-if="(item.system.amount || item.system.count) && category !== 'armor'" class="flex items-center gap-1">
                                <i v-icon data-lucide="hash" class="w-3 h-3 text-gray-500"></i>
                                <span class="text-[10px] font-mono text-gray-400">
                                    x{{ item.system.amount || item.system.count }}
                                </span>
                            </div>

                            <div v-if="category === 'cyberware'" class="flex items-center gap-1">
                                <i v-icon data-lucide="cpu" class="w-3 h-3 text-blue-500"></i>
                                <span class="text-[10px] font-mono text-blue-400">
                                {{ item.system.installedItems.usedSlots }} / {{ item.system.installedItems.slots }}
                            </span>
                            </div>

                            <span v-if="item.system.installLocation" class="text-[9px] text-gray-500 uppercase">
                            [{{ item.system.installLocation }}]
                        </span>
                        </div>
                    </div>
                </div>
            </div>

            <button
                @click.stop="toggle"
                class="w-12 shrink-0 flex items-center justify-center bg-gray-900/30 border-l border-white/5 active:bg-cyan-900/20 transition-colors hover:bg-cyan-900/40 cursor-pointer">
                <div class="transition-transform duration-300 flex items-center justify-center"
                     :class="{ 'rotate-180': expanded }">
                    <i v-icon data-lucide="chevron-down"
                       class="w-5 h-5 text-gray-500 group-hover:text-cyan-500">
                    </i>
                </div>
            </button>
        </div>

        <transition name="expand">
            <div v-if="expanded" class="border-t border-white/5 bg-black/40 p-3 shadow-inner">
                <div v-if="item.system.description && item.system.description.value"
                     class="text-gray-300 text-xs leading-relaxed font-sans cpr-description mb-3"
                     v-html="item.system.description.value">
                </div>
                <div v-else class="text-gray-600 text-xs italic mb-3">No description available.</div>

                <div class="grid grid-cols-2 gap-2 text-[10px] uppercase font-bold text-gray-500">
                    <div v-if="item.system.price" class="bg-gray-900/50 p-1 px-2 border-l-2 border-yellow-600 flex justify-between items-center">
                        <span>Cost</span>
                        <span class="text-yellow-600 font-mono">
                            {{ item.system.price.market }}eb
                        </span>
                    </div>
                    <div v-if="item.system.concealable" class="bg-gray-900/50 p-1 px-2 border-l-2 flex justify-between items-center"
                         :class="item.system.concealable.concealable ? 'border-green-600' : 'border-red-600'">
                        <span>Concealable</span>
                        <span :class="item.system.concealable.concealable ? 'text-green-500' : 'text-red-500'">
                            {{ (item.system.concealable.concealable ? "Yes" : "No" ) }}
                        </span>
                    </div>
                    <div v-if="item.system.source" class="bg-gray-900/50 p-1 px-2 border-l-2 border-gray-600 flex justify-between items-center">
                        <span>Source</span>
                        <span class="text-gray-300 normal-case truncate max-w-[100px] text-right">
                        {{ typeof item.system.source === 'object' ? item.system.source.book + ' p.' + item.system.source.page : item.system.source }}
                    </span>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<script>
import { ref, computed } from 'vue';
import { vIcon } from '../../directives/icon';
import { useCharacterData } from '../../composables/useCharacterData';
import { findItemInList, formatCategoryName } from '../../utils/helpers';

export default {
    name: 'ItemRow',
    props: ['item', 'category', 'allItems'],
    directives: { icon: vIcon },
    emits: ['click'],
    setup(props) {
        const { getImageUrl } = useCharacterData();
        const expanded = ref(false);
        const toggle = () => {
            expanded.value = !expanded.value;
        };

        const formatType = formatCategoryName;

        const findItem = (id) => findItemInList(id, props.allItems);

        const isMeleeOrUnarmed = computed(() => {
            const type = props.item.system.weaponType || '';
            return type.toLowerCase().includes('melee') || type.toLowerCase().includes('martial') || type === 'unarmed' || props.item.system.isMelee || props.category === 'melee';
        });

        const ammoTotal = computed(() => {
            if (props.category !== 'weapon' || !props.allItems) return null;
            if (isMeleeOrUnarmed.value) return null;

            // First, try to find the installed ammo
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
            if (percent < 25) color = 'bg-red-500';
            else if (percent < 60) color = 'bg-amber-500';

            return {
                width: `${percent}%`,
                color: color
            };
        });

        return { expanded, toggle, formatType, findItem, ammoName, ammoTotal, isMeleeOrUnarmed, armorDisplay, statusLine, getImageUrl };
    }
};
</script>
