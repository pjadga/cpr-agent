<template>
    <div class="bg-[var(--cpr-black-soft)] cursor-pointer p-2 flex justify-between items-center relative"
         @click="$emit('sendStatRequest', label)">
        <med-stat-frame></med-stat-frame>
        <button @click.stop="$emit('updateStat', label, Number(current) > 0 ? Number(current) - 1 : 0);"
                class="relative z-10 w-8 h-8 bg-cpr-black-elevated hover:bg-cpr-red-dark text-cpr-yellow font-bold rounded-sm cursor-pointer">
            -
        </button>
        <div class="relative z-10 flex flex-col items-center">
            <span class="text-sm font-bold text-cpr-text-muted uppercase tracking-widest">{{ label }}</span>
            <span class="text-xl font-numbers font-black text-cpr-yellow">
                {{ current }}
                <span class="text-cpr-text-dim text-sm">/ {{ max }}</span>
            </span>
        </div>
        <button
            @click.stop="$emit('updateStat', label, Number(current) < Number(max) ? Number(current) + 1 : Number(max))"
            class="relative z-10 w-8 h-8 bg-cpr-black-elevated hover:bg-green-900 text-cpr-yellow font-bold rounded-sm cursor-pointer">
            +
        </button>
    </div>
</template>

<script>
import MedStatFrame from "./frames/MedStatFrame.vue";

export default {
    name: 'AdjustableStat',
    components: {MedStatFrame},
    props: ['label', 'current', 'max'],
    emits: ['update:current', 'updateStat', 'sendStatRequest']
};
</script>
