<template>
    <button class="flex flex-col items-center gap-1 w-16 transition-colors cursor-pointer"
            :class="active ? 'text-red-500' : 'text-gray-600'">
        <i v-icon :data-lucide="icon" class="w-6 h-6"></i>
        <span class="text-[9px] font-bold uppercase tracking-wider">{{ label }}</span>
    </button>
</template>

<script>
import { vIcon } from '../../directives/icon';

export default {
    name: 'NavBtn',
    props: ['icon', 'label', 'active'],
    directives: { icon: vIcon }
};
</script>
