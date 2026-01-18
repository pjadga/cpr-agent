<template>
    <div v-if="show" class="fixed inset-0 z-[120] flex items-center justify-center p-6 bg-black/70" @click="$emit('close')">
        <div class="w-full max-w-xs bg-[#0b0b0b] border border-gray-800 shadow-2xl" @click.stop>
            <div class="flex items-center justify-between px-3 py-2 border-b border-gray-800 bg-black/40">
                <span class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Adjust {{ resourceLabel }}</span>
                <button class="text-gray-400 hover:text-white" @click="$emit('close')"><i v-icon data-lucide="x" class="w-4 h-4"></i></button>
            </div>
            <div class="p-4 space-y-4">
                <div class="flex items-center justify-center gap-3">
                    <button class="w-10 h-10 bg-gray-900 border border-gray-700 text-white font-bold hover:bg-red-900/40" @click="$emit('step', -1)">-</button>
                    <div class="text-center">
                        <div class="text-xs text-gray-500 uppercase font-bold tracking-widest">Current</div>
                        <div class="text-4xl font-black font-mono" :class="resourceColor">{{ resourceValue }}</div>
                        <div class="text-xs text-gray-600 font-mono">/ {{ resourceMax }}</div>
                    </div>
                    <button class="w-10 h-10 bg-gray-900 border border-gray-700 text-white font-bold hover:bg-green-900/40" @click="$emit('step', 1)">+</button>
                </div>

                <div class="space-y-2">
                    <div class="text-[10px] text-gray-500 uppercase font-bold tracking-widest">Bulk adjust</div>
                    <div class="flex items-center gap-2 w-full">
                        <div class="flex shrink-0">
                            <button class="px-4 py-3 text-base border border-gray-700 rounded-sm"
                                    :class="bulkSign === '+' ? 'bg-green-900/40 text-green-300' : 'bg-gray-900 text-gray-300'"
                                    @click="$emit('update:bulkSign', '+')">+</button>
                            <button class="px-4 py-3 text-base border border-gray-700 -ml-px rounded-sm"
                                    :class="bulkSign === '-' ? 'bg-red-900/40 text-red-300' : 'bg-gray-900 text-gray-300'"
                                    @click="$emit('update:bulkSign', '-')">-</button>
                        </div>
                        <input type="number" inputmode="numeric" pattern="[0-9]*" min="0"
                               class="flex-1 min-w-0 bg-black border border-gray-800 px-3 py-2 text-white placeholder-gray-600"
                               :value="bulkAmount" @input="$emit('update:bulkAmount', $event.target.value)" placeholder="Amount"/>
                        <button class="shrink-0 px-4 py-3 bg-gray-900 border border-gray-700 text-white text-base hover:bg-gray-800"
                                @click="$emit('applyBulk')">Apply</button>
                    </div>
                </div>

                <div class="flex gap-2 pt-2">
                    <button class="flex-1 py-2 bg-red-600/10 border border-red-600 text-red-500 text-xs font-bold uppercase hover:bg-red-600 hover:text-black transition-colors" @click="$emit('close')">Cancel</button>
                    <button class="flex-1 py-2 bg-green-600/10 border border-green-600 text-green-500 text-xs font-bold uppercase hover:bg-green-600 hover:text-black transition-colors" @click="$emit('save')">Save</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { vIcon } from '../../directives/icon';

export default {
    name: 'ResourceModal',
    props: ['show', 'resourceLabel', 'resourceValue', 'resourceMax', 'resourceColor', 'bulkSign', 'bulkAmount'],
    directives: { icon: vIcon },
    emits: ['close', 'step', 'update:bulkSign', 'update:bulkAmount', 'applyBulk', 'save']
}
</script>
