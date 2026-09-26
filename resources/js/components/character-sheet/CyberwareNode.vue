<template>
    <div>
        <item-row
            :item="item"
            category="cyberware"
            :all-items="allItems"
            :active-tab="activeTab"
            :refresh-all="refreshAll"
            @click="$emit('item-click', item)"
            @equip-click="$emit('equip-click', $event)"
            @consume-click="$emit('consume-click', $event)">
        </item-row>

        <!-- Mods nest arbitrarily deep (a Cyberarm holds a Cyberarm which holds
             its own mods), so the node renders itself rather than assuming two
             levels the way the flat markup used to. -->
        <div v-if="children.length"
             class="ml-6 mt-1 space-y-1 border-l border-cpr-teal-dark/50 pl-2">
            <cyberware-node
                v-for="child in children"
                :key="child._id"
                :item="child"
                :all-items="allItems"
                :active-tab="activeTab"
                :refresh-all="refreshAll"
                @item-click="$emit('item-click', $event)"
                @equip-click="$emit('equip-click', $event)"
                @consume-click="$emit('consume-click', $event)">
            </cyberware-node>
        </div>
    </div>
</template>

<script>
import {computed} from 'vue';
import ItemRow from './ItemRow.vue';
import {childrenOf} from '../../utils/cyberware';

export default {
    name: 'CyberwareNode',
    components: {ItemRow},
    props: {
        item: {type: Object, required: true},
        allItems: {type: Array, default: () => []},
        activeTab: {type: String, default: null},
        refreshAll: {type: Function, default: null},
    },
    emits: ['item-click', 'equip-click', 'consume-click'],
    setup(props) {
        const children = computed(() => childrenOf(props.item, props.allItems));
        return {children};
    }
}
</script>
