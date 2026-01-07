<template>
    <div class="min-h-screen pb-28 text-sans">

        <!-- LOADING STATE -->
        <div v-if="loading" class="flex h-screen items-center justify-center bg-black">
            <div class="text-red-500 font-mono animate-pulse text-xl">CONNECTING TO NET...</div>
        </div>

        <!-- ERROR STATE -->
        <div v-else-if="error" class="flex h-screen items-center justify-center p-4 bg-black">
            <div class="text-red-500 font-bold border border-red-500 p-4 bg-red-900/20">
                ERROR: {{ error }}
            </div>
        </div>

        <!-- SYSTEM ALERT POPUP -->
        <div v-if="showPopup" class="fixed inset-0 z-[100] flex items-center justify-center p-6 backdrop-blur-sm bg-black/60">
            <div class="cpr-panel w-full max-w-xs bg-black border-2 border-red-600 shadow-[0_0_20px_rgba(220,38,38,0.3)] animate-in fade-in zoom-in duration-200">
                <div class="bg-red-600 p-2 flex justify-between items-center">
                    <span class="text-xs font-black text-black tracking-tighter uppercase italic">SYSTEM ALERT</span>
                    <button @click="showPopup = false" class="text-black hover:text-white transition-colors">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
                <div class="p-4 space-y-4">
                    <div class="flex gap-3">
                        <div class="shrink-0 w-10 h-10 border border-red-500 flex items-center justify-center text-red-500 bg-red-900/10">
                            <i data-lucide="alert-triangle" class="w-6 h-6"></i>
                        </div>
                        <div class="space-y-1">
                            <h4 class="text-red-500 font-bold text-sm uppercase">Connection Error</h4>
                            <p class="text-gray-300 text-xs font-mono leading-relaxed">{{ popupMessage }}</p>
                        </div>
                    </div>
                    <button @click="showPopup = false" class="w-full py-2 bg-red-600/10 border border-red-600 text-red-500 text-xs font-bold uppercase hover:bg-red-600 hover:text-white transition-all">
                        Acknowledge
                    </button>
                </div>
            </div>
        </div>

        <!-- CHARACTER IMAGE POPUP -->
        <div v-if="showImagePopup" @click="showImagePopup = false" class="fixed inset-0 z-[110] flex items-center justify-center p-4 backdrop-blur-md bg-black/80">
            <div class="relative max-w-2xl w-full max-h-[90vh] flex flex-col items-center justify-center animate-in fade-in zoom-in duration-200" @click.stop>
                <div class="absolute top-0 right-0 m-4">
                    <button @click="showImagePopup = false" class="text-white/70 hover:text-white transition-colors bg-black/50 rounded-full p-2">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>
                <img :src="getImageUrl(charData.data.img)" class="max-w-full max-h-[85vh] object-contain border-2 border-red-600 shadow-[0_0_30px_rgba(220,38,38,0.4)]" :alt="charData.data.name">
                <div class="mt-4 text-center">
                    <h2 class="text-white font-bold uppercase tracking-widest text-lg">{{ charData.data.name }}</h2>
                </div>
            </div>
        </div>

        <!-- MAIN INTERFACE -->
        <div v-if="!loading && charData && !error">
            <header class="p-3 bg-black/90 sticky top-0 z-50 border-b border-red-900 backdrop-blur-md">
                <div class="flex items-center gap-3">
                    <div class="relative w-12 h-12 shrink-0 cursor-pointer group" @click="showImagePopup = true">
                        <div
                            class="absolute inset-0 border border-[var(--cpr-red)] bg-gray-900 flex items-center justify-center overflow-hidden">
                            <img v-if="charData.data.img" :src="getImageUrl(charData.data.img)" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300" :alt="charData.data.name">
                            <span v-else class="text-xl font-bold text-red-500">{{ charData.data.name[0] }}</span>
                        </div>
                        <div class="absolute inset-0 bg-red-600/0 group-hover:bg-red-600/20 transition-colors duration-300 flex items-center justify-center">
                            <i data-lucide="maximize-2" class="w-4 h-4 text-white opacity-0 group-hover:opacity-100 transition-opacity"></i>
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

                <transition name="fade" mode="out-in" @after-enter="refreshIcons">

                    <!-- STATS TAB -->
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

                        <!-- Currently Equipped Section -->
                        <div>
                            <h3 class="text-sm font-bold text-white uppercase mb-4 border-b border-red-900 pb-1 flex items-center gap-2">
                                <i data-lucide="crosshair" class="w-4 h-4 text-red-500"></i> Currently Equipped
                            </h3>

                            <!-- Loop through categorized equipped items -->
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
                                            <!-- Nested Children for Equipped (ammo, attachments) -->
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

                    <!-- SKILLS TAB -->
                    <div v-else-if="activeTab === 'skills'" key="skills" class="space-y-4">
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
                                    <i data-lucide="chevron-down"
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

                    <!-- GEAR TAB -->
                    <div v-else-if="activeTab === 'gear'" key="gear" class="space-y-6">

                        <!-- Cyberware Section -->
                        <div>
                            <h3 @click="toggleCategory('cyberware')" class="text-sm font-bold text-white uppercase mb-2 flex items-center justify-between cursor-pointer group hover:bg-cyan-950/20 p-1 transition-colors">
                                <div class="flex items-center gap-2">
                                    <i data-lucide="zap" class="w-4 h-4 text-blue-500 group-hover:text-cyan-400"></i> <span class="group-hover:text-cyan-400 transition-colors">Cyberware</span>
                                </div>
                                <div class="transition-transform duration-200" :class="{ 'rotate-180': collapsedCategories['cyberware'] }">
                                    <i data-lucide="chevron-down"
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
                                            <!-- Cyberware Item Row -->
                                            <div v-for="cw in group" :key="cw._id">
                                                <item-row
                                                    :item="cw"
                                                    category="cyberware"
                                                    :all-items="charData.data.items"
                                                    @click="$emit('click', cw)">
                                                </item-row>
                                                <!-- Nested Children -->
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

                        <!-- Gear, Weapons, Armor, Ammo Section -->
                        <div v-for="(items, category) in groupedGear" :key="category">
                            <!-- Only show category if items exist -->
                            <div v-if="items.length > 0">
                                <h3 @click="toggleCategory(category)" class="text-sm font-bold text-white uppercase mb-2 flex items-center justify-between cursor-pointer group mt-6 hover:bg-cyan-950/20 p-1 transition-colors">
                                    <div class="flex items-center gap-2">
                                        <!-- Dynamic Icons based on category -->
                                        <i v-if="category === 'weapon'" data-lucide="sword" class="w-4 h-4 text-red-500 group-hover:text-cyan-400 transition-colors"></i>
                                        <i v-else-if="category === 'armor'" data-lucide="shield" class="w-4 h-4 text-blue-500 group-hover:text-cyan-400 transition-colors"></i>
                                        <i v-else-if="category === 'ammo'" data-lucide="database" class="w-4 h-4 text-yellow-500 group-hover:text-cyan-400 transition-colors"></i>
                                        <i v-else data-lucide="package" class="w-4 h-4 text-gray-500 group-hover:text-cyan-400 transition-colors"></i>

                                        <span class="group-hover:text-cyan-400 transition-colors">{{ category }}</span>
                                    </div>
                                    <div class="transition-transform duration-200" :class="{ 'rotate-180': collapsedCategories[category] }">
                                        <i data-lucide="chevron-down"
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
                                            <!-- Nested Children for Gear (e.g. attachments on weapons) -->
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

                    <!-- CONTROL TAB (NEW) -->
                    <div v-else-if="activeTab === 'control'" key="control" class="space-y-6 pb-20">

                        <!-- D-Pad Section -->
                        <div class="flex justify-center my-6">
                            <div class="grid grid-cols-3 gap-2 p-4 bg-gray-900/50 rounded-full border border-gray-800 shadow-[0_0_20px_rgba(0,0,0,0.5)]">
                                <!-- Empty Top Left -->
                                <div></div>
                                <!-- Up Button -->
                                <button @touchstart.prevent="moveToken('up')" @click="moveToken('up')" class="w-16 h-16 bg-[#151515] border border-gray-700 hover:border-red-500 hover:bg-red-900/20 active:bg-red-600 active:text-black rounded-lg flex items-center justify-center transition-all group">
                                    <i data-lucide="chevron-up" class="w-8 h-8 text-gray-400 group-hover:text-red-500 group-active:text-black"></i>
                                </button>
                                <!-- Empty Top Right -->
                                <div></div>

                                <!-- Left Button -->
                                <button @touchstart.prevent="moveToken('left')" @click="moveToken('left')" class="w-16 h-16 bg-[#151515] border border-gray-700 hover:border-red-500 hover:bg-red-900/20 active:bg-red-600 active:text-black rounded-lg flex items-center justify-center transition-all group">
                                    <i data-lucide="chevron-left" class="w-8 h-8 text-gray-400 group-hover:text-red-500 group-active:text-black"></i>
                                </button>
                                <!-- Center (Action/Stop/Center) -->
                                <div class="w-16 h-16 flex items-center justify-center">
                                    <div class="w-4 h-4 bg-red-900/50 rounded-full animate-pulse"></div>
                                </div>
                                <!-- Right Button -->
                                <button @touchstart.prevent="moveToken('right')" @click="moveToken('right')" class="w-16 h-16 bg-[#151515] border border-gray-700 hover:border-red-500 hover:bg-red-900/20 active:bg-red-600 active:text-black rounded-lg flex items-center justify-center transition-all group">
                                    <i data-lucide="chevron-right" class="w-8 h-8 text-gray-400 group-hover:text-red-500 group-active:text-black"></i>
                                </button>

                                <!-- Empty Bottom Left -->
                                <div></div>
                                <!-- Down Button -->
                                <button @touchstart.prevent="moveToken('down')" @click="moveToken('down')" class="w-16 h-16 bg-[#151515] border border-gray-700 hover:border-red-500 hover:bg-red-900/20 active:bg-red-600 active:text-black rounded-lg flex items-center justify-center transition-all group">
                                    <i data-lucide="chevron-down" class="w-8 h-8 text-gray-400 group-hover:text-red-500 group-active:text-black"></i>
                                </button>
                                <!-- Empty Bottom Right -->
                                <div></div>
                            </div>
                        </div>

                        <!-- Targeting Section -->
                        <div class="cpr-panel p-4 bg-[#111]">
                            <h3 class="text-xs font-bold text-red-500 uppercase tracking-widest mb-2 flex items-center gap-2">
                                <i data-lucide="target" class="w-4 h-4"></i> Select Target
                            </h3>
                            <div class="relative">
                                <select v-model="controlState.selectedTarget" class="w-full bg-black border border-gray-700 text-white p-3 text-sm appearance-none focus:border-red-500 focus:outline-none">
                                    <option :value="null">-- NO TARGET --</option>
                                    <option v-for="enemy in mockEnemies" :key="enemy.id" :value="enemy">
                                        {{ enemy.name }} {{ enemy.distanceM }}m
                                    </option>
                                </select>
                                <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-red-500">
                                    <i data-lucide="chevron-down" class="w-4 h-4"></i>
                                </div>
                            </div>
                            <div v-if="controlState.selectedTarget" class="mt-2 text-[10px] text-gray-500 font-mono text-right">
                                Distance {{controlState.selectedTarget.distanceM}}m
                            </div>
                        </div>

                        <!-- Weapon Selection Section -->
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

                        <!-- Reload Button -->
                        <div class="mb-4">
                            <button @click="reloadWeapon"
                                    class="w-full py-4 bg-blue-900/20 border-2 border-blue-600 text-blue-500 hover:bg-blue-600 hover:text-white text-sm font-black uppercase tracking-widest transition-all active:scale-95 flex items-center justify-center gap-2"
                                    :class="{'animate-pulse-purple': controlState.reloadRequired}">
                                <i data-lucide="refresh-cw" class="w-5 h-5"></i> Reload Equipped Weapon
                            </button>
                        </div>

                        <!-- Firing Modes -->
                        <div class="cpr-panel p-4 bg-[#111]">
                            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Firing Mode</h3>
                            <div class="grid grid-cols-4 gap-2">
                                <button v-for="mode in ['Single', 'Aim', 'Auto', 'Suppress']" :key="mode"
                                        @click="controlState.fireMode = mode"
                                        class="py-3 text-xs font-bold uppercase border transition-colors flex flex-col items-center gap-1"
                                        :class="controlState.fireMode === mode ? 'bg-red-600 text-black border-red-600' : 'bg-black text-gray-500 border-gray-800 hover:border-gray-500'">
                                    <i v-if="mode === 'Single'" data-lucide="locate" class="w-4 h-4"></i>
                                    <i v-if="mode === 'Aim'" data-lucide="locate-fixed" class="w-4 h-4"></i>
                                    <i v-if="mode === 'Auto'" data-lucide="zap" class="w-4 h-4"></i>
                                    <i v-if="mode === 'Suppress'" data-lucide="shield-alert" class="w-4 h-4"></i>
                                    {{ mode }}
                                </button>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="grid grid-cols-2 gap-3 pt-4 sticky bottom-20 z-40 bg-gradient-to-t from-black via-black to-transparent p-3 -mx-3">
                            <button @click="rollHit" class="cpr-panel bg-red-900/20 border-2 border-red-600 text-red-500 hover:bg-red-600 hover:text-white py-4 text-sm font-black uppercase tracking-widest transition-all active:scale-95 flex items-center justify-center gap-2">
                                <i data-lucide="crosshair" class="w-5 h-5"></i> Roll Hit
                            </button>
                            <button @click="rollDamage" class="cpr-panel bg-yellow-900/20 border-2 border-yellow-600 text-yellow-500 hover:bg-yellow-500 hover:text-black py-4 text-sm font-black uppercase tracking-widest transition-all active:scale-95 flex items-center justify-center gap-2">
                                <i data-lucide="skull" class="w-5 h-5"></i> Roll Dmg
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
                <!-- NEW CONTROL BTN -->
                <nav-btn icon="gamepad-2" label="Control" :active="activeTab === 'control'"
                         @click="activeTab = 'control'"></nav-btn>
                <nav-btn icon="user-cog" label="Profile" :active="activeTab === 'profile'"
                         @click="goToProfile"></nav-btn>
            </nav>

        </div>
    </div>

