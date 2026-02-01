<template>
    <div class="cpr-panel p-2 bg-[var(--cpr-black-soft)] flex flex-col items-center cursor-pointer" @click="$emit('edit')">
        <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">{{ label }}</span>
        <div class="flex items-baseline gap-1">
            <span class="text-2xl font-black font-mono" :class="color">{{ current }}</span>
            <span class="text-xs text-gray-600 font-mono">/ {{ max }}</span>
        </div>
    </div>
</template>

<script>
export default {
    name: 'ResourceCounter',
    props: ['label', 'current', 'max', 'color'],
    emits: ['edit']
};
</script>
