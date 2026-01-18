<template>
    <div v-if="activeTab === 'gear'" key="gear" class="space-y-6 absolute w-full top-0 left-0 p-3 pb-20">

        <div>
            <h3 @click="$emit('toggle-category', 'cyberware')" class="text-sm font-bold text-white uppercase mb-2 flex items-center justify-between cursor-pointer group hover:bg-cyan-950/20 p-1 transition-colors">
                <div class="flex items-center gap-2">
                    <i v-icon data-lucide="zap" class="w-4 h-4 text-blue-500 group-hover:text-cyan-400"></i> <span class="group-hover:text-cyan-400 transition-colors">Cyberware</span>
                </div>
                <div class="transition-transform duration-200" :class="{ 'rotate-180': collapsedCategories['cyberware'] }">
                    <i v-icon data-lucide="chevron-down"
                       class="w-4 h-4 text-blue-500 group-hover:text-cyan-500"></i>
                </div>
            </h3>
            <transition name="fade-from-black">
                <div v-show="!collapsedCategories['cyberware']" class="space-y-2">
                    <div v-for="(group, type) in groupedCyberware" :key="type">
                        <h4 class="text-[10px] font-bold text-blue-400 uppercase mb-2 mt-4 tracking-widest border-b border-blue-900/30 pb-1">
                            {{ type }}
                        </h4>
                        <div class="space-y-2">
                            <div v-for="cw in group" :key="cw._id">
                                <item-row
                                    :item="cw"
                                    category="cyberware"
                                    :all-items="charData.data.items"
                                    @click="$emit('item-click', cw)">
                                </item-row>
                                <div v-if="cw.system.installedItems && cw.system.installedItems.list && cw.system.installedItems.list.length > 0"
                                     class="ml-6 mt-1 space-y-1 border-l border-blue-900/50 pl-2">
                                    <div v-for="childId in cw.system.installedItems.list" :key="childId">
                                        <item-row v-if="charData.data.items.find(i => i._id === childId)" :item="charData.data.items.find(i => i._id === childId)" category="cyberware" :all-items="charData.data.items" @click="$emit('item-click', charData.data.items.find(i => i._id === childId))"></item-row>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </transition>
        </div>

        <div>
            <h3 @click="$emit('toggle-category', 'gear')" class="text-sm font-bold text-white uppercase mb-2 flex items-center justify-between cursor-pointer group hover:bg-cyan-950/20 p-1 transition-colors">
                <div class="flex items-center gap-2">
                    <i v-icon data-lucide="package" class="w-4 h-4 text-gray-500 group-hover:text-cyan-400"></i> <span class="group-hover:text-cyan-400 transition-colors">Gear & Equipment</span>
                </div>
                <div class="transition-transform duration-200" :class="{ 'rotate-180': collapsedCategories['gear'] }">
                    <i v-icon data-lucide="chevron-down"
                       class="w-4 h-4 text-gray-500 group-hover:text-cyan-500"></i>
                </div>
            </h3>
            <transition name="fade-from-black">
                <div v-show="!collapsedCategories['gear']" class="space-y-6">
                    <div v-for="(items, category) in groupedGear" :key="category">
                        <div v-if="items.length > 0">
                            <h4 class="text-[10px] font-bold text-gray-500 uppercase mb-2 tracking-widest border-b border-gray-800 pb-1">
                                {{ formatCategory(category) }}
                            </h4>
                            <div class="space-y-2">
                                <div v-for="item in items" :key="item._id">
                                    <item-row
                                        :item="item"
                                        :category="category"
                                        :all-items="charData.data.items"
                                        @click="$emit('item-click', item)">
                                    </item-row>
                                    <div v-if="item.system.installedItems && item.system.installedItems.list && item.system.installedItems.list.length > 0"
                                         class="ml-6 mt-1 space-y-1 border-l border-gray-800 pl-2">
                                        <div v-for="childId in item.system.installedItems.list" :key="childId">
                                            <item-row v-if="charData.data.items.find(i => i._id === childId)" :item="charData.data.items.find(i => i._id === childId)" :category="charData.data.items.find(i => i._id === childId).type" :all-items="charData.data.items" @click="$emit('item-click', charData.data.items.find(i => i._id === childId))"></item-row>
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
    props: ['activeTab', 'collapsedCategories', 'groupedCyberware', 'groupedGear', 'charData'],
    components: { ItemRow },
    directives: { icon: vIcon },
    emits: ['toggle-category', 'item-click'],
    setup() {
        return { formatCategory: formatCategoryName };
    }
}
</script>
