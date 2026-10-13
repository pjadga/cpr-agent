<template>
    <div v-if="activeTab === 'stats'" key="stats"
         class="space-y-5 w-full p-3">

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
        <div v-if="groupedArmour.hasArmour" class="grid grid-cols-1 gap-2">
            <template v-for="location in ['head', 'body', 'shield']">
                <armour-row
                    v-for="armour in groupedArmour[location]"
                    :key="armour._id"
                    :item="armour"
                    category="armor"
                    :isPlayer=isPlayer
                    :all-items="charData.data.items"
                    :active-tab="activeTab"
                    :refresh-all="refreshAll"
                >
                </armour-row>
            </template>
        </div>
        <div class="grid grid-cols-4 gap-2">
            <simple-stat label="INT" :value="stats.int?.value" :base="stats.int?.base" @click="$emit('stat-request', 'INT')"></simple-stat>
            <simple-stat label="REF" :value="stats.ref?.value" :base="stats.ref?.base" @click="$emit('stat-request', 'REF')"></simple-stat>
            <simple-stat label="DEX" :value="stats.dex?.value" :base="stats.dex?.base" @click="$emit('stat-request', 'DEX')"></simple-stat>
            <simple-stat label="TECH" :value="stats.tech?.value" :base="stats.tech?.base" @click="$emit('stat-request', 'TECH')"></simple-stat>
            <simple-stat label="COOL" :value="stats.cool?.value" :base="stats.cool?.base" @click="$emit('stat-request', 'COOL')"></simple-stat>
            <simple-stat label="WILL" :value="stats.will?.value" :base="stats.will?.base" @click="$emit('stat-request', 'WILL')"></simple-stat>
            <simple-stat label="MOVE" :value="stats.move?.value" :base="stats.move?.base" @click="$emit('stat-request', 'MOVE')"></simple-stat>
            <simple-stat label="BODY" :value="stats.body?.value" :base="stats.body?.base" @click="$emit('stat-request', 'BODY')"></simple-stat>
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

        <div class="relative p-3">
            <MedStatFrame/>
            <div class="relative z-10 flex justify-between divide-x divide-gray-800">
            <div v-if="charData.type === 'character'"
                 class="px-2 text-center w-1/4 cursor-pointer hover:bg-cpr-black-elevated transition-colors"
                 @click="$emit('open-eurobucks')">
                <div class="text-[10px] text-cpr-text-dim uppercase font-bold">Eurobucks</div>
                <div class="text-cpr-yellow font-bold"><span class="font-numbers">{{
                        charData.data.system.wealth.value
                    }}</span>eb
                </div>
            </div>
            <div v-if="charData.type === 'character'" class="px-2 text-center w-1/4">
                <div class="text-[10px] text-cpr-text-dim uppercase font-bold">IP</div>
                <div class="text-cpr-yellow font-numbers font-bold">
                    {{ charData.data.system.improvementPoints.value }}
                </div>
            </div>
            <div class="px-2 text-center w-1/4 cursor-pointer hover:bg-cpr-black-elevated transition-colors"
                 @click="$emit('facedown')">
                <div class="text-[10px] text-cpr-text-dim uppercase font-bold">Reputation</div>
                <div class="text-cpr-yellow font-numbers font-bold">{{
                        charData.data.system.reputation.value
                    }}
                </div>
            </div>
            <div class="px-2 text-center w-1/4 cursor-pointer hover:bg-cpr-black-elevated transition-colors"
                 @click="$emit('deathsave')">
                <div class="text-[10px] text-cpr-text-dim uppercase font-bold">Death Save</div>
                <div class="text-cpr-red font-numbers font-bold">
                    {{ charData.data.system.derivedStats.deathSave.penalty }}
                </div>
            </div>
            </div>
        </div>

        <div v-if="groupedInjuries && (groupedInjuries.head.length > 0 || groupedInjuries.body.length > 0)">
            <h3 @click="$emit('toggle-category', 'criticalInjury')"
                class="flex items-center justify-between mb-2 mt-4 bg-cpr-black-deep py-1 px-2 border-l-2 border-cpr-red cursor-pointer hover:bg-cpr-red-dark/20 transition-colors group">
                <span class="text-cpr-yellow font-bold text-sm uppercase group-hover:text-cpr-red transition-colors">Critical Injuries</span>
                <div class="transition-transform duration-200"
                     :class="{ 'rotate-180': collapsedCategories['criticalInjury'] }">
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

        <!-- Read-only: every effect on the character (utils/rollEffects.js effectList).
             Toggling stays in Foundry; the Bridge pushes changes back live. -->
        <div v-if="effects && effects.length">
            <h3 @click="$emit('toggle-category', 'activeEffects')"
                class="flex items-center justify-between mb-2 mt-4 bg-cpr-black-deep py-1 px-2 border-l-2 border-cpr-red cursor-pointer hover:bg-cpr-teal-dark/30 transition-colors group">
                <span class="text-cpr-yellow font-bold text-sm uppercase group-hover:text-cpr-teal transition-colors">Active Effects</span>
                <div class="transition-transform duration-200"
                     :class="{ 'rotate-180': collapsedCategories['activeEffects'] }">
                    <i v-icon data-lucide="chevron-down"
                       class="w-4 h-4 text-cpr-red group-hover:text-cpr-teal"></i>
                </div>
            </h3>
            <div v-show="!collapsedCategories['activeEffects']" class="flex flex-col gap-2">
                <!-- Plain grey row (EffectRowFrame): the skill-row outline without the
                     value chip, with ends that stay put when a row wraps. -->
                <div v-for="(eff, idx) in effects" :key="idx"
                     class="relative flex items-stretch min-h-[3.25rem]"
                     :style="{ '--frame-accent': 'var(--cpr-border)' }">
                    <EffectRowFrame class="absolute inset-0 pointer-events-none select-none"/>
                    <div class="relative z-10 flex-1 flex items-center p-2 pl-4 pr-4"
                         :class="{ 'opacity-60': !eff.active }">
                        <div class="flex flex-col min-w-0">
                            <div class="flex items-baseline gap-2 flex-wrap">
                                <span class="text-sm font-medium text-cpr-text-main">{{ eff.source }}</span>
                                <span v-if="eff.effect && eff.effect !== eff.source"
                                      class="text-[10px] text-cpr-text-dim">{{ eff.effect }}</span>
                                <span v-if="!eff.active"
                                      class="text-[10px] text-cpr-red font-black uppercase tracking-wider">({{ eff.reason || 'off' }})</span>
                            </div>
                            <div class="flex flex-wrap gap-x-3 gap-y-0.5 mt-0.5">
                                <span v-for="(change, cIdx) in eff.changes" :key="cIdx"
                                      :class="change.value < 0 ? 'text-cpr-red' : 'text-cpr-lime'"
                                      class="text-[11px]">{{ formatChange(change) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <h3 class="flex items-center justify-between mb-4 mt-4 bg-cpr-black-deep py-1 px-2 border-l-2 border-cpr-red transition-colors group">
                <span class="text-cpr-yellow font-bold text-sm uppercase">Currently Equipped</span>
            </h3>

            <div v-for="(items, category) in groupedEquipped" :key="category">
                <div v-if="items.length > 0" class="mb-4">
                    <h4 class="text-[10px] font-bold text-cpr-text-dim uppercase mb-2 tracking-widest pl-1">
                        {{ formatCategory(category) }}</h4>
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
                            <div
                                v-if="item.system.installedItems && item.system.installedItems.list && item.system.installedItems.list.length > 0"
                                class="ml-6 mt-1 space-y-1 border-l border-cpr-red-dark/50 pl-2">
                                <div v-for="childId in item.system.installedItems.list" :key="childId">
                                    <item-row v-if="charData.data.items.find(i => i._id === childId)"
                                              :item="charData.data.items.find(i => i._id === childId)"
                                              :category="charData.data.items.find(i => i._id === childId).type"
                                              :all-items="charData.data.items"
                                              @click="$emit('item-click', charData.data.items.find(i => i._id === childId))"
                                              @equip-click="$emit('equip-click', $event)"
                                              @consume-click="$emit('consume-click', $event)"></item-row>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="Object.values(groupedEquipped).flat().length === 0"
                 class="text-cpr-text-dim text-sm italic p-2 text-center">
                No items equipped.
            </div>
        </div>
    </div>
</template>

<script>
import {vIcon} from '../../directives/icon';
import ResourceCounter from './ResourceCounter.vue';
import SimpleStat from './SimpleStat.vue';
import AdjustableStat from './AdjustableStat.vue';
import ItemRow from './ItemRow.vue';
import {formatCategoryName} from '../../utils/helpers';
import ArmourRow from "./ArmourRow.vue";
import MedStatFrame from "./frames/MedStatFrame.vue";
import EffectRowFrame from "./frames/EffectRowFrame.vue";

export default {
    name: 'StatsTab',
    props: ['activeTab', 'charData', 'stats', 'luck', 'emp', 'groupedEquipped', 'groupedInjuries', 'collapsedCategories', 'refreshAll', 'groupedArmour', 'effects'],
    components: {ResourceCounter, SimpleStat, AdjustableStat, ItemRow, ArmourRow, MedStatFrame, EffectRowFrame},
    directives: {icon: vIcon},
    emits: ['open-resource', 'stat-request', 'update-stat', 'facedown', 'deathsave', 'item-click', 'equip-click', 'consume-click', 'toggle-category', 'open-eurobucks'],
    setup() {
        // One string per change so the label and value can't lose their space: "MOVE -6"
        const formatChange = (c) => `${c.label} ${c.value > 0 ? '+' : ''}${c.value}${c.situational ? ' (situational)' : ''}`;
        return {formatCategory: formatCategoryName, formatChange};
    }
}
</script>
