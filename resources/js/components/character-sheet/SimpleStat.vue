<template>
    <div @click="$emit('click')"
         class="cpr-panel bg-[#151515] hover:bg-cyan-900/40 hover:border-cyan-500/50 p-2 flex flex-col items-center justify-center cpr-stat-box cursor-pointer transition-all group">
        <span class="text-xl font-bold text-white leading-none group-hover:text-cyan-400 transition-colors">{{ value }}</span>
        <span class="text-[10px] text-[var(--cpr-red)] font-bold uppercase mt-1 group-hover:text-cyan-500 transition-colors">{{ label }}</span>
    </div>
</template>

<script>
export default {
    name: 'SimpleStat',
    props: ['label', 'value'],
    emits: ['click']
};
</script>
