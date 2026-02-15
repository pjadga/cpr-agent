<template>
    <div v-if="activeTab === 'gear'" key="gear" class="space-y-6 absolute w-full top-0 left-0 p-3 pb-20">

        <div v-if="groupedInjuries.head.length > 0 || groupedInjuries.body.length > 0">
            <h3 @click="$emit('toggle-category', 'criticalInjury')" class="text-sm font-bold text-white uppercase mb-2 flex items-center justify-between cursor-pointer group hover:bg-cpr-red-dark/20 p-1 transition-colors">
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
            <h3 @click="$emit('toggle-category', 'cyberware')" class="text-sm font-bold text-white uppercase mb-2 flex items-center justify-between cursor-pointer group hover:bg-cpr-teal-dark/20 p-1 transition-colors">
                <div class="flex items-center gap-2">
                    <i v-icon data-lucide="zap" class="w-4 h-4 text-cpr-teal group-hover:text-cpr-teal"></i> <span class="group-hover:text-cpr-teal transition-colors">Cyberware</span>
                </div>
                <div class="transition-transform duration-200" :class="{ 'rotate-180': collapsedCategories['cyberware'] }">
                    <i v-icon data-lucide="chevron-down"
                       class="w-4 h-4 text-cpr-teal group-hover:text-cpr-teal"></i>
                </div>
            </h3>
            <transition name="fade-from-black">
                <div v-show="!collapsedCategories['cyberware']" class="space-y-2">
                    <div v-for="(group, type) in groupedCyberware" :key="type">
                        <h4 class="text-[10px] font-bold text-cpr-teal uppercase mb-2 mt-4 tracking-widest border-b border-cpr-teal-dark/30 pb-1">
                            {{ type }}
                        </h4>
                        <div class="space-y-2">
                            <div v-for="cw in group" :key="cw._id">
                                <item-row
                                    :item="cw"
                                    category="cyberware"
                                    :all-items="charData.data.items"
                                    :active-tab="activeTab"
                                    :refresh-all="refreshAll"
                                    @click="$emit('item-click', cw)"
                                    @equip-click="$emit('equip-click', $event)">
                                </item-row>
                                <div v-if="cw.system.installedItems && cw.system.installedItems.list && cw.system.installedItems.list.length > 0"
                                     class="ml-6 mt-1 space-y-1 border-l border-cpr-teal-dark/50 pl-2">
                                    <div v-for="childId in cw.system.installedItems.list" :key="childId">
                                        <item-row v-if="charData.data.items.find(i => i._id === childId)" :item="charData.data.items.find(i => i._id === childId)" category="cyberware" :all-items="charData.data.items" :active-tab="activeTab" :refresh-all="refreshAll" @click="$emit('item-click', charData.data.items.find(i => i._id === childId))" @equip-click="$emit('equip-click', $event)"></item-row>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </transition>
        </div>

        <div>
            <h3 @click="$emit('toggle-category', 'gear')" class="text-sm font-bold text-white uppercase mb-2 flex items-center justify-between cursor-pointer group hover:bg-cpr-teal-dark/20 p-1 transition-colors">
                <div class="flex items-center gap-2">
                    <i v-icon data-lucide="package" class="w-4 h-4 text-cpr-text-dim group-hover:text-cpr-teal"></i> <span class="group-hover:text-cpr-teal transition-colors">Gear & Equipment</span>
                </div>
                <div class="transition-transform duration-200" :class="{ 'rotate-180': collapsedCategories['gear'] }">
                    <i v-icon data-lucide="chevron-down"
                       class="w-4 h-4 text-cpr-text-dim group-hover:text-cpr-teal"></i>
                </div>
            </h3>
            <transition name="fade-from-black">
                <div v-show="!collapsedCategories['gear']" class="space-y-6">
                    <div v-for="(items, category) in groupedGear" :key="category">
                        <div v-if="items.length > 0">
                            <h4 class="text-[10px] font-bold text-cpr-text-dim uppercase mb-2 tracking-widest border-b border-cpr-border pb-1">
                                {{ formatCategory(category) }}
                            </h4>
                            <div class="space-y-2">
                                <div v-for="item in items" :key="item._id">
                                    <item-row
                                        :item="item"
                                        :category="category"
                                        :all-items="charData.data.items"
                                        :active-tab="activeTab"
                                        :refresh-all="refreshAll"
                                        @click="$emit('item-click', item)"
                                        @equip-click="$emit('equip-click', $event)">
                                    </item-row>
                                    <div v-if="item.system.installedItems && item.system.installedItems.list && item.system.installedItems.list.length > 0"
                                         class="ml-6 mt-1 space-y-1 border-l border-cpr-border pl-2">
                                        <div v-for="childId in item.system.installedItems.list" :key="childId">
                                            <item-row v-if="charData.data.items.find(i => i._id === childId)" :item="charData.data.items.find(i => i._id === childId)" :category="charData.data.items.find(i => i._id === childId).type" :all-items="charData.data.items" :active-tab="activeTab" :refresh-all="refreshAll" @click="$emit('item-click', charData.data.items.find(i => i._id === childId))" @equip-click="$emit('equip-click', $event)"></item-row>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </transition>
        </div>
    </div>
</template>

<script>
import { vIcon } from '../../directives/icon';
import ItemRow from './ItemRow.vue';
import { formatCategoryName } from '../../utils/helpers';

export default {
    name: 'GearTab',
    props: ['activeTab', 'collapsedCategories', 'groupedCyberware', 'groupedGear', 'groupedInjuries', 'charData', 'refreshAll'],
    components: { ItemRow },
    directives: { icon: vIcon },
    emits: ['toggle-category', 'item-click', 'equip-click'],
    setup() {
        return { formatCategory: formatCategoryName };
    }
}
</script>
