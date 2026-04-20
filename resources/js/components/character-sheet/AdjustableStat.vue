<template>
    <div class="cpr-panel bg-[var(--cpr-black-elevated)] cursor-pointer p-2 flex justify-between items-center relative"
         @click="$emit('sendStatRequest', label)">
        <button @click.stop="$emit('updateStat', label, Number(current) > 0 ? Number(current) - 1 : 0);"
                class="w-8 h-8 bg-cpr-black-elevated hover:bg-cpr-red-dark text-white font-bold rounded-sm cursor-pointer">
            -
        </button>
        <div class="flex flex-col items-center">
            <span class="text-sm font-bold text-cpr-text-muted uppercase tracking-widest">{{ label }}</span>
            <span class="text-xl font-numbers font-black text-white">
                {{ current }}
                <span class="text-cpr-text-dim text-sm">/ {{ max }}</span>
            </span>
        </div>
        <button
            @click.stop="$emit('updateStat', label, Number(current) < Number(max) ? Number(current) + 1 : Number(max))"
            class="w-8 h-8 bg-cpr-black-elevated hover:bg-green-900 text-white font-bold rounded-sm cursor-pointer">+
        </button>
        <div class="cpr-corner-square text-cpr-border"></div>
    </div>
</template>

<script>
export default {
    name: 'AdjustableStat',
    props: ['label', 'current', 'max'],
    emits: ['update:current', 'updateStat', 'sendStatRequest']
};
</script>
