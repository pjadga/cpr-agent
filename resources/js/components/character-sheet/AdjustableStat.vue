<template>
    <div class="cpr-panel bg-[#151515] cursor-pointer p-2 flex justify-between items-center"
         @click="$emit('sendStatRequest', label)">
        <button @click.stop="$emit('update:current', current > 0 ? current - 1 : current); $emit('updateStat', label, current > 0 ? current - 1 : current);"
                class="w-8 h-8 bg-gray-800 hover:bg-red-900 text-white font-bold rounded-sm cursor-pointer">-
        </button>
        <div class="flex flex-col items-center">
            <span class="text-sm font-bold text-gray-400 uppercase tracking-widest">{{ label }}</span>
            <span class="text-xl font-mono font-black text-white">
                {{ current }}
                <span class="text-gray-600 text-sm">/ {{ max }}</span>
            </span>
        </div>
        <button @click.stop="$emit('update:current', (current < max ? current + 1 : current)); $emit('updateStat', label, current < max ? current + 1 : current)"
                class="w-8 h-8 bg-gray-800 hover:bg-green-900 text-white font-bold rounded-sm cursor-pointer">+
        </button>
    </div>
</template>

<script>
export default {
    name: 'AdjustableStat',
    props: ['label', 'current', 'max'],
    emits: ['update:current', 'updateStat', 'sendStatRequest']
};
</script>
