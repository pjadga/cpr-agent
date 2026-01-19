<template>
    <div v-if="activeTab === 'stats'" key="stats" class="space-y-5 absolute w-full top-0 left-0 p-3 pb-20">

        <div class="grid grid-cols-2 gap-3">
            <resource-counter label="HIT POINTS"
                              :current="charData.data.system.derivedStats.hp.value"
                              :max="charData.data.system.derivedStats.hp.max"
                              color="text-red-500"
                              @edit="$emit('open-resource', 'HIT POINTS', charData.data.system.derivedStats.hp.value, charData.data.system.derivedStats.hp.max, 'text-red-500')">
            </resource-counter>
            <resource-counter label="HUMANITY"
                              :current="charData.data.system.derivedStats.humanity.value"
                              :max="charData.data.system.derivedStats.humanity.max"
                              color="text-cyan-400"
                              @edit="$emit('open-resource', 'HUMANITY', charData.data.system.derivedStats.humanity.value, charData.data.system.derivedStats.humanity.max, 'text-cyan-400')">
            </resource-counter>
        </div>

        <div class="grid grid-cols-4 gap-2">
            <simple-stat label="INT" :value="stats.int?.value" @click="$emit('stat-request', 'INT')"></simple-stat>
            <simple-stat label="REF" :value="stats.ref?.value" @click="$emit('stat-request', 'REF')"></simple-stat>
            <simple-stat label="DEX" :value="stats.dex?.value" @click="$emit('stat-request', 'DEX')"></simple-stat>
            <simple-stat label="TECH" :value="stats.tech?.value" @click="$emit('stat-request', 'TECH')"></simple-stat>
            <simple-stat label="COOL" :value="stats.cool?.value" @click="$emit('stat-request', 'COOL')"></simple-stat>
            <simple-stat label="WILL" :value="stats.will?.value" @click="$emit('stat-request', 'WILL')"></simple-stat>
            <simple-stat label="MOVE" :value="stats.move?.value" @click="$emit('stat-request', 'MOVE')"></simple-stat>
            <simple-stat label="BODY" :value="stats.body?.value" @click="$emit('stat-request', 'BODY')"></simple-stat>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <adjustable-stat label="LUCK"
                             :current="luck.value"
                             :max="luck.max"
                             @updateStat="(label, val) => $emit('update-stat', label, val)"
                             @sendStatRequest="$emit('stat-request', 'LUCK')">
            </adjustable-stat>
            <adjustable-stat label="EMP"
                             :current="emp.value"
                             :max="emp.max"
                             @updateStat="(label, val) => $emit('update-stat', label, val)"
                             @sendStatRequest="$emit('stat-request', 'EMP')">
            </adjustable-stat>
        </div>

        <div class="bg-[#111] border border-gray-800 p-3 flex justify-between divide-x divide-gray-800">
            <div class="px-2 text-center w-1/4">
                <div class="text-[9px] text-gray-500 uppercase font-bold">Eurobucks</div>
                <div class="text-yellow-500 font-mono font-bold">{{
                        charData.data.system.wealth.value
                    }}eb
                </div>
            </div>
            <div class="px-2 text-center w-1/4">
                <div class="text-[9px] text-gray-500 uppercase font-bold">IP</div>
                <div class="text-white font-mono font-bold">
                    {{ charData.data.system.improvementPoints.value }}
                </div>
            </div>
            <div class="px-2 text-center w-1/4 cursor-pointer hover:bg-gray-800 transition-colors" @click="$emit('facedown')">
                <div class="text-[9px] text-gray-500 uppercase font-bold">Reputation</div>
                <div class="text-white font-mono font-bold">{{
                        charData.data.system.reputation.value
                    }}
                </div>
            </div>
            <div class="px-2 text-center w-1/4 cursor-pointer hover:bg-gray-800 transition-colors" @click="$emit('deathsave')">
                <div class="text-[9px] text-gray-500 uppercase font-bold">Death Save</div>
                <div class="text-red-500 font-mono font-bold">
                    {{ charData.data.system.derivedStats.deathSave.penalty }}
                </div>
            </div>
        </div>

        <div>
            <h3 class="text-sm font-bold text-white uppercase mb-4 border-b border-red-900 pb-1 flex items-center gap-2">
                <i v-icon data-lucide="crosshair" class="w-4 h-4 text-red-500"></i> Currently Equipped
            </h3>

            <div v-for="(items, category) in groupedEquipped" :key="category">
                <div v-if="items.length > 0" class="mb-4">
                    <h4 class="text-[9px] font-bold text-gray-500 uppercase mb-2 tracking-widest pl-1">{{ formatCategory(category) }}</h4>
                    <div class="space-y-2">
                        <div v-for="item in items" :key="item._id">
                            <item-row
                                :item="item"
                                :category="category"
                                :all-items="charData.data.items"
                                @click="$emit('item-click', item)">
                            </item-row>
                            <div v-if="item.system.installedItems && item.system.installedItems.list && item.system.installedItems.list.length > 0"
                                 class="ml-6 mt-1 space-y-1 border-l border-red-900/50 pl-2">
                                <div v-for="childId in item.system.installedItems.list" :key="childId">
                                    <item-row v-if="charData.data.items.find(i => i._id === childId)" :item="charData.data.items.find(i => i._id === childId)" :category="charData.data.items.find(i => i._id === childId).type" :all-items="charData.data.items" @click="$emit('item-click', charData.data.items.find(i => i._id === childId))"></item-row>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="Object.values(groupedEquipped).flat().length === 0" class="text-gray-600 text-xs italic p-2 text-center">
                No items equipped.
            </div>
        </div>
    </div>
</template>

<script>
import { vIcon } from '../../directives/icon';
import ResourceCounter from './ResourceCounter.vue';
import SimpleStat from './SimpleStat.vue';
import AdjustableStat from './AdjustableStat.vue';
import ItemRow from './ItemRow.vue';
import { formatCategoryName } from '../../utils/helpers';

export default {
    name: 'StatsTab',
    props: ['activeTab', 'charData', 'stats', 'luck', 'emp', 'groupedEquipped'],
    components: { ResourceCounter, SimpleStat, AdjustableStat, ItemRow },
    directives: { icon: vIcon },
    emits: ['open-resource', 'stat-request', 'update-stat', 'facedown', 'deathsave', 'item-click'],
    setup() {
        return { formatCategory: formatCategoryName };
    }
}
</script>