</template>

<script>
import {computed, onMounted, onUpdated, ref, nextTick, watch, onUnmounted} from "vue";

// --- SUB COMPONENTS ---

const NavBtn = {
    props: ['icon', 'label', 'active'],
    template: `
        <button class="flex flex-col items-center gap-1 w-16 transition-colors cursor-pointer"
                :class="active ? 'text-red-500' : 'text-gray-600'"><i :data-lucide="icon" class="w-6 h-6"></i><span
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
    template: `
        <div class="cpr-panel p-2 bg-[#111] flex flex-col items-center"><span
            class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">{{ label }}</span>
            <div class="flex items-baseline gap-1"><span class="text-2xl font-black font-mono"
                                                         :class="color">{{ current }}</span><span
                class="text-xs text-gray-600 font-mono">/ {{ max }}</span></div>
        </div>`
};

// New Item Row Component with Expansion Logic
const ItemRow = {
    props: ['item', 'category', 'all-items'],
    setup(props) {
        const expanded = ref(false);
        const toggle = () => {
            expanded.value = !expanded.value;
            if (expanded.value) {
                // Ensure icons inside dropdown render
                setTimeout(() => { if(window.lucide) window.lucide.createIcons(); }, 10);
            }
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
            const ammoId = props.item.system.installedItems.list[0]; // Assuming first item in list is ammo for weapons
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

            // Ensure percent is between 0 and 100
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

            <!-- Status Bar Line -->
            <div v-if="statusLine" class="absolute top-0 left-0 h-[3px] transition-all duration-500 z-10"
                 :class="statusLine.color"
                 :style="{ width: statusLine.width }">
            </div>

            <div class="flex items-stretch min-h-[3.5rem]">
                <!-- Main Content -->
                <div class="flex-1 p-2 flex flex-col justify-center cursor-default transition-colors" @click="$emit('click', item)">
                    <div class="flex justify-between items-start pr-2">
                        <div class="flex flex-col">
                        <span class="text-sm font-bold text-gray-200 group-hover:text-cyan-400 transition-colors">
                            {{ item.name }}
                        </span>

                            <!-- Subtitles / Stats -->
                            <div class="flex flex-wrap gap-2 mt-1">
                                <!-- Armor Display -->
                                <template v-if="category === 'armor' && armorDisplay">
                                    <div v-for="stat in armorDisplay" :key="stat.label" class="flex items-center gap-1 mr-2">
                                        <i :data-lucide="stat.icon" class="w-3 h-3 text-blue-500"></i>
                                        <span class="text-[10px] font-mono text-gray-400">
                                            <span class="text-[8px] uppercase text-gray-600 mr-0.5">{{ stat.label }}:</span>
                                            {{ stat.value }}
                                        </span>
                                    </div>
                                </template>

                                <!-- Magazine / Weapons -->
                                <div v-if="item.system.magazine && category === 'weapon'" class="flex items-center gap-1">
                                    <i data-lucide="logs" class="w-3 h-3 text-gray-500"></i>
                                    <span class="text-[10px] font-mono text-gray-400">
                                    {{ item.system.magazine.value }} <span class="text-gray-600">/</span> {{ item.system.magazine.max }}
                                        <span v-if="ammoName" class="ml-1 text-yellow-600">[{{ ammoName }}]</span>
                                    <span v-if="item.system.damage" class="ml-2 text-red-400 font-bold">{{ item.system.damage }}</span>
                                    <span v-if="item.system.rof > 0" class="ml-2 text-orange-400">ROF:{{ item.system.rof }}</span>
                                </span>
                                </div>

                                <!-- Amount -->
                                <div v-else-if="(item.system.amount || item.system.count) && category !== 'armor'" class="flex items-center gap-1">
                                    <i data-lucide="hash" class="w-3 h-3 text-gray-500"></i>
                                    <span class="text-[10px] font-mono text-gray-400">
                                    x{{ item.system.amount || item.system.count }}
                                        <span v-if="item.system.damage" class="ml-2 text-red-400 font-bold">{{ item.system.damage }}</span>
                                    <span v-if="item.system.rof > 0" class="ml-2 text-orange-400">ROF:{{ item.system.rof }}</span>
                                </span>
                                </div>

                                <!-- Install Slots (Cyberware/Attachments) -->
                                <div v-if="category === 'cyberware'" class="flex items-center gap-1">
                                    <i data-lucide="cpu" class="w-3 h-3 text-blue-500"></i>
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

                <!-- Chevron Trigger -->
                <button
                    @click.stop="toggle"
                    class="w-12 shrink-0 flex items-center justify-center bg-gray-900/30 border-l border-white/5 active:bg-cyan-900/20 transition-colors hover:bg-cyan-900/40 cursor-pointer">
                    <div class="transition-transform duration-300 flex items-center justify-center"
                         :class="{ 'rotate-180': expanded }">
                        <i data-lucide="chevron-down"
                           class="w-5 h-5 text-gray-500 group-hover:text-cyan-500">
                        </i>
                    </div>
                </button>
            </div>

            <!-- Dropdown Content -->
            <transition name="expand">
                <div v-if="expanded" class="border-t border-white/5 bg-black/40 p-3 shadow-inner">
                    <!-- Description -->
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
    setup() {
        const activeTab = ref('stats');
        const charData = ref(null);
        const loading = ref(true);
        const error = ref(null);
        const showPopup = ref(false);
        const showImagePopup = ref(false);
        const popupMessage = ref('');

        // --- MOCK DATA FOR CONTROL TAB ---
        const mockEnemies = ref([]);

        const controlState = ref({
            selectedTarget: null,
            selectedWeapon: null,
            fireMode: 'Single',
            reloadRequired: false
        });

        watch(controlState, (newControlState) => {
            if (newControlState) {
                console.log(newControlState);
            }
        }, { deep: true });


        // Helper to refresh icons safely
        const refreshIcons = () => {
            if (window.lucide) {
                nextTick(() => window.lucide.createIcons());
            }
        };

        const getImageUrl = (path) => {
            if (!path) return '';
            if (path.startsWith('http')) return path;
            return 'https://foundry.surrealdisco.co.uk/' + path;
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

                // Simple check to see if data changed to avoid unnecessary re-renders
                // though usually Vue's reactivity handles this well, we can compare requestId if it exists
                if (!charData.value || charData.value.requestId !== response.data.requestId) {
                    charData.value = response.data;
                    if (isInitial) {
                        loading.value = false;
                    }
                    // Wait for DOM update then render icons
                    setTimeout(refreshIcons, 50);
                }
            } catch (err) {
                console.error("Fetch Error:", err);
                if (isInitial) {
                    if (err.response) {
                        error.value = err.response.data.error;
                        popupMessage.value = err.response.data.error;
                        showPopup.value = true;
                    }
                    loading.value = false;
                    setTimeout(refreshIcons, 50);
                }
            }
        };

        // --- FETCH DATA --
        onMounted(async () => {
            await fetchCharacterData(true);
            // Set up polling every 5 seconds
            refreshInterval = setInterval(() => {
                fetchCharacterData();
            }, 5000);
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

            // Get all items that could be installed in others
            const allItems = charData.value.data.items;
            const installedIds = new Set();
            allItems.forEach(item => {
                if (item.system.installedItems && item.system.installedItems.list) {
                    item.system.installedItems.list.forEach(id => installedIds.add(id));
                }
            });

            const cyberware = allItems.filter(i =>
                ['cyberware', 'fashionware', 'cyberwareInternal', 'cyberwareExternal'].includes(i.type) &&
                !installedIds.has(i._id) // Only top-level items
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

            // Get all items that could be installed in others
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

            // Get all items that could be installed in others
            const allItems = charData.value.data.items;
            const installedIds = new Set();
            allItems.forEach(item => {
                if (item.system.installedItems && item.system.installedItems.list) {
                    item.system.installedItems.list.forEach(id => installedIds.add(id));
                }
            });

            const groups = { weapon: [], armor: [], ammo: [], gear: [] };
            // Filter strictly for equipped items AND NOT installed in others
            const items = allItems.filter(i => i.system.usage === 'equipped' && !installedIds.has(i._id));

            items.forEach(item => {
                if (groups[item.type]) groups[item.type].push(item);
                else if (['cyberware', 'fashionware', 'cyberwareInternal', 'cyberwareExternal'].includes(item.type)) {
                    // Handled in Cyberware tab usually, but if user equips them they show in Gear tab under "Gear" or "Other"
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

        const getItemIcon = (type) => {
            const icons = {
                weapon: 'sword',
                armor: 'shield',
                gear: 'package',
                ammo: 'database',
                cyberware: 'zap',
                skill: 'book'
            };
            return icons[type] || 'circle-help';
        };

        const sendRequest = (data, url) => {
            window.axios.post(url, data
            ).then(response => {
                console.log('Stat post successful', response);
                return response;
            }).catch(err => {
                console.error('Error posting stat', err);
                popupMessage.value = "Neural uplink failed: Data packet lost.";
                showPopup.value = true;
                setTimeout(refreshIcons, 50);
                return err;
            });
        }

        const getVisibleTokens = () => {
            window.axios.post("/getvisibletokens").then(response => {
                if (response.data.success && response.data.data.result) {
                    mockEnemies.value = response.data.data.result;
                }
            }).catch(err => {
                console.error('Error fetching visible tokens', err);
            });
        };

        watch(activeTab, (newTab) => {
            if (newTab === 'control') {
                getVisibleTokens();
            }
        });

        const sendStatRequest = (stat) => {
            sendRequest({
                label: stat
            }, "/statrollrequest")
        };

        const updateStat = (stat, value) => {
            sendRequest({
                label: stat,
                value: value
            }, "/updatestat")
        }

        const sendSkillRequest = (skill) => {
            sendRequest({
                label: skill
            }, "/skillrollrequest")
        };

        const sendRoleRequest = (role) => {
            sendRequest({
                label: role
            }, "/rolerollrequest")
        };

        // --- CONTROL TAB ACTIONS ---
        const moveToken = (direction) => {
            window.axios.post("/movetoken", { direction });
        };

        const goToProfile = () => {
            window.location.href = '/profile-setup';
        };

        watch(() => controlState.value.selectedTarget, (newTarget) => {
            if (newTarget) {
                targetToken(newTarget.id);
            }
        });

        const targetToken = (tokenId) => {
            window.axios.post("/targettokens", { token: tokenId });
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
            window.axios.post("/attackroll", controlState.value);
        };

        const rollDamage = () => {
            console.log("Rolling Damage", controlState.value);
            // TODO: Implement API call
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

        watch(() => controlState.value.selectedWeapon, (newWeapon) => {
            checkReloadRequired();
        }, { deep: true });

        watch(() => charData.value, (newData) => {
            if (newData && controlState.value.selectedWeapon) {
                // Update selected weapon reference from the fresh charData if it exists
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
                sendRequest({
                    itemUuid: controlState.value.selectedWeapon.uuid,
                    actorUuid: actorUuid
                }, "/reloadweapon");
            }
        };

        onUpdated(() => {
            if (!loading.value) refreshIcons();
        });

        const throwError = (message) => {
            popupMessage.value = message;
            showPopup.value = true;
        }

        const collapsedCategories = ref({});

        const toggleCategory = (category) => {
            collapsedCategories.value[category] = !collapsedCategories.value[category];
            setTimeout(refreshIcons, 50);
        };

        return {
            charData, loading, error, activeTab,
            stats, luck, emp, roleItem,
            groupedSkills, groupedCyberware, groupedGear, groupedEquipped,
            calculateBase, findItem, formatCategory, getItemIcon, getImageUrl, sendStatRequest, sendSkillRequest,
            sendRoleRequest, updateStat, showPopup, showImagePopup, popupMessage,
            collapsedCategories, toggleCategory,
            refreshIcons,
            // New Control Exports
            mockEnemies, controlState, moveToken, rollHit, rollDamage, reloadWeapon, goToProfile
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

/* Specific styles for the description HTML injection */
.cpr-description p {
    margin-bottom: 0.5rem;
}
.cpr-description strong {
    color: #ef4444; /* red-500 */
}
</style>
