<template>
    <div v-if="activeTab === 'skills'" key="skills" class="space-y-4 absolute w-full top-0 left-0 p-3 pb-20">
        <div v-if="roleItem" class="relative bg-black/60 border border-yellow-500/30 overflow-hidden clip-corner shadow-[0_0_15px_rgba(234,179,8,0.1)]">
            <div class="flex items-stretch bg-gradient-to-r from-yellow-900/40 to-transparent border-b border-yellow-500/20">
                <div @click="$emit('role-request', roleItem.name)" class="flex-grow p-3 cursor-pointer hover:bg-yellow-500/10 transition-colors group">
                    <div class="flex justify-between items-start gap-3">
                        <div class="flex-grow">
                            <h3 class="text-yellow-500 font-black uppercase text-base tracking-tighter group-hover:text-yellow-400 transition-colors">{{ roleItem.name }}</h3>
                            <p class="text-[10px] text-yellow-500/60 font-mono uppercase tracking-widest">{{ roleItem.system.mainRoleAbility }}</p>
                        </div>
                        <div class="flex flex-col items-end shrink-0">
                            <span class="text-2xl font-black text-white leading-none">{{ roleItem.system.rank }}</span>
                            <span class="text-[8px] text-yellow-500/50 uppercase font-bold">Rank</span>
                        </div>
                    </div>
                </div>
                <div @click="$emit('toggle-role', roleItem._id)" class="w-10 flex items-center justify-center border-l border-yellow-500/20 cursor-pointer hover:bg-yellow-500/10 transition-colors">
                    <i v-icon data-lucide="chevron-down" class="w-5 h-5 text-yellow-500/50 transition-transform duration-300" :class="{'rotate-180': expandedRoleItems[roleItem._id]}"></i>
                </div>
            </div>

            <transition name="expand">
                <div v-if="expandedRoleItems[roleItem._id]" class="p-3 bg-yellow-900/5 border-b border-yellow-500/10">
                    <div class="text-[11px] text-gray-300 leading-relaxed cpr-description" v-html="roleItem.system.description.value"></div>
                </div>
            </transition>

            <div v-if="roleItem.system.abilities && Object.keys(roleItem.system.abilities).length > 0" class="divide-y divide-yellow-500/10">
                <div v-for="(ability, key) in roleItem.system.abilities" :key="key" class="group/ability">
                    <div class="flex items-stretch hover:bg-yellow-500/5 transition-colors">
                        <div @click="$emit('role-ability-request', roleItem.name, ability.name)" class="flex-grow p-2 pl-4 cursor-pointer">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h4 class="text-yellow-500/90 font-bold uppercase text-xs group-hover/ability:text-yellow-400 transition-colors">{{ ability.name }}</h4>
                                    <p class="text-[9px] text-gray-500 font-mono">{{ ability.stat || 'Special' }}</p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-lg font-black text-white/80">{{ ability.rank }}</span>
                                    <div class="w-1 h-8 bg-yellow-500/20 group-hover/ability:bg-yellow-500/40 transition-colors"></div>
                                </div>
                            </div>
                        </div>
                        <div @click="$emit('toggle-role', roleItem._id + key)" class="w-8 flex items-center justify-center border-l border-yellow-500/10 cursor-pointer hover:bg-yellow-500/10 transition-colors">
                            <i v-icon data-lucide="info" class="w-3.5 h-3.5 text-yellow-500/30 group-hover/ability:text-yellow-500/60"></i>
                        </div>
                    </div>
                    <transition name="expand">
                        <div v-if="expandedRoleItems[roleItem._id + key]" class="p-3 bg-black/40 text-[10px] text-gray-400 italic border-t border-yellow-500/5">
                            <div v-if="ability.description" v-html="ability.description"></div>
                            <div v-else>No additional data available for this ability.</div>
                        </div>
                    </transition>
                </div>
            </div>
        </div>

        <div v-for="(group, categoryKey) in groupedSkills" :key="categoryKey">
            <div
                @click="$emit('toggle-category', categoryKey)"
                class="flex items-center justify-between mb-2 mt-4 bg-gray-900 py-1 px-2 border-l-2 border-red-500 cursor-pointer hover:bg-cyan-950/40 transition-colors group">
                <span class="text-white font-bold text-xs uppercase group-hover:text-cyan-400 transition-colors">{{
                        formatCategory(categoryKey)
                    }}</span>
                <div class="transition-transform duration-200" :class="{ 'rotate-180': collapsedCategories[categoryKey] }">
                    <i v-icon data-lucide="chevron-down"
                       class="w-4 h-4 text-red-500 group-hover:text-cyan-500"></i>
                </div>
            </div>
            <div v-show="!collapsedCategories[categoryKey]" class="grid grid-cols-1 gap-px bg-gray-800 border border-gray-800">
                <div v-for="(skill, index) in group" :key="skill.name"
                     @click="$emit('skill-request', skill.name)"
                     class="flex justify-between items-center bg-[#0a0a0a] p-2 hover:bg-cyan-950/20 cursor-pointer group transition-colors gap-3">
                    <div class="flex items-center gap-3 flex-1">
                        <div class="flex flex-col">
                            <div class="flex items-baseline gap-2">
                                <span class="text-sm font-medium text-gray-300 group-hover:text-cyan-400 transition-colors">{{ skill.name }}</span>
                                <span class="text-[9px] text-red-500 font-bold uppercase tracking-wider">({{
                                        skill.system.stat
                                    }})</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-[9px] text-gray-600 uppercase font-mono">Lvl {{
                                skill.system.level
                            }}</span>
                        <div
                            class="w-8 h-8 flex items-center justify-center bg-[#151515] border border-gray-700 text-red-500 font-bold font-mono text-sm rounded-sm">
                            {{ calculateBase(skill) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { vIcon } from '../../directives/icon';
import { formatCategoryName } from '../../utils/helpers';

export default {
    name: 'SkillsTab',
    props: ['activeTab', 'roleItem', 'expandedRoleItems', 'groupedSkills', 'collapsedCategories', 'calculateBase'],
    directives: { icon: vIcon },
    emits: ['role-request', 'role-ability-request', 'toggle-role', 'toggle-category', 'skill-request'],
    setup() {
        return { formatCategory: formatCategoryName };
    }
}
</script>
