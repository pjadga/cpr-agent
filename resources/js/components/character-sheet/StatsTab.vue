<template>
    <div v-if="activeTab === 'stats'" key="stats" class="space-y-5 absolute w-full top-0 left-0 p-3 pb-[calc(5rem+env(safe-area-inset-bottom))]">

        <div class="grid grid-cols-2 gap-3">
            <resource-counter label="HIT POINTS"
                              :current="charData.data.system.derivedStats.hp.value"
                              :max="charData.data.system.derivedStats.hp.max"
                              color="text-cpr-red"
                              @edit="$emit('open-resource', 'HIT POINTS', charData.data.system.derivedStats.hp.value, charData.data.system.derivedStats.hp.max, 'text-cpr-red')">
            </resource-counter>
            <resource-counter label="HUMANITY"
                              :current="charData.data.system.derivedStats.humanity.value"
                              :max="charData.data.system.derivedStats.humanity.max"
                              color="text-cpr-teal"
                              @edit="$emit('open-resource', 'HUMANITY', charData.data.system.derivedStats.humanity.value, charData.data.system.derivedStats.humanity.max, 'text-cpr-teal')">
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

        <div class="cpr-box border-cpr-red p-3 flex justify-between divide-x divide-gray-800">
            <div class="px-2 text-center w-1/4">
                <div class="text-[10px] text-cpr-text-dim uppercase font-bold">Eurobucks</div>
                <div class="text-cpr-yellow font-bold"><span class="font-numbers">{{
                        charData.data.system.wealth.value
                    }}</span>eb
                </div>
            </div>
            <div class="px-2 text-center w-1/4">
                <div class="text-[10px] text-cpr-text-dim uppercase font-bold">IP</div>
                <div class="text-cpr-yellow font-numbers font-bold">
                    {{ charData.data.system.improvementPoints.value }}
                </div>
            </div>
            <div class="px-2 text-center w-1/4 cursor-pointer hover:bg-cpr-black-elevated transition-colors" @click="$emit('facedown')">
                <div class="text-[10px] text-cpr-text-dim uppercase font-bold">Reputation</div>
                <div class="text-cpr-yellow font-numbers font-bold">{{
                        charData.data.system.reputation.value
                    }}
                </div>
            </div>
            <div class="px-2 text-center w-1/4 cursor-pointer hover:bg-cpr-black-elevated transition-colors" @click="$emit('deathsave')">
                <div class="text-[10px] text-cpr-text-dim uppercase font-bold">Death Save</div>
                <div class="text-cpr-red font-numbers font-bold">
                    {{ charData.data.system.derivedStats.deathSave.penalty }}
                </div>
            </div>
        </div>

        <div v-if="groupedInjuries && (groupedInjuries.head.length > 0 || groupedInjuries.body.length > 0)">
            <h3 @click="$emit('toggle-category', 'criticalInjury')" class="text-sm font-bold text-cpr-yellow uppercase mb-2 flex items-center justify-between cursor-pointer group hover:bg-cpr-red-dark/20 p-1 transition-colors">
                <div class="flex items-center gap-2">
                    <i v-icon data-lucide="alert-triangle" class="w-4 h-4 text-cpr-red group-hover:text-cpr-red"></i> <span class="group-hover:text-cpr-red transition-colors">Critical Injuries</span>
                </div>
                <div class="transition-transform duration-200" :class="{ 'rotate-180': collapsedCategories['criticalInjury'] }">
                    <i v-icon data-lucide="chevron-down"
                       class="w-4 h-4 text-cpr-red group-hover:text-cpr-red"></i>
                </div>
            </h3>
            <transition name="fade-from-black">
                <div v-show="!collapsedCategories['criticalInjury']" class="space-y-4">
                    <div v-if="groupedInjuries.head.length > 0">
                        <h4 class="text-[10px] font-bold text-cpr-red uppercase mb-2 mt-2 tracking-widest border-b border-cpr-red-dark/30 pb-1">
                            Head Injuries
                        </h4>
                        <div class="space-y-2">
                            <item-row
                                v-for="injury in groupedInjuries.head"
                                :key="injury._id"
                                :item="injury"
                                category="criticalInjury"
                                :all-items="charData.data.items"
                                :active-tab="activeTab"
                                :refresh-all="refreshAll"
                                @click="$emit('item-click', injury)">
                            </item-row>
                        </div>
                    </div>
                    <div v-if="groupedInjuries.body.length > 0">
                        <h4 class="text-[10px] font-bold text-cpr-red uppercase mb-2 mt-4 tracking-widest border-b border-cpr-red-dark/30 pb-1">
                            Body Injuries
                        </h4>
                        <div class="space-y-2">
                            <item-row
                                v-for="injury in groupedInjuries.body"
                                :key="injury._id"
                                :item="injury"
                                category="criticalInjury"
                                :all-items="charData.data.items"
                                :active-tab="activeTab"
                                :refresh-all="refreshAll"
                                @click="$emit('item-click', injury)">
                            </item-row>
                        </div>
                    </div>
                </div>
            </transition>
        </div>

        <div>
            <h3 class="text-sm font-bold text-cpr-yellow uppercase mb-4 border-b border-cpr-red-dark pb-1 flex items-center gap-2">
                <i v-icon data-lucide="crosshair" class="w-4 h-4 text-cpr-red"></i> Currently Equipped
            </h3>

            <div v-for="(items, category) in groupedEquipped" :key="category">
                <div v-if="items.length > 0" class="mb-4">
                    <h4 class="text-[10px] font-bold text-cpr-text-dim uppercase mb-2 tracking-widest pl-1">{{ formatCategory(category) }}</h4>
                    <div class="space-y-2">
                        <div v-for="item in items" :key="item._id">
                            <item-row
                                :item="item"
                                :category="category"
                                :all-items="charData.data.items"
                                @click="$emit('item-click', item)"
                                @equip-click="$emit('equip-click', $event)"
                                @consume-click="$emit('consume-click', $event)">
                            </item-row>
                            <div v-if="item.system.installedItems && item.system.installedItems.list && item.system.installedItems.list.length > 0"
                                 class="ml-6 mt-1 space-y-1 border-l border-cpr-red-dark/50 pl-2">
                                <div v-for="childId in item.system.installedItems.list" :key="childId">
                                    <item-row v-if="charData.data.items.find(i => i._id === childId)" :item="charData.data.items.find(i => i._id === childId)" :category="charData.data.items.find(i => i._id === childId).type" :all-items="charData.data.items" @click="$emit('item-click', charData.data.items.find(i => i._id === childId))" @equip-click="$emit('equip-click', $event)" @consume-click="$emit('consume-click', $event)"></item-row>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="Object.values(groupedEquipped).flat().length === 0" class="text-cpr-text-dim text-sm italic p-2 text-center">
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
    props: ['activeTab', 'charData', 'stats', 'luck', 'emp', 'groupedEquipped', 'groupedInjuries', 'collapsedCategories', 'refreshAll'],
    components: { ResourceCounter, SimpleStat, AdjustableStat, ItemRow },
    directives: { icon: vIcon },
    emits: ['open-resource', 'stat-request', 'update-stat', 'facedown', 'deathsave', 'item-click', 'equip-click', 'consume-click', 'toggle-category'],
    setup() {
        return { formatCategory: formatCategoryName };
    }
}
</script>
