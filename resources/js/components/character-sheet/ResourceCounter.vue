<template>
    <div class="cpr-panel p-2 bg-[var(--cpr-black-soft)] flex flex-col items-center cursor-pointer relative" @click="$emit('edit')">
        <span class="text-[10px] font-bold text-cpr-text-dim uppercase tracking-widest mb-1">{{ label }}</span>
        <div class="flex items-baseline gap-1">
            <span class="text-2xl font-black font-mono" :class="color">{{ current }}</span>
            <span class="text-xs text-cpr-text-dim font-mono">/ {{ max }}</span>
        </div>
        <div class="cpr-corner-square" :class="color"></div>
    </div>
</template>

<script>
export default {
    name: 'ResourceCounter',
    props: ['label', 'current', 'max', 'color'],
    emits: ['edit']
};
</script>
