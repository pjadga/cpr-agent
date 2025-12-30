<template>
    <div id="app" class="min-h-screen pb-28">

        <div v-if="loading" class="flex h-screen items-center justify-center">
            <div class="text-red-500 font-mono animate-pulse text-xl">CONNECTING TO NET...</div>
        </div>

        <div v-else-if="error" class="flex h-screen items-center justify-center p-4">
            <div class="text-red-500 font-bold border border-red-500 p-4 bg-red-900/20">
                ERROR: {{ error }}
            </div>
        </div>

        <div v-else>
            <header class="p-3 bg-black/90 sticky top-0 z-50 border-b border-red-900 backdrop-blur-md">
                <div class="flex items-center gap-3">
                    <div class="relative w-12 h-12 shrink-0">
                        <div
                            class="absolute inset-0 border border-[var(--cpr-red)] bg-gray-900 flex items-center justify-center">
                            <span class="text-xl font-bold text-red-500">{{ charData.data.name[0] }}</span>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h1 class="text-2xl font-bold uppercase truncate leading-none text-white">{{
                                charData.data.name
                            }}</h1>
                        <span class="text-[10px] text-red-500 uppercase font-bold tracking-widest">{{
                                charData.data.system.roleInfo.activeRole
                            }}</span>
                    </div>
                </div>
            </header>

            <main class="p-3 max-w-md mx-auto space-y-4">

                <transition name="fade" mode="out-in">

                    <div v-if="activeTab === 'stats'" key="stats" class="space-y-5">

                        <div class="grid grid-cols-2 gap-3">
                            <resource-counter label="HIT POINTS"
                                              :current="charData.data.system.derivedStats.hp.value"
                                              :max="charData.data.system.derivedStats.hp.max"
                                              color="text-green-500">
                            </resource-counter>
                            <resource-counter label="HUMANITY"
                                              :current="charData.data.system.derivedStats.humanity.value"
                                              :max="charData.data.system.derivedStats.humanity.max"
                                              color="text-blue-500">
                            </resource-counter>
                        </div>

                        <div class="grid grid-cols-4 gap-2">
                            <simple-stat label="INT" :value="stats.int?.value"></simple-stat>
                            <simple-stat label="REF" :value="stats.ref?.value"></simple-stat>
                            <simple-stat label="DEX" :value="stats.dex?.value"></simple-stat>
                            <simple-stat label="TECH" :value="stats.tech?.value"></simple-stat>
                            <simple-stat label="COOL" :value="stats.cool?.value"></simple-stat>
                            <simple-stat label="WILL" :value="stats.will?.value"></simple-stat>
                            <simple-stat label="MOVE" :value="stats.move?.value"></simple-stat>
                            <simple-stat label="BODY" :value="stats.body?.value"></simple-stat>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <adjustable-stat label="LUCK"
                                             v-model:current="luck.value"
                                             :max="luck.max">
                            </adjustable-stat>
                            <adjustable-stat label="EMP"
                                             v-model:current="emp.value"
                                             :max="emp.max">
                            </adjustable-stat>
                        </div>

                        <div class="bg-[#111] border border-gray-800 p-3 flex justify-between divide-x divide-gray-800">
                            <div class="px-2 text-center w-1/4">
                                <div class="text-[9px] text-gray-500 uppercase font-bold">Eurobucks</div>
                                <div class="text-yellow-500 font-mono font-bold">{{
                                        charData.data.system.wealth.value
                                    }}eb
                                </div>
                            </div>
                            <div class="px-2 text-center w-1/4">
                                <div class="text-[9px] text-gray-500 uppercase font-bold">IP</div>
                                <div class="text-white font-mono font-bold">
                                    {{ charData.data.system.improvementPoints.value }}
                                </div>
                            </div>
                            <div class="px-2 text-center w-1/4">
                                <div class="text-[9px] text-gray-500 uppercase font-bold">Reputation</div>
                                <div class="text-white font-mono font-bold">{{
                                        charData.data.system.reputation.value
                                    }}
                                </div>
                            </div>
                            <div class="px-2 text-center w-1/4">
                                <div class="text-[9px] text-gray-500 uppercase font-bold">Death Save</div>
                                <div class="text-red-500 font-mono font-bold">
                                    {{ charData.data.system.derivedStats.deathSave.value }}
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-sm font-bold text-white uppercase mb-2 border-b border-red-900 pb-1 flex items-center gap-2">
                                <i data-lucide="crosshair" class="w-4 h-4 text-red-500"></i> Currently Equipped
                            </h3>

                            <div class="space-y-2">
                                <equipped-card label="Weapon" icon="sword"
                                               :item="findItem(charData.data.system.externalData.currentWeapon.id)">
                                </equipped-card>

                                <equipped-card label="Body Armor" icon="shield"
                                               :item="findItem(charData.data.system.externalData.currentArmorBody.id)">
                                </equipped-card>

                                <equipped-card label="Head Armor" icon="hard-hat"
                                               :item="findItem(charData.data.system.externalData.currentArmorHead.id)">
                                </equipped-card>

                                <div v-if="charData.data.system.externalData.currentArmorShield.id">
                                    <equipped-card label="Shield" icon="shield-alert"
                                                   :item="findItem(charData.data.system.externalData.currentArmorShield.id)">
                                    </equipped-card>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="activeTab === 'skills'" key="skills" class="space-y-4">
                        <div v-if="roleItem" class="bg-yellow-900/20 border border-yellow-600/30 p-3 clip-corner">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h3 class="text-yellow-500 font-bold uppercase text-sm">{{ roleItem.name }}</h3>
                                    <p class="text-[10px] text-gray-400">{{ roleItem.system.mainRoleAbility }}</p>
                                </div>
                                <span class="text-2xl font-black text-white">{{ roleItem.system.rank }}</span>
                            </div>
                        </div>

                        <div v-for="(group, categoryKey) in groupedSkills" :key="categoryKey">
                            <div
                                class="flex items-center gap-2 mb-2 mt-4 bg-gray-900 py-1 px-2 border-l-2 border-red-500">
                                <span class="text-white font-bold text-xs uppercase">{{
                                        formatCategory(categoryKey)
                                    }}</span>
                            </div>
                            <div class="grid grid-cols-1 gap-px bg-gray-800 border border-gray-800">
                                <div v-for="skill in group" :key="skill.name"
                                     class="flex justify-between items-center bg-[#0a0a0a] p-2 hover:bg-[#111]">
                                    <div class="flex flex-col">
                                        <div class="flex items-baseline gap-2">
                                            <span class="text-sm font-medium text-gray-300">{{ skill.name }}</span>
                                            <span class="text-[9px] text-red-500 font-bold uppercase tracking-wider">({{
                                                    skill.system.stat
                                                }})</span>
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

                    <div v-else-if="activeTab === 'gear'" key="gear" class="space-y-6">
                        <div>
                            <h3 class="text-sm font-bold text-white uppercase mb-2 flex items-center gap-2">
                                <i data-lucide="zap" class="w-4 h-4 text-blue-500"></i> Cyberware
                            </h3>
                            <div class="space-y-2">
                                <div v-for="cw in allCyberware" :key="cw._id"
                                     class="cpr-panel p-3 bg-[#111] flex flex-col">
                                    <div class="flex justify-between items-start">
                                        <span class="text-sm font-bold text-blue-100">{{ cw.name }}</span>
                                        <span v-if="cw.system.installedItems"
                                              class="text-[9px] bg-blue-900/30 text-blue-400 px-1 border border-blue-900">
                                        SLOTS: {{ cw.system.installedItems.slots }}
                                    </span>
                                    </div>
                                    <div class="mt-1 text-[10px] text-gray-500 uppercase flex justify-between">
                                        <span>{{ formatCategory(cw.type) }}</span>
                                        <span v-if="cw.system.installLocation">{{ cw.system.installLocation }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-sm font-bold text-white uppercase mb-2 flex items-center gap-2">
                                <i data-lucide="package" class="w-4 h-4 text-gray-500"></i> Gear & Weapons
                            </h3>
                            <div class="space-y-2">
                                <div v-for="item in otherGear" :key="item._id"
                                     class="border-l-2 border-gray-700 pl-3 py-1">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-300">{{ item.name }}</span>
                                        <span v-if="item.system.price" class="text-[10px] font-mono text-yellow-600">{{
                                                item.system.price.market
                                            }}eb</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </transition>
            </main>

            <nav
                class="fixed bottom-0 inset-x-0 bg-[#050505] border-t border-red-900/30 flex justify-around p-2 pb-6 z-50">
                <nav-btn icon="activity" label="Stats" :active="activeTab === 'stats'"
                         @click="activeTab = 'stats'"></nav-btn>
                <nav-btn icon="brain" label="Skills" :active="activeTab === 'skills'"
                         @click="activeTab = 'skills'"></nav-btn>
                <nav-btn icon="shield" label="Gear" :active="activeTab === 'gear'"
                         @click="activeTab = 'gear'"></nav-btn>
            </nav>

        </div>
    </div>

</template>

<script>
export default {
    name: 'CharacterSheet',
};
</script>
