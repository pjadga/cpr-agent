<template>
    <div v-if="activeTab === 'skills'" key="skills"
         class="space-y-4 absolute w-full top-0 left-0 p-3 pb-[calc(5rem+env(safe-area-inset-bottom))]">
        <div v-if="roleItem" class="flex flex-col gap-1">
            <!-- Role header row -->
            <div class="relative min-h-[3.75rem] group"
                 :style="{ '--frame-accent': 'var(--cpr-border)', '--frame-colour-change': 'var(--cpr-yellow)' }">
                <RoleRowFrame class="absolute inset-0 pointer-events-none select-none"/>
                <div class="relative z-10 flex items-stretch">
                    <div @click="$emit('role-request', roleItem.name)"
                         class="flex-grow p-3 pl-4 cursor-pointer">
                        <div class="flex justify-between items-start gap-3">
                            <div class="flex-grow">
                                <h3 class="text-cpr-yellow font-black uppercase text-base tracking-tighter">
                                    {{ roleItem.name }}</h3>
                                <p class="text-[10px] text-cpr-yellow/60 font-mono uppercase tracking-widest">
                                    {{ roleItem.system.mainRoleAbility }}</p>
                            </div>
                            <div class="flex flex-col items-end shrink-0 pr-2">
                                <span class="text-2xl font-black text-cpr-yellow leading-none font-numbers">{{
                                        roleItem.system.rank
                                    }}</span>
                                <span class="text-[8px] text-cpr-yellow/50 uppercase font-bold">Rank</span>
                            </div>
                        </div>
                    </div>
                    <div @click="$emit('toggle-role', roleItem._id)"
                         class="w-12 flex items-center justify-center cursor-pointer">
                        <i v-icon data-lucide="chevron-down"
                           class="w-5 h-5 text-cpr-yellow/50 transition-transform duration-300"
                           :class="{'rotate-180': expandedRoleItems[roleItem._id]}"></i>
                    </div>
                </div>
            </div>

            <!-- Role description dropdown -->
            <transition name="expand">
                <div v-if="expandedRoleItems[roleItem._id]" class="relative"
                     :style="{ '--frame-accent': 'var(--cpr-border)' }">
                    <DropdownInfoFrame class="absolute inset-0 pointer-events-none select-none"/>
                    <div class="relative z-10 p-4 text-[11px] text-cpr-text-main leading-relaxed cpr-description"
                         v-html="roleItem.system.description.value"></div>
                </div>
            </transition>

            <!-- Role abilities (unchanged first pass) -->
            <div v-if="roleItem.system.abilities && Object.keys(roleItem.system.abilities).length > 0"
                 class="divide-y divide-yellow-500/10 border border-cpr-yellow/20 bg-cpr-black/40">
                <div v-for="(ability, key) in roleItem.system.abilities" :key="key" class="group/ability">
                    <div class="flex items-stretch hover:bg-cpr-yellow/5 transition-colors">
                        <div @click="$emit('role-ability-request', roleItem.name, ability.name)"
                             class="flex-grow p-2 pl-4 cursor-pointer">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h4 class="text-cpr-yellow/90 font-bold uppercase text-xs group-hover/ability:text-cpr-yellow transition-colors">
                                        {{ ability.name }}</h4>
                                    <p class="text-[9px] text-cpr-text-dim font-mono">{{
                                            ability.stat || 'Special'
                                        }}</p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-lg font-black text-cpr-yellow/80 font-numbers">{{
                                            ability.rank
                                        }}</span>
                                    <div
                                        class="w-1 h-8 bg-cpr-yellow/20 group-hover/ability:bg-cpr-yellow/40 transition-colors"></div>
                                </div>
                            </div>
                        </div>
                        <div @click="$emit('toggle-role', roleItem._id + key)"
                             class="w-8 flex items-center justify-center border-l border-cpr-yellow/10 cursor-pointer hover:bg-cpr-yellow/10 transition-colors">
                            <i v-icon data-lucide="info"
                               class="w-3.5 h-3.5 text-cpr-yellow/30 group-hover/ability:text-cpr-yellow/60"></i>
                        </div>
                    </div>
                    <transition name="expand">
                        <div v-if="expandedRoleItems[roleItem._id + key]"
                             class="p-3 bg-cpr-black/40 text-[10px] text-cpr-text-muted italic border-t border-cpr-yellow/5">
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
                class="flex items-center justify-between mb-2 mt-4 bg-cpr-black-deep py-1 px-2 border-l-2 border-cpr-red cursor-pointer hover:bg-cpr-teal-dark/30 transition-colors group">
                <span class="text-cpr-yellow font-bold text-sm uppercase group-hover:text-cpr-teal transition-colors">{{
                        formatCategory(categoryKey)
                    }}</span>
                <div class="transition-transform duration-200"
                     :class="{ 'rotate-180': collapsedCategories[categoryKey] }">
                    <i v-icon data-lucide="chevron-down"
                       class="w-4 h-4 text-cpr-red group-hover:text-cpr-teal"></i>
                </div>
            </div>
            <div v-show="!collapsedCategories[categoryKey]" class="flex flex-col gap-2">
                <div v-for="(skill, index) in group" :key="skill._id" class="flex flex-col">
                    <!-- Skill row -->
                    <div class="relative flex items-stretch min-h-[3.25rem] group"
                         :style="{ '--frame-accent': 'var(--cpr-border)', '--frame-colour-change': 'var(--cpr-red)' }">
                        <SkillRowFrame class="absolute inset-0 pointer-events-none select-none"/>
                        <div
                            class="relative z-10 flex-1 flex justify-between items-center p-2 pl-4 cursor-pointer gap-3"
                            @click="$emit('skill-request', skill.name)">
                            <div class="flex items-center gap-3 flex-1">
                                <div class="flex flex-col">
                                    <div class="flex items-baseline gap-2">
                                        <span
                                            class="text-sm font-medium text-cpr-text-main group-hover:text-cpr-teal transition-colors">{{
                                                skill.name
                                            }}</span>
                                        <span class="text-[10px] text-cpr-red font-black uppercase tracking-wider">({{
                                                skill.system.stat
                                            }})</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button
                                    @click.stop="$emit('toggle-skill', skill._id)"
                                    class="p-1 hover:text-cpr-teal transition-colors"
                                    v-if="skillsDescriptions[skill.name]">
                                    <i v-icon data-lucide="chevron-down"
                                       class="w-4 h-4 text-cpr-text-dim transition-transform duration-300"
                                       :class="{ 'rotate-180': expandedSkills[skill._id] }"></i>
                                </button>
                                <span class="text-[9px] mr-4 text-cpr-text-dim uppercase">Lvl <span class="font-numbers">{{
                                        skill.system.level
                                    }}</span></span>
                                <div
                                    class="w-8 h-8 flex items-center justify-center mt-[2px] text-cpr-black font-bold font-black font-numbers text-m">
                                    {{ calculateBase(skill) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Skill description dropdown -->
                    <transition name="expand">
                        <div v-if="expandedSkills[skill._id] && skillsDescriptions[skill.name]" class="relative mt-1"
                             :style="{ '--frame-accent': 'var(--cpr-border)' }">
                            <DropdownInfoFrame class="absolute inset-0 pointer-events-none select-none"/>
                            <div class="relative z-10 px-4 py-3 text-[11px] text-cpr-text-muted">
                                {{ skillsDescriptions[skill.name].text }}
                            </div>
                        </div>
                    </transition>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import {vIcon} from '../../directives/icon';
import {formatCategoryName} from '../../utils/helpers';
import RoleRowFrame from './frames/RoleRowFrame.vue';
import SkillRowFrame from './frames/SkillRowFrame.vue';
import DropdownInfoFrame from './frames/DropdownInfoFrame.vue';

export default {
    name: 'SkillsTab',
    components: {RoleRowFrame, SkillRowFrame, DropdownInfoFrame},
    props: ['activeTab', 'roleItem', 'expandedRoleItems', 'expandedSkills', 'groupedSkills', 'collapsedCategories', 'calculateBase', 'skillsDescriptions'],
    directives: {icon: vIcon},
    emits: ['role-request', 'role-ability-request', 'toggle-role', 'toggle-skill', 'toggle-category', 'skill-request'],
    setup() {
        return {formatCategory: formatCategoryName};
    }
}
</script>

<style @cspNonce scoped>
.expand-enter-active,
.expand-leave-active {
    transition: all 0.3s ease-in-out;
    max-height: 200px;
    overflow: hidden;
}

.expand-enter-from,
.expand-leave-to {
    max-height: 0;
    opacity: 0;
    padding-top: 0;
    padding-bottom: 0;
}
</style>
