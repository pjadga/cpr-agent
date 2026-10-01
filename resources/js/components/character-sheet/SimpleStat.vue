<template>
    <div @click="$emit('click')"
         class="h-25 hover:bg-cpr-teal-dark/40 hover:border-cpr-teal/50 hover:[--frame-colour-change:var(--cpr-teal)] p-2 flex flex-col items-center justify-center  cursor-pointer transition-all group relative">
        <simple-stat-frame></simple-stat-frame>
        <span
            :class="modified ? (value < base ? 'text-cpr-red' : 'text-cpr-lime') : 'text-cpr-yellow'"
            class="text-2xl font-black leading-none group-hover:text-cpr-teal transition-colors z-0 font-numbers">{{
                value
            }}</span>
        <span class="text-[11px] text-cpr-red font-black uppercase mt-1 group-hover:text-cpr-teal z-0 transition-colors">{{
                label
            }}</span>
        <!-- Shown when an active effect (injury, drug, cyberware) moves the stat. -->
        <span v-if="modified" class="text-[9px] text-cpr-text-dim font-numbers z-0">base {{ base }}</span>

    </div>
</template>

<script>
import SimpleStatFrame from "./frames/SimpleStatFrame.vue";

export default {
    name: 'SimpleStat',
    components: {SimpleStatFrame},
    props: ['label', 'value', 'base'],
    emits: ['click'],
    computed: {
        modified() {
            return this.base !== undefined && this.base !== null && this.value !== this.base;
        }
    }
};
</script>

<style>
.stat-frame {
    filter: drop-shadow(3px 3px 0px #ff00001f) drop-shadow(6px 6px 0px #310000);
    transition: 0.2s
}
.stat-frame:hover {
    filter: drop-shadow(0px 0px 0px #ff00001f) drop-shadow(0px 0px 0px #310000);
    transition: 0.2s
}
</style>
