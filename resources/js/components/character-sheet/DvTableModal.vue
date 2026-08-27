<template>
    <transition name="fade">
        <div v-if="show"
             class="fixed inset-0 z-[130] flex items-center justify-center p-4 bg-cpr-black/80 backdrop-blur-md"
             @click.self="$emit('close')">
            <div class="w-full max-w-sm bg-cpr-black border-2 border-cpr-red shadow-[0_0_40px_rgba(220,38,38,0.35)] flex flex-col max-h-[85vh]">
                <!-- Header -->
                <div class="flex items-center justify-between p-3 border-b border-cpr-red-dark bg-cpr-black-deep/40 shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="text-[11px] font-black uppercase tracking-[0.2em] text-cpr-red">Range DV</span>
                    </div>
                    <button @click="$emit('close')" class="p-1 text-cpr-text-muted hover:text-cpr-yellow transition-colors">
                        <i v-icon data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>

                <div class="p-4 overflow-y-auto">
                    <div class="text-[10px] text-cpr-text-dim uppercase font-bold tracking-widest mb-3">
                        {{ weaponName }} <span class="text-cpr-yellow-dark">/ {{ table.category }}</span>
                    </div>

                    <!-- Melee: single opposed-roll note -->
                    <div v-if="table.melee"
                         class="border border-cpr-red-dark/40 p-4 text-center">
                        <div class="text-3xl font-black text-cpr-yellow font-numbers leading-none mb-1">VS</div>
                        <div class="text-[10px] text-cpr-text-dim uppercase tracking-widest">Opposed roll (no range table)</div>
                    </div>

                    <!-- Ranged: DV by range band -->
                    <table v-else class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[9px] text-cpr-text-dim uppercase tracking-widest border-b border-cpr-red-dark/40">
                                <th class="py-1.5 font-bold">Range</th>
                                <th class="py-1.5 font-bold text-right">DV</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, i) in table.rows" :key="i"
                                class="border-b border-cpr-border/20"
                                :class="i === activeIndex ? 'bg-cpr-red/15' : ''">
                                <td class="py-2 font-mono text-xs"
                                    :class="i === activeIndex ? 'text-cpr-yellow font-bold' : 'text-cpr-text-main'">
                                    <span v-if="i === activeIndex" class="text-cpr-red mr-1">&#9656;</span>{{ row.range }}
                                </td>
                                <td class="py-2 font-numbers text-right"
                                    :class="[
                                        i === activeIndex ? 'text-cpr-yellow font-black text-lg' : 'text-cpr-text-main font-bold',
                                        row.outOfRange ? 'opacity-40' : ''
                                    ]">
                                    {{ row.outOfRange ? '--' : row.dv }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </transition>
</template>

<script>
import {vIcon} from '../../directives/icon';
import {dvRowsForWeapon, activeBracketIndex} from '../../utils/dvTable';

export default {
    name: 'DvTableModal',
    props: ['show', 'weapon', 'targetDistance'],
    directives: {icon: vIcon},
    emits: ['close'],
    computed: {
        table() {
            return dvRowsForWeapon(this.weapon);
        },
        activeIndex() {
            if (this.table.melee) return -1;
            return activeBracketIndex(this.targetDistance);
        },
        weaponName() {
            return this.weapon?.name || 'Weapon';
        }
    }
}
</script>
