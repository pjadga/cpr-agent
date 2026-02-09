<template>
    <div @click="$emit('click')"
         class="cpr-panel bg-[var(--cpr-black-elevated)] hover:bg-cpr-teal-dark/40 hover:border-cpr-teal/50 p-2 flex flex-col items-center justify-center cpr-stat-box cursor-pointer transition-all group">
        <span class="text-2xl font-black text-white leading-none group-hover:text-cpr-teal transition-colors">{{ value }}</span>
        <span class="text-[11px] text-cpr-red font-black uppercase mt-1 group-hover:text-cpr-teal transition-colors">{{ label }}</span>
    </div>
</template>

<script>
export default {
    name: 'SimpleStat',
    props: ['label', 'value'],
    emits: ['click']
};
</script>
