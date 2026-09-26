<template>
    <div v-if="activeTab === 'gear'" key="gear" class="space-y-6 w-full p-3">


        <div>
            <h3 class="flex items-center justify-between mb-2 mt-4 bg-cpr-black-deep py-1 px-2 border-l-2 border-cpr-red cursor-pointer hover:bg-cpr-teal-dark/20 transition-colors group">
                <span @click="$emit('toggle-category', 'cyberware')"
                      class="flex-1 text-cpr-yellow font-bold text-sm uppercase group-hover:text-cpr-teal transition-colors">Cyberware</span>
                <div class="flex items-center gap-2">
                    <!-- Empty locations are hidden by default, so this is the
                         only way to reach a slot you have not installed into yet. -->
                    <button @click.stop="$emit('toggle-cyberware-show-all')"
                            class="p-1 transition-colors"
                            :class="cyberwareShowAll ? 'text-cpr-teal' : 'text-cpr-text-dim hover:text-cpr-teal'"
                            :title="cyberwareShowAll ? 'Hide empty locations' : 'Show all locations'">
                        <i v-icon :data-lucide="cyberwareShowAll ? 'eye' : 'eye-off'" class="w-4 h-4 pointer-events-none"></i>
                    </button>
                    <div @click="$emit('toggle-category', 'cyberware')" class="transition-transform duration-200"
                         :class="{ 'rotate-180': collapsedCategories['cyberware'] }">
                        <i v-icon data-lucide="chevron-down"
                           class="w-4 h-4 text-cpr-teal group-hover:text-cpr-teal"></i>
                    </div>
                </div>
            </h3>
            <transition name="fade-from-black">
                <div v-show="!collapsedCategories['cyberware']" class="space-y-2">
                    <!-- One heading per body location. The heading is the top
                         parent, so what sits directly under it is the cyberware
                         fitted there, and anything inside that nests deeper. -->
                    <div v-for="(group, type) in groupedCyberware" :key="type">
                        <h4 class="text-[10px] font-bold text-cpr-teal uppercase mb-2 mt-4 tracking-widest border-b border-cpr-teal-dark/30 pb-1">
                            {{ type }}
                        </h4>
                        <div class="space-y-2">
                            <cyberware-node
                                v-for="cw in group"
                                :key="cw._id"
                                :item="cw"
                                :all-items="charData.data.items"
                                :active-tab="activeTab"
                                :refresh-all="refreshAll"
                                @item-click="$emit('item-click', $event)"
                                @equip-click="$emit('equip-click', $event)"
                                @consume-click="$emit('consume-click', $event)">
                            </cyberware-node>
                            <div v-if="!group.length" class="text-cpr-text-dim text-[11px] italic pl-1 pb-1">
                                Nothing installed here.
                            </div>
                        </div>
                    </div>

                    <!-- Owned but not fitted. Separate so a spare arm is never
                         mistaken for one that is actually attached. -->
                    <div v-if="uninstalledCyberware && uninstalledCyberware.length">
                        <h4 class="text-[10px] font-bold text-cpr-yellow uppercase mb-2 mt-4 tracking-widest border-b border-cpr-yellow/30 pb-1">
                            Not Installed
                        </h4>
                        <div class="space-y-2">
                            <cyberware-node
                                v-for="cw in uninstalledCyberware"
                                :key="cw._id"
                                :item="cw"
                                :all-items="charData.data.items"
                                :active-tab="activeTab"
                                :refresh-all="refreshAll"
                                @item-click="$emit('item-click', $event)"
                                @equip-click="$emit('equip-click', $event)"
                                @consume-click="$emit('consume-click', $event)">
                            </cyberware-node>
                        </div>
                    </div>

                    <div v-if="!hasCyberware"
                         class="text-cpr-text-dim text-sm italic p-2 text-center">
                        No cyberware. Use the eye icon to show all locations.
                    </div>
                </div>
            </transition>
        </div>

        <div>
            <h3 @click="$emit('toggle-category', 'gear')"
                class="flex items-center justify-between mb-2 mt-4 bg-cpr-black-deep py-1 px-2 border-l-2 border-cpr-red cursor-pointer hover:bg-cpr-teal-dark/20 transition-colors group">
                <span class="text-cpr-yellow font-bold text-sm uppercase group-hover:text-cpr-teal transition-colors">Gear & Equipment</span>
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
                                        @equip-click="$emit('equip-click', $event)"
                                        @consume-click="$emit('consume-click', $event)">
                                    </item-row>
                                    <div
                                        v-if="item.system.installedItems && item.system.installedItems.list && item.system.installedItems.list.length > 0"
                                        class="ml-6 mt-1 space-y-1 border-l border-cpr-border pl-2">
                                        <div v-for="childId in item.system.installedItems.list" :key="childId">
                                            <item-row v-if="charData.data.items.find(i => i._id === childId)"
                                                      :item="charData.data.items.find(i => i._id === childId)"
                                                      :category="charData.data.items.find(i => i._id === childId).type"
                                                      :all-items="charData.data.items" :active-tab="activeTab"
                                                      :refresh-all="refreshAll"
                                                      @click="$emit('item-click', charData.data.items.find(i => i._id === childId))"
                                                      @equip-click="$emit('equip-click', $event)"
                                                      @consume-click="$emit('consume-click', $event)"></item-row>
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
import {computed} from 'vue';
import {vIcon} from '../../directives/icon';
import ItemRow from './ItemRow.vue';
import CyberwareNode from './CyberwareNode.vue';
import {formatCategoryName} from '../../utils/helpers';

export default {
    name: 'GearTab',
    props: ['activeTab', 'collapsedCategories', 'groupedCyberware', 'uninstalledCyberware', 'cyberwareShowAll', 'groupedGear', 'charData', 'refreshAll'],
    components: {ItemRow, CyberwareNode},
    directives: {icon: vIcon},
    emits: ['toggle-category', 'toggle-cyberware-show-all', 'item-click', 'equip-click', 'consume-click'],
    setup(props) {
        const hasCyberware = computed(() =>
            Object.keys(props.groupedCyberware || {}).length > 0
            || (props.uninstalledCyberware || []).length > 0
        );

        return {formatCategory: formatCategoryName, hasCyberware};
    }
}
</script>
