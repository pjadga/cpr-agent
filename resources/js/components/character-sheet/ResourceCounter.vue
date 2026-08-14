<template>
    <div class="p-2 bg-[var(--cpr-black-soft)] flex flex-col items-center cursor-pointer relative"
         @click="$emit('edit')">
        <med-stat-frame></med-stat-frame>
        <span class="relative z-10 text-[10px] font-bold text-cpr-text-dim uppercase tracking-widest mb-1">{{ label }}</span>
        <div class="relative z-10 flex items-baseline gap-1">
            <span class="text-2xl font-black font-numbers" :class="color">{{ current }}</span>
            <span class="text-xs text-cpr-text-dim font-numbers">/ {{ max }}</span>
        </div>
    </div>
</template>

<script>
import MedStatFrame from "./frames/MedStatFrame.vue";

export default {
    name: 'ResourceCounter',
    components: {MedStatFrame},
    props: ['label', 'current', 'max', 'color'],
    emits: ['edit']
};
</script>
