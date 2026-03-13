<template>
    <div v-if="show" class="fixed inset-0 z-[120] flex items-center justify-center p-6 bg-cpr-black/70 p-[env(safe-area-inset-top)_env(safe-area-inset-right)_env(safe-area-inset-bottom)_env(safe-area-inset-left)]" @click="$emit('close')">
        <div class="w-full max-w-xs bg-cpr-black-deep border border-cpr-border shadow-2xl" @click.stop>
            <div class="flex items-center justify-between px-3 py-2 border-b border-cpr-border bg-cpr-black/40">
                <span class="text-[10px] font-bold uppercase tracking-widest text-cpr-text-dim">Adjust {{ resourceLabel }}</span>
                <button class="text-cpr-text-muted hover:text-white" @click="$emit('close')"><i v-icon data-lucide="x" class="w-4 h-4"></i></button>
            </div>
            <div class="p-4 space-y-4">
                <div class="flex items-center justify-center gap-3">
                    <button class="w-10 h-10 bg-cpr-black-deep border border-cpr-border text-white font-bold hover:bg-cpr-red-dark/40" @click="$emit('step', -1)">-</button>
                    <div class="text-center">
                        <div class="text-xs text-cpr-text-dim uppercase font-bold tracking-widest">Current</div>
                        <div class="text-4xl font-black font-numbers" :class="resourceColor">{{ resourceValue }}</div>
                        <div class="text-xs text-cpr-text-dim font-numbers">/ {{ resourceMax }}</div>
                    </div>
                    <button class="w-10 h-10 bg-cpr-black-deep border border-cpr-border text-white font-bold hover:bg-green-900/40" @click="$emit('step', 1)">+</button>
                </div>

                <div class="space-y-2">
                    <div class="text-[10px] text-cpr-text-dim uppercase font-bold tracking-widest">Bulk adjust</div>
                    <div class="flex items-center gap-2 w-full">
                        <div class="flex shrink-0">
                            <button class="px-4 py-3 text-base border border-cpr-border rounded-sm"
                                    :class="bulkSign === '+' ? 'bg-green-900/40 text-green-300' : 'bg-cpr-black-deep text-cpr-text-main'"
                                    @click="$emit('update:bulkSign', '+')">+</button>
                            <button class="px-4 py-3 text-base border border-cpr-border -ml-px rounded-sm"
                                    :class="bulkSign === '-' ? 'bg-cpr-red-dark/40 text-cpr-red' : 'bg-cpr-black-deep text-cpr-text-main'"
                                    @click="$emit('update:bulkSign', '-')">-</button>
                        </div>
                        <input type="number" inputmode="numeric" pattern="[0-9]*" min="0"
                               class="flex-1 min-w-0 bg-cpr-black border border-cpr-border px-3 py-2 text-white placeholder-gray-600"
                               :value="bulkAmount" @input="$emit('update:bulkAmount', $event.target.value)" placeholder="Amount"/>
                        <button class="shrink-0 px-4 py-3 bg-cpr-black-deep border border-cpr-border text-white text-base hover:bg-cpr-black-elevated"
                                @click="$emit('applyBulk')">Apply</button>
                    </div>
                </div>

                <div class="flex gap-2 pt-2">
                    <button class="flex-1 py-2 bg-cpr-red/10 border border-cpr-red text-cpr-red text-xs font-bold uppercase hover:bg-cpr-red hover:text-black transition-colors" @click="$emit('close')">Cancel</button>
                    <button class="flex-1 py-2 bg-green-600/10 border border-green-600 text-cpr-lime text-xs font-bold uppercase hover:bg-green-600 hover:text-black transition-colors" @click="$emit('save')">Save</button>
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
