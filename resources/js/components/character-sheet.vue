<template>
    <div class="min-h-screen pb-28 text-sans">

        <div v-if="loading" class="flex h-screen items-center justify-center bg-black">
            <div class="flex flex-col items-center">
                <div class="text-lime-500 font-mono animate-pulse text-xl mb-4 tracking-widest">ESTABLISHING NEURAL LINK...</div>
                <div class="w-64 h-1 bg-gray-900 overflow-hidden relative border border-gray-800">
                    <div class="absolute inset-0 bg-lime-500 animate-[loading-bar_2s_infinite]"></div>
                </div>
                <div class="mt-4 text-[10px] text-gray-500 font-mono uppercase tracking-[0.3em] italic">Initializing Headless Session</div>
            </div>
        </div>

        <div v-else-if="error" class="flex h-screen items-center justify-center p-4 bg-black">
            <div class="w-full max-w-md">
                <div class="border-2 border-red-600 bg-red-900/10 p-6 shadow-[0_0_30px_rgba(220,38,38,0.2)]">
                    <div class="flex items-center gap-4 mb-4 border-b border-red-600 pb-4">
                        <i v-icon data-lucide="alert-octagon" class="w-8 h-8 text-red-600"></i>
                        <h2 class="text-red-500 font-black text-2xl uppercase tracking-tighter">Uplink Failed</h2>
                    </div>
                    <div class="text-gray-300 font-mono text-sm leading-relaxed mb-6">
                        {{ error }}
                    </div>
                    <div class="flex gap-4">
                        <button @click="window.location.reload()" class="flex-1 py-2 bg-red-600 text-black font-bold uppercase text-xs hover:bg-red-500 transition-colors">
                            Retry Connection
                        </button>
                        <button @click="goToSettings" class="flex-1 py-2 border border-red-600 text-red-600 font-bold uppercase text-xs hover:bg-red-600/10 transition-colors">
                            Neural Config
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showPopup" class="fixed inset-0 z-[100] flex items-center justify-center p-6 backdrop-blur-sm bg-black/60">
            <div class="cpr-panel w-full max-w-xs bg-black border-2 border-red-600 shadow-[0_0_20px_rgba(220,38,38,0.3)] animate-in fade-in zoom-in duration-200">
                <div class="bg-red-600 p-2 flex justify-between items-center">
                    <span class="text-xs font-black text-black tracking-tighter uppercase italic">SYSTEM ALERT</span>
                    <button @click="close" class="text-black hover:text-white transition-colors">
                        <i v-icon data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
                <div class="p-4 space-y-4">
                    <div class="flex gap-3">
                        <div class="shrink-0 w-10 h-10 border border-red-500 flex items-center justify-center text-red-500 bg-red-900/10">
                            <i v-icon data-lucide="alert-triangle" class="w-6 h-6"></i>
                        </div>
                        <div class="space-y-1">
                            <h4 class="text-red-500 font-bold text-sm uppercase">Connection Error</h4>
                            <p class="text-gray-300 text-xs font-mono leading-relaxed">{{ popupMessage }}</p>
                        </div>
                    </div>
                    <button @click="close" class="w-full py-2 bg-red-600/10 border border-red-600 text-red-500 text-xs font-bold uppercase hover:bg-red-600 hover:text-white transition-all">
                        Acknowledge
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showImagePopup" @click="showImagePopup = false" class="fixed inset-0 z-[110] flex items-center justify-center p-4 backdrop-blur-md bg-black/80">
            <div class="relative max-w-2xl w-full max-h-[90vh] flex flex-col items-center justify-center animate-in fade-in zoom-in duration-200" @click.stop>
                <div class="absolute top-0 right-0 m-4">
                    <button @click="showImagePopup = false" class="text-white/70 hover:text-white transition-colors bg-black/50 rounded-full p-2">
                        <i v-icon data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>
                <img :src="getImageUrl(charData.data.img)" class="max-w-full max-h-[85vh] object-contain border-2 border-red-600 shadow-[0_0_30px_rgba(220,38,38,0.4)]" :alt="charData.data.name">
                <div class="mt-4 text-center">
                    <h2 class="text-white font-bold uppercase tracking-widest text-lg">{{ charData.data.name }}</h2>
                </div>
            </div>
        </div>

        <div v-if="showResourceModal" class="fixed inset-0 z-[120] flex items-center justify-center p-6 bg-black/70" @click="closeResourceModal">
            <div class="w-full max-w-xs bg-[#0b0b0b] border border-gray-800 shadow-2xl" @click.stop>
                <div class="flex items-center justify-between px-3 py-2 border-b border-gray-800 bg-black/40">
                    <span class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Adjust {{ resourceLabel }}</span>
                    <button class="text-gray-400 hover:text-white" @click="closeResourceModal"><i v-icon data-lucide="x" class="w-4 h-4"></i></button>
                </div>
                <div class="p-4 space-y-4">
                    <div class="flex items-center justify-center gap-3">
                        <button class="w-10 h-10 bg-gray-900 border border-gray-700 text-white font-bold hover:bg-red-900/40" @click="stepResource(-1)">-</button>
                        <div class="text-center">
                            <div class="text-xs text-gray-500 uppercase font-bold tracking-widest">Current</div>
                            <div class="text-4xl font-black font-mono" :class="resourceColor">{{ resourceValue }}</div>
                            <div class="text-xs text-gray-600 font-mono">/ {{ resourceMax }}</div>
                        </div>
                        <button class="w-10 h-10 bg-gray-900 border border-gray-700 text-white font-bold hover:bg-green-900/40" @click="stepResource(1)">+</button>
                    </div>

                    <div class="space-y-2">
                        <div class="text-[10px] text-gray-500 uppercase font-bold tracking-widest">Bulk adjust</div>
                        <div class="flex items-center gap-2 w-full">
                            <div class="flex shrink-0">
                                <button class="px-4 py-3 text-base border border-gray-700 rounded-sm"
                                        :class="bulkSign === '+' ? 'bg-green-900/40 text-green-300' : 'bg-gray-900 text-gray-300'"
                                        @click="bulkSign = '+'">+</button>
                                <button class="px-4 py-3 text-base border border-gray-700 -ml-px rounded-sm"
                                        :class="bulkSign === '-' ? 'bg-red-900/40 text-red-300' : 'bg-gray-900 text-gray-300'"
                                        @click="bulkSign = '-'">-</button>
                            </div>
                            <input type="number" inputmode="numeric" pattern="[0-9]*" min="0"
                                   class="flex-1 min-w-0 bg-black border border-gray-800 px-3 py-2 text-white placeholder-gray-600"
                                   v-model.number="bulkAmount" placeholder="Amount"/>
                            <button class="shrink-0 px-4 py-3 bg-gray-900 border border-gray-700 text-white text-base hover:bg-gray-800"
                                    @click="applyBulkChange">Apply</button>
                        </div>
                    </div>

                    <div class="flex gap-2 pt-2">
                        <button class="flex-1 py-2 bg-red-600/10 border border-red-600 text-red-500 text-xs font-bold uppercase hover:bg-red-600 hover:text-black transition-colors" @click="closeResourceModal">Cancel</button>
                        <button class="flex-1 py-2 bg-green-600/10 border border-green-600 text-green-500 text-xs font-bold uppercase hover:bg-green-600 hover:text-black transition-colors" @click="commitResourceValue">Save</button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="!loading && charData && !error">
            <header class="p-3 bg-black/90 sticky top-0 z-50 border-b border-red-900 backdrop-blur-md">
                <div class="flex items-center gap-3 justify-between">
                    <div class="relative w-12 h-12 shrink-0 cursor-pointer group" @click="showImagePopup = true">
                        <div
                            class="absolute inset-0 border border-[var(--cpr-red)] bg-gray-900 flex items-center justify-center overflow-hidden">
                            <img v-if="charData.data.img" :src="getImageUrl(charData.data.img)" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300" :alt="charData.data.name">
                            <span v-else class="text-xl font-bold text-red-500">{{ charData.data.name[0] }}</span>
                        </div>
                        <div class="absolute inset-0 bg-red-600/0 group-hover:bg-red-600/20 transition-colors duration-300 flex items-center justify-center">
                            <i v-icon data-lucide="maximize-2" class="w-4 h-4 text-white opacity-0 group-hover:opacity-100 transition-opacity"></i>
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
                    <div class="shrink-0">
                        <button @click="openRollPanel" class="p-2 border border-red-600 text-red-500 hover:bg-red-600 hover:text-black transition-colors" title="Show Roll Modifiers">
                            <i v-icon data-lucide="dice-6" class="w-5 h-5"></i>
                        </button>
                    </div>
                </div>
            </header>

            <main class="p-3 max-w-md mx-auto space-y-4 relative min-h-[60vh]">

                <transition name="fade">

                    <div v-if="activeTab === 'stats'" key="stats" class="space-y-5 absolute w-full top-0 left-0 p-3">

                        <div class="grid grid-cols-2 gap-3">
                            <resource-counter label="HIT POINTS"
                                              :current="charData.data.system.derivedStats.hp.value"
                                              :max="charData.data.system.derivedStats.hp.max"
                                              color="text-green-500"
                                              @edit="openResourceModal('HIT POINTS', charData.data.system.derivedStats.hp.value, charData.data.system.derivedStats.hp.max, 'text-green-500')">
                            </resource-counter>
                            <resource-counter label="HUMANITY"
                                              :current="charData.data.system.derivedStats.humanity.value"
                                              :max="charData.data.system.derivedStats.humanity.max"
                                              color="text-blue-500"
                                              @edit="openResourceModal('HUMANITY', charData.data.system.derivedStats.humanity.value, charData.data.system.derivedStats.humanity.max, 'text-blue-500')">
                            </resource-counter>
                        </div>

                        <div class="grid grid-cols-4 gap-2">
                            <simple-stat label="INT" :value="stats.int?.value" @click="sendStatRequest('INT')"></simple-stat>
                            <simple-stat label="REF" :value="stats.ref?.value" @click="sendStatRequest('REF')"></simple-stat>
                            <simple-stat label="DEX" :value="stats.dex?.value" @click="sendStatRequest('DEX')"></simple-stat>
                            <simple-stat label="TECH" :value="stats.tech?.value" @click="sendStatRequest('TECH')"></simple-stat>
                            <simple-stat label="COOL" :value="stats.cool?.value" @click="sendStatRequest('COOL')"></simple-stat>
                            <simple-stat label="WILL" :value="stats.will?.value" @click="sendStatRequest('WILL')"></simple-stat>
                            <simple-stat label="MOVE" :value="stats.move?.value" @click="sendStatRequest('MOVE')"></simple-stat>
                            <simple-stat label="BODY" :value="stats.body?.value" @click="sendStatRequest('BODY')"></simple-stat>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <adjustable-stat label="LUCK"
                                             v-model:current="luck.value"
                                             :max="luck.max"
                                             @updateStat="updateStat"
                                             @sendStatRequest="sendStatRequest">
                            </adjustable-stat>
                            <adjustable-stat label="EMP"
                                             v-model:current="emp.value"
                                             :max="emp.max"
                                             @updateStat="updateStat"
                                             @sendStatRequest="sendStatRequest">
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
                            <div class="px-2 text-center w-1/4 cursor-pointer hover:bg-gray-800 transition-colors" @click="sendFaceDown">
                                <div class="text-[9px] text-gray-500 uppercase font-bold">Reputation</div>
                                <div class="text-white font-mono font-bold">{{
                                        charData.data.system.reputation.value
                                    }}
                                </div>
                            </div>
                            <div class="px-2 text-center w-1/4 cursor-pointer hover:bg-gray-800 transition-colors" @click="sendDeathSave">
                                <div class="text-[9px] text-gray-500 uppercase font-bold">Death Save</div>
                                <div class="text-red-500 font-mono font-bold">
                                    {{ charData.data.system.derivedStats.deathSave.penalty }}
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-sm font-bold text-white uppercase mb-4 border-b border-red-900 pb-1 flex items-center gap-2">
                                <i v-icon data-lucide="crosshair" class="w-4 h-4 text-red-500"></i> Currently Equipped
                            </h3>

                            <div v-for="(items, category) in groupedEquipped" :key="category">
                                <div v-if="items.length > 0" class="mb-4">
                                    <h4 class="text-[9px] font-bold text-gray-500 uppercase mb-2 tracking-widest pl-1">{{ category }}</h4>
                                    <div class="space-y-2">
                                        <div v-for="item in items" :key="item._id">
                                            <item-row
                                                :item="item"
                                                :category="category"
                                                :all-items="charData.data.items"
                                                @click="$emit('click', item)">
                                            </item-row>
                                            <div v-if="item.system.installedItems && item.system.installedItems.list && item.system.installedItems.list.length > 0"
                                                 class="ml-6 mt-1 space-y-1 border-l border-red-900/50 pl-2">
                                                <div v-for="childId in item.system.installedItems.list" :key="childId">
                                                    <item-row v-if="charData.data.items.find(i => i._id === childId)" :item="charData.data.items.find(i => i._id === childId)" :category="charData.data.items.find(i => i._id === childId).type" :all-items="charData.data.items" @click="$emit('click', charData.data.items.find(i => i._id === childId))"></item-row>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-if="Object.values(groupedEquipped).flat().length === 0" class="text-gray-600 text-xs italic p-2 text-center">
                                No items equipped.
                            </div>
                        </div>
                    </div>

                    <div v-else-if="activeTab === 'skills'" key="skills" class="space-y-4 absolute w-full top-0 left-0 p-3">
                        <div v-if="roleItem" @click="sendRoleRequest(roleItem.name)" class="relative bg-yellow-900/20 border border-yellow-600/30 p-3 clip-corner cursor-pointer group">
                            <div class="flex justify-between items-center pr-2">
                                <div>
                                    <h3 class="text-yellow-500 font-bold uppercase text-sm">{{ roleItem.name }}</h3>
                                    <p class="text-[10px] text-gray-400">{{ roleItem.system.mainRoleAbility }}</p>
                                </div>
                                <span class="text-2xl font-black text-white">{{ roleItem.system.rank }}</span>
                            </div>
                        </div>

                        <div v-for="(group, categoryKey) in groupedSkills" :key="categoryKey">
                            <div
                                @click="toggleCategory(categoryKey)"
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
                                     @click="sendSkillRequest(skill.name)"
                                     class="flex justify-between items-center bg-[#0a0a0a] p-2 hover:bg-cyan-950/20 cursor-pointer group transition-colors">
                                    <div class="flex flex-col">
                                        <div class="flex items-baseline gap-2">
                                            <span class="text-sm font-medium text-gray-300 group-hover:text-cyan-400 transition-colors">{{ skill.name }}</span>
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

                    <div v-else-if="activeTab === 'gear'" key="gear" class="space-y-6 absolute w-full top-0 left-0 p-3">

                        <div>
                            <h3 @click="toggleCategory('cyberware')" class="text-sm font-bold text-white uppercase mb-2 flex items-center justify-between cursor-pointer group hover:bg-cyan-950/20 p-1 transition-colors">
                                <div class="flex items-center gap-2">
                                    <i v-icon data-lucide="zap" class="w-4 h-4 text-blue-500 group-hover:text-cyan-400"></i> <span class="group-hover:text-cyan-400 transition-colors">Cyberware</span>
                                </div>
                                <div class="transition-transform duration-200" :class="{ 'rotate-180': collapsedCategories['cyberware'] }">
                                    <i v-icon data-lucide="chevron-down"
                                       class="w-4 h-4 text-blue-500 group-hover:text-cyan-500"></i>
                                </div>
                            </h3>
                            <transition name="fade-from-black">
                                <div v-show="!collapsedCategories['cyberware']" class="space-y-2">
                                    <div v-for="(group, type) in groupedCyberware" :key="type">
                                        <h4 class="text-[10px] font-bold text-blue-400 uppercase mb-2 mt-4 tracking-widest border-b border-blue-900/30 pb-1">
                                            {{ type }}
                                        </h4>
                                        <div class="space-y-2">
                                            <div v-for="cw in group" :key="cw._id">
                                                <item-row
                                                    :item="cw"
                                                    category="cyberware"
                                                    :all-items="charData.data.items"
                                                    @click="$emit('click', cw)">
                                                </item-row>
                                                <div v-if="cw.system.installedItems && cw.system.installedItems.list && cw.system.installedItems.list.length > 0"
                                                     class="ml-6 mt-1 space-y-1 border-l border-blue-900/50 pl-2">
                                                    <div v-for="childId in cw.system.installedItems.list" :key="childId">
                                                        <item-row v-if="charData.data.items.find(i => i._id === childId)" :item="charData.data.items.find(i => i._id === childId)" category="cyberware" :all-items="charData.data.items" @click="$emit('click', charData.data.items.find(i => i._id === childId))"></item-row>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </transition>
                        </div>

                        <div v-for="(items, category) in groupedGear" :key="category">
                            <div v-if="items.length > 0">
                                <h3 @click="toggleCategory(category)" class="text-sm font-bold text-white uppercase mb-2 flex items-center justify-between cursor-pointer group mt-6 hover:bg-cyan-950/20 p-1 transition-colors">
                                    <div class="flex items-center gap-2">
                                        <i v-if="category === 'weapon'" v-icon data-lucide="sword" class="w-4 h-4 text-red-500 group-hover:text-cyan-400 transition-colors"></i>
                                        <i v-else-if="category === 'armor'" v-icon data-lucide="shield" class="w-4 h-4 text-blue-500 group-hover:text-cyan-400 transition-colors"></i>
                                        <i v-else-if="category === 'ammo'" v-icon data-lucide="database" class="w-4 h-4 text-yellow-500 group-hover:text-cyan-400 transition-colors"></i>
                                        <i v-else v-icon data-lucide="package" class="w-4 h-4 text-gray-500 group-hover:text-cyan-400 transition-colors"></i>

                                        <span class="group-hover:text-cyan-400 transition-colors">{{ category }}</span>
                                    </div>
                                    <div class="transition-transform duration-200" :class="{ 'rotate-180': collapsedCategories[category] }">
                                        <i v-icon data-lucide="chevron-down"
                                           class="w-4 h-4 text-gray-500 group-hover:text-cyan-500"></i>
                                    </div>
                                </h3>

                                <transition name="fade-from-black">
                                    <div v-show="!collapsedCategories[category]" class="space-y-2">
                                        <div v-for="item in items" :key="item._id">
                                            <item-row
                                                :item="item"
                                                :category="category"
                                                :all-items="charData.data.items"
                                                @click="$emit('click', item)">
                                            </item-row>
                                            <div v-if="item.system.installedItems && item.system.installedItems.list && item.system.installedItems.list.length > 0"
                                                 class="ml-6 mt-1 space-y-1 border-l border-gray-700/50 pl-2">
                                                <div v-for="childId in item.system.installedItems.list" :key="childId">
                                                    <item-row v-if="charData.data.items.find(i => i._id === childId)" :item="charData.data.items.find(i => i._id === childId)" :category="charData.data.items.find(i => i._id === childId).type" :all-items="charData.data.items" @click="$emit('click', charData.data.items.find(i => i._id === childId))"></item-row>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </transition>
                            </div>
                        </div>

                    </div>

                    <div v-else-if="activeTab === 'control'" key="control" class="space-y-6 pb-20 absolute w-full top-0 left-0 p-3">

                        <div class="flex justify-center my-6">
                            <div class="grid grid-cols-3 gap-2 p-4 bg-gray-900/50 rounded-full border border-gray-800 shadow-[0_0_20px_rgba(0,0,0,0.5)]">
                                <div></div>
                                <button @touchstart.prevent="moveToken('up')" @click="moveToken('up')" class="w-16 h-16 bg-[#151515] border border-gray-700 hover:border-red-500 hover:bg-red-900/20 active:bg-red-600 active:text-black rounded-lg flex items-center justify-center transition-all group">
                                    <i v-icon data-lucide="chevron-up" class="w-8 h-8 text-gray-400 group-hover:text-red-500 group-active:text-black"></i>
                                </button>
                                <div></div>

                                <button @touchstart.prevent="moveToken('left')" @click="moveToken('left')" class="w-16 h-16 bg-[#151515] border border-gray-700 hover:border-red-500 hover:bg-red-900/20 active:bg-red-600 active:text-black rounded-lg flex items-center justify-center transition-all group">
                                    <i v-icon data-lucide="chevron-left" class="w-8 h-8 text-gray-400 group-hover:text-red-500 group-active:text-black"></i>
                                </button>
                                <div class="w-16 h-16 flex items-center justify-center">
                                    <div class="w-4 h-4 bg-red-900/50 rounded-full animate-pulse"></div>
                                </div>
                                <button @touchstart.prevent="moveToken('right')" @click="moveToken('right')" class="w-16 h-16 bg-[#151515] border border-gray-700 hover:border-red-500 hover:bg-red-900/20 active:bg-red-600 active:text-black rounded-lg flex items-center justify-center transition-all group">
                                    <i v-icon data-lucide="chevron-right" class="w-8 h-8 text-gray-400 group-hover:text-red-500 group-active:text-black"></i>
                                </button>

                                <div></div>
                                <button @touchstart.prevent="moveToken('down')" @click="moveToken('down')" class="w-16 h-16 bg-[#151515] border border-gray-700 hover:border-red-500 hover:bg-red-900/20 active:bg-red-600 active:text-black rounded-lg flex items-center justify-center transition-all group">
                                    <i v-icon data-lucide="chevron-down" class="w-8 h-8 text-gray-400 group-hover:text-red-500 group-active:text-black"></i>
                                </button>
                                <div></div>
                            </div>
                        </div>

                        <div class="cpr-panel p-4 bg-[#111]">
                            <h3 class="text-xs font-bold text-red-500 uppercase tracking-widest mb-2 flex items-center gap-2">
                                <i v-icon data-lucide="target" class="w-4 h-4"></i> Select Target
                            </h3>
                            <div class="relative">
                                <select v-model="controlState.selectedTarget" class="w-full bg-black border border-gray-700 text-white p-3 text-sm appearance-none focus:border-red-500 focus:outline-none">
                                    <option :value="null">-- NO TARGET --</option>
                                    <option v-for="enemy in mockEnemies" :key="enemy.id" :value="enemy">
                                        {{ enemy.name }} {{ enemy.distanceM }}m
                                    </option>
                                </select>
                                <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-red-500">
                                    <i v-icon data-lucide="chevron-down" class="w-4 h-4"></i>
                                </div>
                            </div>
                            <div v-if="controlState.selectedTarget" class="mt-2 text-[10px] text-gray-500 font-mono text-right">
                                Distance {{controlState.selectedTarget.distanceM}}m
                            </div>
                        </div>

                        <div>
                            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 px-1">Select Weapon</h3>
                            <div class="space-y-2">
                                <div v-if="groupedEquipped.weapon.length === 0" class="text-gray-600 text-xs italic p-2 border border-dashed border-gray-800 text-center">
                                    No weapons equipped.
                                </div>
                                <div v-for="weapon in groupedEquipped.weapon" :key="weapon._id"
                                     @click="controlState.selectedWeapon = weapon"
                                     class="cursor-pointer transition-all duration-200">
                                    <div class="relative" :class="{'border-2 border-red-600': controlState.selectedWeapon?._id === weapon._id}">
                                        <item-row :item="weapon" category="weapon" :all-items="charData.data.items"></item-row>
                                        <div v-if="controlState.selectedWeapon?._id === weapon._id" class="absolute -top-2 -right-2 bg-red-600 text-black text-[9px] font-black px-1 uppercase z-20">Active</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <button @click="reloadWeapon"
                                    class="w-full py-4 bg-blue-900/20 border-2 border-blue-600 text-blue-500 hover:bg-blue-600 hover:text-white text-sm font-black uppercase tracking-widest transition-all active:scale-95 flex items-center justify-center gap-2"
                                    :class="{'animate-pulse-purple': controlState.reloadRequired}">
                                <i v-icon data-lucide="refresh-cw" class="w-5 h-5"></i> Reload Equipped Weapon
                            </button>
                        </div>

                        <div class="cpr-panel p-4 bg-[#111]">
                            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Firing Mode</h3>
                            <div class="grid grid-cols-4 gap-2">
                                <button v-for="mode in ['Single', 'Aim', 'Auto', 'Suppress']" :key="mode"
                                        @click="controlState.fireMode = mode"
                                        class="py-3 text-xs font-bold uppercase border transition-colors flex flex-col items-center gap-1"
                                        :class="controlState.fireMode === mode ? 'bg-red-600 text-black border-red-600' : 'bg-black text-gray-500 border-gray-800 hover:border-gray-500'">
                                    <i v-if="mode === 'Single'" v-icon data-lucide="locate" class="w-4 h-4"></i>
                                    <i v-if="mode === 'Aim'" v-icon data-lucide="locate-fixed" class="w-4 h-4"></i>
                                    <i v-if="mode === 'Auto'" v-icon data-lucide="zap" class="w-4 h-4"></i>
                                    <i v-if="mode === 'Suppress'" v-icon data-lucide="shield-alert" class="w-4 h-4"></i>
                                    {{ mode }}
                                </button>
                            </div>
                        </div>

                        <div v-if="dvInfo && controlState.selectedWeapon" class="text-center py-3">
                            <div class="text-4xl font-black tracking-widest" :class="dvInfo.dv === 'Out of Range' ? 'text-red-500' : 'text-white'">
                                DV: <span>{{ dvInfo.dv }}</span>
                            </div>
                            <div class="text-[15px] text-gray-500 uppercase font-bold mt-1" v-if="dvInfo.distanceMeters && dvInfo.weaponCategory">
                                {{ dvInfo.weaponCategory }} • {{ dvInfo.distanceMeters }}m
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 pt-4 sticky bottom-20 z-40 bg-gradient-to-t from-black via-black to-transparent p-3 -mx-3">
                            <button @click="rollHit" class="cpr-panel bg-red-900/20 border-2 border-red-600 text-red-500 hover:bg-red-600 hover:text-white py-4 text-sm font-black uppercase tracking-widest transition-all active:scale-95 flex items-center justify-center gap-2">
                                <i v-icon data-lucide="crosshair" class="w-5 h-5"></i> Roll Hit
                            </button>
                            <button @click="rollDamage" class="cpr-panel bg-yellow-900/20 border-2 border-yellow-600 text-yellow-500 hover:bg-yellow-500 hover:text-black py-4 text-sm font-black uppercase tracking-widest transition-all active:scale-95 flex items-center justify-center gap-2">
                                <i v-icon data-lucide="skull" class="w-5 h-5"></i> Roll Dmg
                            </button>
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
                <nav-btn icon="gamepad-2" label="Control" :active="activeTab === 'control'"
                         @click="activeTab = 'control'"></nav-btn>
                <nav-btn icon="settings" label="Config" :active="activeTab === 'profile'"
                         @click="goToSettings"></nav-btn>
            </nav>

            <transition name="slide-right">
                <aside v-if="showRollPanel" class="fixed top-0 right-0 h-full w-[20rem] max-w-[85vw] z-[60] bg-black/95 border-l border-red-900 shadow-[0_0_30px_rgba(220,38,38,0.35)]">
                    <div class="flex items-center justify-between p-3 border-b border-red-900 bg-black/70 sticky top-0">
                        <div class="flex items-center gap-2">
                            <i v-icon data-lucide="dice-6" class="w-4 h-4 text-red-500"></i>
                            <span class="text-xs font-black uppercase tracking-widest text-red-500">Roll Modifiers</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button @click="fetchRollModifiers" class="p-1 text-gray-300 hover:text-white">
                                <i v-icon data-lucide="refresh-cw" class="w-4 h-4"></i>
                            </button>
                            <button @click="closeRollPanel" class="p-1 text-gray-300 hover:text-white">
                                <i v-icon data-lucide="x" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                    <div class="p-3 space-y-3 overflow-y-auto h-full pb-24">
                        <div v-if="rollModsLoading" class="text-center py-8 text-gray-400 text-xs">Loading modifiers...</div>

                        <div v-else-if="rollModsError" class="border border-red-700 bg-red-900/20 text-red-400 text-xs p-3">
                            {{ rollModsError }}
                            <div v-if="!controlState.selectedWeapon" class="mt-2">
                                <button @click="activeTab='control'; closeRollPanel()" class="text-[10px] uppercase font-bold border border-red-600 text-red-500 px-2 py-1 hover:bg-red-600 hover:text-black transition-colors">Go to Control</button>
                            </div>
                        </div>

                        <div v-else-if="rollModsData" class="space-y-4">
                            <div class="bg-[#0b0b0b] border border-gray-800 p-3">
                                <div class="text-[10px] text-gray-500 uppercase font-bold">Actor</div>
                                <div class="text-white font-bold">{{ rollModsData.actorName }}</div>
                            </div>

                            <div class="bg-[#0b0b0b] border border-gray-800 p-3">
                                <div class="flex items-center justify-between">
                                    <div class="text-[10px] text-gray-500 uppercase font-bold">Base Bonus</div>
                                    <div class="text-green-400 font-mono font-bold">+{{ rollModsData.baseBonus }}</div>
                                </div>
                                <div v-if="rollModsData.breakdown" class="mt-2 text-[11px] text-gray-300 font-mono space-y-1">
                                    <div class="flex justify-between"><span>{{ (rollModsData.breakdown.statName || 'STAT').toUpperCase() }}</span><span>+{{ rollModsData.breakdown.statValue }}</span></div>
                                    <div class="flex justify-between"><span>{{ rollModsData.breakdown.skillName }}</span><span>+{{ rollModsData.breakdown.skillLevel }}</span></div>
                                    <div class="flex justify-between"><span>Weapon Acc</span><span>+{{ rollModsData.breakdown.weaponAcc }}</span></div>
                                </div>
                            </div>

                            <div class="bg-[#0b0b0b] border border-gray-800 p-3">
                                <div class="flex items-center justify-between">
                                    <div class="text-[10px] text-gray-500 uppercase font-bold">Total Penalty</div>
                                    <div :class="{'text-red-400': rollModsData.totalPenalty < 0, 'text-gray-300': rollModsData.totalPenalty === 0, 'text-green-400': rollModsData.totalPenalty > 0}" class="font-mono font-bold">{{ rollModsData.totalPenalty }}</div>
                                </div>
                            </div>

                            <div class="bg-[#0b0b0b] border border-gray-800 p-3">
                                <div class="flex items-center justify-between">
                                    <div class="text-[10px] text-gray-500 uppercase font-bold">Final Roll Modifier</div>
                                    <div :class="{'text-green-400': rollModsData.finalModifier >= 0, 'text-red-400': rollModsData.finalModifier < 0}" class="font-mono font-bold">{{ rollModsData.finalModifier }}</div>
                                </div>
                            </div>

                            <div class="bg-[#0b0b0b] border border-gray-800 p-3">
                                <div class="text-[10px] text-gray-500 uppercase font-bold mb-2">Injuries & Effects</div>
                                <div v-if="!rollModsData.injuries || rollModsData.injuries.length === 0" class="text-xs text-gray-500 italic">No injuries affecting rolls.</div>
                                <div v-else class="space-y-2">
                                    <div v-for="(inj, idx) in rollModsData.injuries" :key="idx" class="border border-gray-800 p-2 bg-black/40">
                                        <div class="flex items-center justify-between">
                                            <div class="text-xs font-bold text-white">{{ inj.title }}</div>
                                            <div :class="{'text-red-400': inj.penalty < 0, 'text-gray-300': inj.penalty === 0, 'text-green-400': inj.penalty > 0}" class="text-[11px] font-mono">{{ inj.penalty }}</div>
                                        </div>
                                        <div v-if="inj.description" class="text-[11px] text-gray-400 mt-1">{{ inj.description }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else class="text-xs text-gray-400">No data available.</div>
                    </div>
                </aside>
            </transition>

        </div>
    </div>

</template>

<script>
import {computed, onMounted, onUpdated, ref, shallowRef, triggerRef, nextTick, watch, onUnmounted} from "vue";

// --- PERFORMANCE HELPERS ---

// Custom icon directive to render icons efficiently per element
const vIcon = {
    mounted(el) {
        // Wait for DOM insertion
        nextTick(() => {
            if (window.lucide && el.parentElement) {
                window.lucide.createIcons({ root: el.parentElement });
            }
        });
    },
    updated(el) {
        // Wait for DOM updates
        nextTick(() => {
            if (window.lucide && el.parentElement) {
                window.lucide.createIcons({ root: el.parentElement });
            }
        });
    }
};

// Deep equality check to prevent unnecessary reactivity updates
function isDeepEqual(obj1, obj2) {
    if (obj1 === obj2) return true;
    if (typeof obj1 !== 'object' || obj1 === null || typeof obj2 !== 'object' || obj2 === null) return false;
    const keys1 = Object.keys(obj1);
    const keys2 = Object.keys(obj2);
    if (keys1.length !== keys2.length) return false;
    for (let key of keys1) {
        if (!keys2.includes(key) || !isDeepEqual(obj1[key], obj2[key])) return false;
    }
    return true;
}

const _inFlight = new Map();
const _responseCache = new Map();
const _makeKey = (url, data) => `${url}::${JSON.stringify(data||{})}`;

function cachedPost(url, data, ttlMs = 1500) {
    const key = _makeKey(url, data);
    const now = Date.now();
    const cached = _responseCache.get(key);
    if (cached && cached.expiresAt > now) {
        return Promise.resolve(cached.data);
    }
    if (_inFlight.has(key)) {
        return _inFlight.get(key);
    }
    const p = window.axios.post(url, data).then(resp => {
        _responseCache.set(key, { expiresAt: now + ttlMs, data: resp });
        _inFlight.delete(key);
        return resp;
    }).catch(err => {
        _inFlight.delete(key);
        throw err;
    });
    _inFlight.set(key, p);
    return p;
}

function debounce(fn, wait = 250) {
    let t = null;
    return (...args) => {
        if (t) clearTimeout(t);
        t = setTimeout(() => fn(...args), wait);
    };
}

function arraysEqualShallow(a, b) {
    if (a === b) return true;
    if (!Array.isArray(a) || !Array.isArray(b)) return false;
    if (a.length !== b.length) return false;
    // Compare items by ID hash if available, else simple equality
    const aIds = a.map(i => i.id || JSON.stringify(i)).join(',');
    const bIds = b.map(i => i.id || JSON.stringify(i)).join(',');
    return aIds === bIds;
}

// --- SUB COMPONENTS (Directives added) ---

const NavBtn = {
    props: ['icon', 'label', 'active'],
    directives: { icon: vIcon },
    template: `
        <button class="flex flex-col items-center gap-1 w-16 transition-colors cursor-pointer"
                :class="active ? 'text-red-500' : 'text-gray-600'"><i v-icon :data-lucide="icon" class="w-6 h-6"></i><span
            class="text-[9px] font-bold uppercase tracking-wider">{{ label }}</span></button>`
};

const SimpleStat = {
    props: ['label', 'value'],
    emits: ['click'],
    template: `
        <div @click="$emit('click')" class="cpr-panel bg-[#151515] hover:bg-cyan-900/40 hover:border-cyan-500/50 p-2 flex flex-col items-center justify-center cpr-stat-box cursor-pointer transition-all group">
            <span class="text-xl font-bold text-white leading-none group-hover:text-cyan-400 transition-colors">{{ value }}</span>
            <span class="text-[10px] text-[var(--cpr-red)] font-bold uppercase mt-1 group-hover:text-cyan-500 transition-colors">{{ label }}</span>
        </div>`
};

const AdjustableStat = {
    props: ['label', 'current', 'max'],
    emits: ['update:current', 'updateStat', 'sendStatRequest'],
    template: `
        <div class="cpr-panel bg-[#151515] cursor-pointer p-2 flex justify-between items-center" @click="$emit('sendStatRequest', label)">
            <button @click.stop="$emit('update:current', current > 0 ? current - 1 : current); $emit('updateStat', label, current > 0 ? current - 1 : current);"
                    class="w-8 h-8 bg-gray-800 hover:bg-red-900 text-white font-bold rounded-sm cursor-pointer">-
            </button>
            <div class="flex flex-col items-center"><span
                class="text-sm font-bold text-gray-400 uppercase tracking-widest">{{ label }}</span><span
                class="text-xl font-mono font-black text-white">{{ current }} <span class="text-gray-600 text-sm">/
                {{ max }}</span></span></div>
            <button @click.stop="$emit('update:current', (current < max ? current + 1 : current)); $emit('updateStat', label, current < max ? current + 1 : current)"
                    class="w-8 h-8 bg-gray-800 hover:bg-green-900 text-white font-bold rounded-sm cursor-pointer">+
            </button>
        </div>`
};

const ResourceCounter = {
    props: ['label', 'current', 'max', 'color'],
    emits: ['edit'],
    template: `
        <div class="cpr-panel p-2 bg-[#111] flex flex-col items-center cursor-pointer" @click="$emit('edit')"><span
            class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">{{ label }}</span>
            <div class="flex items-baseline gap-1"><span class="text-2xl font-black font-mono"
                                                         :class="color">{{ current }}</span><span
                class="text-xs text-gray-600 font-mono">/ {{ max }}</span></div>
        </div>`
};

const ItemRow = {
    props: ['item', 'category', 'all-items'],
    directives: { icon: vIcon },
    setup(props) {
        const expanded = ref(false);
        const toggle = () => {
            expanded.value = !expanded.value;
        };
        const formatType = (type) => {
            if (!type) return 'Other';
            const result = type.replace(/([A-Z])/g, ' $1').trim();
            return result.charAt(0).toUpperCase() + result.slice(1);
        };

        const findItem = (id) => {
            if (!id || !props.allItems) return null;
            return props.allItems.find(i => i._id === id);
        };

        const ammoName = computed(() => {
            if (props.category !== 'weapon' || !props.item.system.installedItems || !props.item.system.installedItems.list) return null;
            const ammoId = props.item.system.installedItems.list[0];
            if (!ammoId) return null;
            const ammoItem = findItem(ammoId);
            return ammoItem ? ammoItem.name : null;
        });

        const armorDisplay = computed(() => {
            if (props.category !== 'armor') return null;
            const sys = props.item.system;
            const results = [];

            if (sys.isShield && sys.shieldHitPoints) {
                results.push({
                    label: 'Shield HP',
                    value: `${sys.shieldHitPoints.value} / ${sys.shieldHitPoints.max}`,
                    icon: 'shield'
                });
            }

            if (sys['armor-head'] && sys['armor-head'].sp > 0) {
                const head = sys['armor-head'];
                results.push({
                    label: 'Head',
                    value: `${head.sp - head.ablation} / ${head.sp}`,
                    icon: 'user'
                });
            }

            if (sys['armor-body'] && sys['armor-body'].sp > 0) {
                const body = sys['armor-body'];
                results.push({
                    label: 'Body',
                    value: `${body.sp - body.ablation} / ${body.sp}`,
                    icon: 'user'
                });
            }

            return results.length > 0 ? results : null;
        });

        const statusLine = computed(() => {
            let percent = null;
            const sys = props.item.system;

            if (props.category === 'weapon' && sys.magazine && sys.magazine.max > 0) {
                percent = (sys.magazine.value / sys.magazine.max) * 100;
            } else if (props.category === 'armor') {
                if (sys.isShield && sys.shieldHitPoints && sys.shieldHitPoints.max > 0) {
                    percent = (sys.shieldHitPoints.value / sys.shieldHitPoints.max) * 100;
                } else {
                    let totalSp = 0;
                    let currentSp = 0;
                    let count = 0;

                    if (sys['armor-head'] && sys['armor-head'].sp > 0) {
                        totalSp += sys['armor-head'].sp;
                        currentSp += (sys['armor-head'].sp - sys['armor-head'].ablation);
                        count++;
                    }
                    if (sys['armor-body'] && sys['armor-body'].sp > 0) {
                        totalSp += sys['armor-body'].sp;
                        currentSp += (sys['armor-body'].sp - sys['armor-body'].ablation);
                        count++;
                    }

                    if (totalSp > 0) {
                        percent = (currentSp / totalSp) * 100;
                    }
                }
            }

            if (percent === null) return null;
            percent = Math.min(100, Math.max(0, percent));

            let color = 'bg-green-500';
            if (percent < 25) color = 'bg-red-500';
            else if (percent < 60) color = 'bg-amber-500';

            return {
                width: `${percent}%`,
                color: color
            };
        });

        return { expanded, toggle, formatType, findItem, ammoName, armorDisplay, statusLine };
    },
    template: `
        <div class="cpr-panel bg-[#111] flex flex-col transition-colors border-l-2 relative group overflow-hidden"
             :class="{
            'border-red-600': category === 'weapon',
            'border-blue-600': category === 'armor',
            'border-yellow-600': category === 'ammo',
            'border-gray-600': category === 'gear',
            'border-blue-400': category === 'cyberware'
         }">

            <div v-if="statusLine" class="absolute top-0 left-0 h-[3px] transition-all duration-500 z-10"
                 :class="statusLine.color"
                 :style="{ width: statusLine.width }">
            </div>

            <div class="flex items-stretch min-h-[3.5rem]">
                <div class="flex-1 p-2 flex flex-col justify-center cursor-default transition-colors" @click="$emit('click', item)">
                    <div class="flex justify-between items-start pr-2">
                        <div class="flex flex-col">
                        <span class="text-sm font-bold text-gray-200 group-hover:text-cyan-400 transition-colors">
                            {{ item.name }}
                        </span>

                            <div class="flex flex-wrap gap-2 mt-1">
                                <template v-if="category === 'armor' && armorDisplay">
                                    <div v-for="stat in armorDisplay" :key="stat.label" class="flex items-center gap-1 mr-2">
                                        <i v-icon :data-lucide="stat.icon" class="w-3 h-3 text-blue-500"></i>
                                        <span class="text-[10px] font-mono text-gray-400">
                                            <span class="text-[8px] uppercase text-gray-600 mr-0.5">{{ stat.label }}:</span>
                                            {{ stat.value }}
                                        </span>
                                    </div>
                                </template>

                                <div v-if="item.system.magazine && category === 'weapon'" class="flex items-center gap-1">
                                    <i v-icon data-lucide="logs" class="w-3 h-3 text-gray-500"></i>
                                    <span class="text-[10px] font-mono text-gray-400">
                                    {{ item.system.magazine.value }} <span class="text-gray-600">/</span> {{ item.system.magazine.max }}
                                        <span v-if="ammoName" class="ml-1 text-yellow-600">[{{ ammoName }}]</span>
                                    <span v-if="item.system.damage" class="ml-2 text-red-400 font-bold">{{ item.system.damage }}</span>
                                    <span v-if="item.system.rof > 0" class="ml-2 text-orange-400">ROF:{{ item.system.rof }}</span>
                                </span>
                                </div>

                                <div v-else-if="(item.system.amount || item.system.count) && category !== 'armor'" class="flex items-center gap-1">
                                    <i v-icon data-lucide="hash" class="w-3 h-3 text-gray-500"></i>
                                    <span class="text-[10px] font-mono text-gray-400">
                                    x{{ item.system.amount || item.system.count }}
                                        <span v-if="item.system.damage" class="ml-2 text-red-400 font-bold">{{ item.system.damage }}</span>
                                    <span v-if="item.system.rof > 0" class="ml-2 text-orange-400">ROF:{{ item.system.rof }}</span>
                                </span>
                                </div>

                                <div v-if="category === 'cyberware'" class="flex items-center gap-1">
                                    <i v-icon data-lucide="cpu" class="w-3 h-3 text-blue-500"></i>
                                    <span class="text-[10px] font-mono text-blue-400">
                                    {{ item.system.installedItems.usedSlots }} / {{ item.system.installedItems.slots }}
                                </span>
                                </div>

                                <span v-if="item.system.installLocation" class="text-[9px] text-gray-500 uppercase">
                                [{{ item.system.installLocation }}]
                            </span>
                            </div>
                        </div>
                    </div>
                </div>

                <button
                    @click.stop="toggle"
                    class="w-12 shrink-0 flex items-center justify-center bg-gray-900/30 border-l border-white/5 active:bg-cyan-900/20 transition-colors hover:bg-cyan-900/40 cursor-pointer">
                    <div class="transition-transform duration-300 flex items-center justify-center"
                         :class="{ 'rotate-180': expanded }">
                        <i v-icon data-lucide="chevron-down"
                           class="w-5 h-5 text-gray-500 group-hover:text-cyan-500">
                        </i>
                    </div>
                </button>
            </div>

            <transition name="expand">
                <div v-if="expanded" class="border-t border-white/5 bg-black/40 p-3 shadow-inner">
                    <div v-if="item.system.description && item.system.description.value"
                         class="text-gray-300 text-xs leading-relaxed font-sans cpr-description mb-3"
                         v-html="item.system.description.value">
                    </div>
                    <div v-else class="text-gray-600 text-xs italic mb-3">No description available.</div>

                    <div class="grid grid-cols-2 gap-2 text-[10px] uppercase font-bold text-gray-500">
                        <div v-if="item.system.price" class="bg-gray-900/50 p-1 px-2 border-l-2 border-yellow-600 flex justify-between items-center">
                            <span>Cost</span>
                            <span class="text-yellow-600 font-mono">
                                {{ item.system.price.market }}eb
                            </span>
                        </div>
                        <div v-if="item.system.concealable" class="bg-gray-900/50 p-1 px-2 border-l-2 flex justify-between items-center"
                             :class="item.system.concealable.concealable ? 'border-green-600' : 'border-red-600'">
                            <span>Concealable</span>
                            <span :class="item.system.concealable.concealable ? 'text-green-500' : 'text-red-500'">
                                {{ (item.system.concealable.concealable ? "Yes" : "No" ) }}
                            </span>
                        </div>
                        <div v-if="item.system.source" class="bg-gray-900/50 p-1 px-2 border-l-2 border-gray-600 flex justify-between items-center">
                            <span>Source</span>
                            <span class="text-gray-300 normal-case truncate max-w-[100px] text-right">
                            {{ typeof item.system.source === 'object' ? item.system.source.book + ' p.' + item.system.source.page : item.system.source }}
                        </span>
                        </div>
                    </div>
                </div>
            </transition>
        </div>
    `
};

export default {
    name: 'CharacterSheet',
    components: { NavBtn, SimpleStat, AdjustableStat, ResourceCounter, ItemRow },
    directives: { icon: vIcon },
    setup() {
        const activeTab = ref('stats');
        // Changed to shallowRef for performance optimization on large objects
        const charData = shallowRef(null);
        const loading = ref(true);
        const error = ref(null);
        const showPopup = ref(false);
        const showImagePopup = ref(false);
        const popupMessage = ref('');
        const baseUrl = ref('');

        const mockEnemies = ref([]);

        const controlState = ref({
            selectedTarget: null,
            selectedWeapon: null,
            fireMode: 'Single',
            reloadRequired: false
        });

        // Helper to refresh icons safely (now mostly used via directives, but kept for popups)
        const refreshIcons = () => {
            if (window.lucide) {
                nextTick(() => window.lucide.createIcons());
            }
        };

        const getImageUrl = (path) => {
            if (!path) return '';
            if (path.startsWith('http')) return path;
            if (baseUrl.value) return baseUrl.value + "/" + path;
            window.axios.get("/getbaseurl").then(response => {
                baseUrl.value = response.data;
                console.log(baseUrl.value)
                return baseUrl.value + "/" + path;
            })
        };

        // --- ROLL MODIFIERS PANEL STATE ---
        const showRollPanel = ref(false);
        const rollModsLoading = ref(false);
        const rollModsError = ref(null);
        const rollModsData = ref(null);

        // --- DV INFO STATE ---
        const dvInfo = ref(null);
        const dvLoading = ref(false);
        const dvError = ref(null);

        const fetchDVForSelectedWeapon = async () => {
            dvError.value = null;
            dvInfo.value = null;
            if (!controlState.value.selectedWeapon) return;
            if (!controlState.value.selectedTarget) return;
            try {
                dvLoading.value = true;
                const response = await cachedPost('/getvisibletokens', {
                    selectedWeapon: controlState.value.selectedWeapon
                }, 1000);
                const payload = response?.data?.data?.result;
                if (!isDeepEqual(dvInfo.value || {}, payload || {})) {
                    dvInfo.value = payload;
                }
            } catch (e) {
                console.error('Error fetching DV', e.error);
                dvError.value = 'Unable to fetch DV.';
            } finally {
                dvLoading.value = false;
            }
        };

        const debouncedFetchDV = debounce(fetchDVForSelectedWeapon, 250);

        const fetchRollModifiers = async () => {
            rollModsError.value = null;
            rollModsLoading.value = true;
            rollModsData.value = null;
            try {
                if (!controlState.value.selectedWeapon) {
                    rollModsError.value = 'No weapon selected. Choose a weapon in Control > Select Weapon.';
                    return;
                }
                const response = await cachedPost('/getrollmodifiers', {
                    selectedWeapon: controlState.value.selectedWeapon
                }, 2500);
                const data = response?.data?.data;
                const payload = data?.result ? data.result : data;

                if (!isDeepEqual(rollModsData.value || {}, payload || {})) {
                    rollModsData.value = payload;
                }

                if (rollModsData.value?.error) {
                    rollModsError.value = rollModsData.value.error;
                    rollModsData.value = null;
                }
            } catch (e) {
                console.error('Error fetching roll modifiers', e);
                rollModsError.value = 'Unable to fetch roll modifiers.';
            } finally {
                rollModsLoading.value = false;
            }
        };

        const debouncedFetchRollModifiers = debounce(fetchRollModifiers, 250);

        const showResourceModal = ref(false);
        const resourceLabel = ref('');
        const resourceValue = ref(0);
        const resourceMax = ref(0);
        const resourceColor = ref('text-white');
        const bulkSign = ref('+');
        const bulkAmount = ref(0);

        const clampResource = (val) => {
            const max = Number(resourceMax.value) || 0;
            if (max <= 0) return Math.max(0, Math.floor(val));
            return Math.max(0, Math.min(max, Math.floor(val)));
        };

        const openResourceModal = (label, current, max, color) => {
            resourceLabel.value = label;
            resourceValue.value = clampResource(Number(current) || 0);
            resourceMax.value = Number(max) || 0;
            resourceColor.value = color || 'text-white';
            bulkSign.value = '+';
            bulkAmount.value = 0;
            showResourceModal.value = true;
        };

        const closeResourceModal = () => {
            showResourceModal.value = false;
        };

        const stepResource = (delta) => {
            resourceValue.value = clampResource(resourceValue.value + (delta || 0));
        };

        const applyBulkChange = () => {
            const amt = Math.abs(Number(bulkAmount.value) || 0);
            if (!amt) return;
            const sign = bulkSign.value === '-' ? -1 : 1;
            resourceValue.value = clampResource(resourceValue.value + sign * amt);
        };

        const commitResourceValue = () => {
            if (charData.value) {
                if (resourceLabel.value === 'HIT POINTS') {
                    const hp = charData.value.data.system.derivedStats.hp;
                    if (hp) hp.value = resourceValue.value;
                } else if (resourceLabel.value === 'HUMANITY') {
                    const hum = charData.value.data.system.derivedStats.humanity;
                    if (hum) hum.value = resourceValue.value;
                }
                // Important: Trigger ref update since we mutated a nested prop of a shallowRef
                triggerRef(charData);
            }
            updateStat(resourceLabel.value, resourceValue.value);
            showResourceModal.value = false;
        };

        const openRollPanel = async () => {
            showRollPanel.value = true;
            await fetchRollModifiers();
            if (controlState.value.selectedWeapon) {
                fetchDVForSelectedWeapon();
            }
        };
        const closeRollPanel = () => {
            showRollPanel.value = false;
        };

        let refreshInterval = null;
        let actorUuid = null;

        const fetchCharacterData = async (isInitial = false) => {
            try {
                if (isInitial) {
                    const getActors = await window.axios.post("/getactors");
                    actorUuid = getActors.data.uuid;
                }
                if (!actorUuid) return;

                const response = await window.axios.post("/actorsheet", {"actorUuid" : actorUuid});

                // Optimization: Deep Equality Check prevents re-render if data hasn't changed
                if (charData.value && isDeepEqual(charData.value.data, response.data.data)) {
                    // Update only metadata/requestId if needed, but skip heavy re-assignment
                    if (charData.value.requestId !== response.data.requestId) {
                        charData.value.requestId = response.data.requestId;
                    }
                    return;
                }

                charData.value = response.data;
                if (isInitial) {
                    loading.value = false;
                }
                // Refresh icons only when structure actually changes
                setTimeout(refreshIcons, 50);
            } catch (err) {
                console.error("Fetch Error:", err);
                if (isInitial) {
                    if (err.response) {
                        error.value = err.response.data.error;
                        popupMessage.value = err.response.data.error;
                        showPopup.value = true;
                    }
                    loading.value = false;
                }
            }
        };

        onMounted(async () => {
            await fetchCharacterData(true);
            refreshInterval = setInterval(() => {
                fetchCharacterData();
            }, 30000);
        });

        onUnmounted(() => {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        });

        const stats = computed(() => {
            if (!charData.value) return {};
            const { luck, emp, ...rest } = charData.value.data.system.stats;
            return rest;
        });

        const luck = computed(() => charData.value?.data.system.stats.luck || { value: 0, max: 0 });
        const emp = computed(() => charData.value?.data.system.stats.emp || { value: 0, max: 0 });

        const roleItem = computed(() => {
            if (!charData.value) return null;
            const id = charData.value.data.system.roleInfo.activeNetRole;
            return charData.value.data.items.find(i => i._id === id);
        });

        const groupedCyberware = computed(() => {
            if (!charData.value) return {};
            const allItems = charData.value.data.items;
            const installedIds = new Set();
            allItems.forEach(item => {
                if (item.system.installedItems && item.system.installedItems.list) {
                    item.system.installedItems.list.forEach(id => installedIds.add(id));
                }
            });
            const cyberware = allItems.filter(i =>
                ['cyberware', 'fashionware', 'cyberwareInternal', 'cyberwareExternal'].includes(i.type) &&
                !installedIds.has(i._id)
            );
            const groups = {};
            cyberware.forEach(cw => {
                const type = cw.system.cyberwareType || 'Other';
                if (!groups[type]) groups[type] = [];
                groups[type].push(cw);
            });
            return groups;
        });

        const groupedGear = computed(() => {
            if (!charData.value) return {};
            const allItems = charData.value.data.items;
            const installedIds = new Set();
            allItems.forEach(item => {
                if (item.system.installedItems && item.system.installedItems.list) {
                    item.system.installedItems.list.forEach(id => installedIds.add(id));
                }
            });
            const groups = { weapon: [], armor: [], ammo: [], gear: [] };
            const items = allItems.filter(i =>
                ['weapon', 'armor', 'gear', 'ammo'].includes(i.type) &&
                !installedIds.has(i._id)
            );
            items.forEach(item => {
                if (groups[item.type]) groups[item.type].push(item);
                else groups.gear.push(item);
            });
            return groups;
        });

        const groupedEquipped = computed(() => {
            if (!charData.value) return {};
            const allItems = charData.value.data.items;
            const installedIds = new Set();
            allItems.forEach(item => {
                if ((item.system.installedItems && item.system.installedItems.list) && item.system.isWeapon === true) {
                    item.system.installedItems.list.forEach(id => installedIds.add(id));
                }
            });
            const groups = { weapon: [], armor: [], ammo: [], gear: [] };
            const items = allItems.filter(i => {
                const isEquipped = i.system.equipped === 'equipped' || i.system.equipped === 'owned';
                const isToggledWeapon = i.system.isWeapon && i.system.usage === 'toggled';
                return (isEquipped || isToggledWeapon) && !installedIds.has(i._id);
            });
            items.forEach(item => {
                if (item.system.isWeapon) {
                    groups['weapon'].push(item);
                    return;
                }
                if (groups[item.type]) groups[item.type].push(item);
                else if (['cyberware', 'fashionware', 'cyberwareInternal', 'cyberwareExternal'].includes(item.type)) {
                }
                else groups.gear.push(item);
            });
            return groups;
        });

        const groupedSkills = computed(() => {
            if (!charData.value) return {};
            const skills = charData.value.data.items.filter(i => i.type === 'skill');
            const groups = {};
            skills.sort((a, b) => a.name.localeCompare(b.name));
            skills.forEach(skill => {
                const cat = skill.system.category || 'other';
                if (!groups[cat]) groups[cat] = [];
                groups[cat].push(skill);
            });
            return groups;
        });

        const calculateBase = (skill) => {
            if (!charData.value) return 0;
            const statVal = charData.value.data.system.stats[skill.system.stat]?.value || 0;
            return skill.system.level + statVal;
        };

        const findItem = (id) => {
            if (!id || !charData.value) return null;
            return charData.value.data.items.find(i => i._id === id);
        };

        const formatCategory = (cat) => {
            if (!cat) return 'Other';
            const result = cat.replace(/([A-Z])/g, ' $1').trim();
            return result.charAt(0).toUpperCase() + result.slice(1);
        };

        const delayedFetch = () => {
            setTimeout(() => {
                fetchCharacterData();
            }, 3000);
        };

        const sendRequest = (data, url) => {
            window.axios.post(url, data).then(response => {
                console.log('Stat post successful', response);
                delayedFetch();
                return response;
            }).catch(err => {
                console.error('Error posting stat', err);
                popupMessage.value = "Neural uplink failed: Data packet lost.";
                showPopup.value = true;
                return err;
            });
        }

        const getVisibleTokens = () => {
            cachedPost("/listvisibletokens", {}, 1500).then(response => {
                const d = response?.data?.data;
                const payload = d?.result;
                const list = Array.isArray(payload) ? payload : [];

                // Optimization: Patch existing array to prevent v-for destroy/re-create
                const currentIds = (mockEnemies.value || []).map(e => e.id).sort().join(',');
                const newIds = list.map(e => e.id).sort().join(',');

                if (currentIds !== newIds) {
                    // Structure changed (new enemy appeared/died), replace whole list
                    const prevSelectedId = controlState.value.selectedTarget?.id || null;
                    mockEnemies.value = list;
                    // Try to preserve selection
                    if (prevSelectedId) {
                        const preserved = list.find(t => t.id === prevSelectedId);
                        if (preserved && controlState.value.selectedTarget?.id !== preserved.id) {
                            controlState.value.selectedTarget = preserved;
                        }
                    }
                } else {
                    // Same enemies, just update properties (like distance) in place
                    list.forEach(newItem => {
                        const existing = mockEnemies.value.find(e => e.id === newItem.id);
                        if (existing) {
                            // Vue will reactively update only the text that changed
                            if(existing.distanceM !== newItem.distanceM) existing.distanceM = newItem.distanceM;
                            if(existing.name !== newItem.name) existing.name = newItem.name;
                        }
                    });
                }

                if (controlState.value.selectedTarget && controlState.value.selectedWeapon) {
                    debouncedFetchDV();
                }
            }).catch(err => {
                console.error('Error fetching visible tokens', err);
            });
        };
        const debouncedGetVisibleTokens = debounce(getVisibleTokens, 200);

        watch(activeTab, (newTab) => {
            if (newTab === 'control') {
                const prevSelectedId = controlState.value.selectedTarget?.id || null;
                if (prevSelectedId) {
                    const match = mockEnemies.value.find(t => t.id === prevSelectedId);
                    if (match && controlState.value.selectedTarget !== match) {
                        controlState.value.selectedTarget = match;
                    }
                }
                debouncedGetVisibleTokens();
            }
        });

        const sendStatRequest = (stat) => {
            sendRequest({ label: stat }, "/statrollrequest")
        };

        const updateStat = (stat, value) => {
            // FIX: Manual local update because charData is shallowRef
            if (charData.value) {
                const key = stat.toLowerCase(); // LUCK -> luck
                if (charData.value.data.system.stats[key]) {
                    charData.value.data.system.stats[key].value = value;
                    triggerRef(charData); // Force UI update
                }
            }
            sendRequest({ label: stat, value: value }, "/updatestat")
        }

        const sendSkillRequest = (skill) => {
            sendRequest({ label: skill }, "/skillrollrequest")
        };

        const sendRoleRequest = (role) => {
            sendRequest({ label: role }, "/rolerollrequest")
        };

        const sendDeathSave = () => {
            sendRequest({}, "/deathsave")
        };

        const sendFaceDown = () => {
            sendRequest({}, "/facedown")
        };

        let moveRefreshTimer = null;
        const moveToken = (direction) => {
            window.axios.post("/movetoken", { direction }).then(() => {
                delayedFetch();
            });
            if (moveRefreshTimer) clearTimeout(moveRefreshTimer);
            moveRefreshTimer = setTimeout(() => {
                getVisibleTokens();
                if (controlState.value.selectedWeapon && controlState.value.selectedTarget) {
                    debouncedFetchDV();
                }
            }, 4000);
        };

        const goToSettings = () => {
            window.location.href = '/settings';
        };

        const goToProfile = () => {
            window.location.href = '/settings';
        };

        watch(() => controlState.value.selectedTarget, (newTarget) => {
            if (newTarget) {
                targetToken(newTarget.id);
                debouncedFetchDV();
            }
        });

        const targetToken = (tokenId) => {
            window.axios.post("/targettokens", { token: tokenId }).then(() => {
                delayedFetch();
            });
        }

        const rollHit = () => {
            console.log("Rolling Hit", controlState.value);
            if (!controlState.value.selectedTarget) {
                throwError("No Target Selected")
                return;
            }
            if (!controlState.value.selectedWeapon) {
                throwError("No Weapon Selected")
                return;
            }
            if (controlState.value.reloadRequired) {
                throwError("Out of Ammo!")
                return;
            }
            window.axios.post("/attackroll", controlState.value).then(() => {
                delayedFetch();
            });
        };

        const rollDamage = () => {
            console.log("Rolling Damage", controlState.value);
            window.axios.post("/damageroll", controlState.value).then(() => {
                delayedFetch();
            });
        };

        const checkReloadRequired = () => {
            if (controlState.value.selectedWeapon) {
                const weapon = controlState.value.selectedWeapon;
                if (weapon.system.magazine && weapon.system.magazine.max > 0) {
                    controlState.value.reloadRequired = weapon.system.magazine.value === 0;
                } else {
                    controlState.value.reloadRequired = false;
                }
            } else {
                controlState.value.reloadRequired = false;
            }
        };

        watch(() => controlState.value.selectedWeapon, async (newWeapon) => {
            checkReloadRequired();
            if (newWeapon) {
                debouncedFetchDV();
                debouncedFetchRollModifiers();
            } else {
                dvInfo.value = null;
            }
        }, { deep: true });

        watch(() => charData.value, (newData) => {
            if (newData && controlState.value.selectedWeapon) {
                const updatedWeapon = newData.data.items.find(i => i._id === controlState.value.selectedWeapon._id);
                if (updatedWeapon) {
                    controlState.value.selectedWeapon = updatedWeapon;
                    checkReloadRequired();
                }
            }
        }, { deep: true });

        const reloadWeapon = () => {
            console.log("Reloading Weapon", controlState.value.selectedWeapon);
            if (controlState.value.selectedWeapon) {
                sendRequest(controlState.value, "/reloadweapon");
            }
        };

        // Removed the global onUpdated hook to prevent excessive icon refresh
        // onUpdated(() => { if (!loading.value) refreshIcons(); });

        onUnmounted(() => {
            if (moveRefreshTimer) clearTimeout(moveRefreshTimer);
        });

        const throwError = (message) => {
            popupMessage.value = message;
            showPopup.value = true;
        }

        const logout = () => {
            window.axios.post('/logout').then(() => {
                window.location.href = '/login';
            });
        };

        const close = () => {
            if (charData.value) {
                showPopup.value = false;
            } else {
                logout();
            }
        };

        const collapsedCategories = ref({});

        const toggleCategory = (category) => {
            collapsedCategories.value[category] = !collapsedCategories.value[category];
            // refreshIcons is still useful here for the expanded section
            setTimeout(refreshIcons, 50);
        };

        return {
            charData, loading, error, activeTab,
            stats, luck, emp, roleItem,
            groupedSkills, groupedCyberware, groupedGear, groupedEquipped,
            calculateBase, findItem, formatCategory, getImageUrl, sendStatRequest, sendSkillRequest,
            sendRoleRequest, updateStat, showPopup, showImagePopup, popupMessage,
            collapsedCategories, toggleCategory,
            refreshIcons,
            logout, close,
            mockEnemies, controlState, moveToken, rollHit, rollDamage, reloadWeapon, goToProfile, goToSettings,
            showRollPanel, rollModsLoading, rollModsError, rollModsData, openRollPanel, closeRollPanel, fetchRollModifiers,
            dvInfo, dvLoading, dvError, fetchDVForSelectedWeapon,
            showResourceModal, resourceLabel, resourceValue, resourceMax, resourceColor, bulkSign, bulkAmount,
            openResourceModal, closeResourceModal, stepResource, applyBulkChange, commitResourceValue,
            sendDeathSave, sendFaceDown
        };
    }
};
</script>
<style scoped>
/* Added basic fade transition styles if they were missing */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.fade-from-black-enter-active,
.fade-from-black-leave-active {
    transition: all 0.7s cubic-bezier(0.165, 0.84, 0.44, 1);
    overflow: hidden;
}

.fade-from-black-enter-from,
.fade-from-black-leave-to {
    opacity: 0;
    max-height: 0;
    transform: translateY(20px) skewY(2deg);
    filter: brightness(3) hue-rotate(90deg);
}

.fade-from-black-enter-to,
.fade-from-black-leave-from {
    max-height: 1000px;
}

@keyframes pulse-purple {
    0% {
        background-color: rgba(88, 28, 135, 0.2); /* bg-purple-900/20 */
        box-shadow: 0 0 0 0 rgba(88, 28, 135, 0.4);
    }
    50% {
        background-color: rgba(88, 28, 135, 0.6); /* darker purple */
        box-shadow: 0 0 15px 5px rgba(88, 28, 135, 0.4);
    }
    100% {
        background-color: rgba(88, 28, 135, 0.2);
        box-shadow: 0 0 0 0 rgba(88, 28, 135, 0.4);
    }
}

@keyframes loading-bar {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

.animate-pulse-purple {
    animation: pulse-purple 3s infinite ease-in-out;
    border-color: #7e22ce !important; /* purple-700 */
    color: #e9d5ff !important; /* purple-200 for readability */
}

/* Expansion Animation */
.expand-enter-active,
.expand-leave-active {
    transition: all 0.2s ease-out;
    max-height: 500px;
    opacity: 1;
}
.expand-enter-from,
.expand-leave-to {
    max-height: 0;
    opacity: 0;
    overflow: hidden;
}

/* Slide-in from right */
.slide-right-enter-active,
.slide-right-leave-active {
    transition: transform 0.2s ease, opacity 0.2s ease;
}
.slide-right-enter-from,
.slide-right-leave-to {
    transform: translateX(100%);
    opacity: 0.5;
}

/* Specific styles for the description HTML injection */
.cpr-description p {
    margin-bottom: 0.5rem;
}
.cpr-description strong {
    color: #ef4444; /* red-500 */
}
</style>
