<template>
    <div class="min-h-screen pb-28">

        <div v-if="loading" class="flex h-screen items-center justify-center">
            <div class="text-red-500 font-mono animate-pulse text-xl">CONNECTING TO NET...</div>
        </div>

        <div v-else-if="error" class="flex h-screen items-center justify-center p-4">
            <div class="text-red-500 font-bold border border-red-500 p-4 bg-red-900/20">
                ERROR: {{ error }}
            </div>
        </div>

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

        <div v-else-if="!loading && charData">
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

                        <div>
                            <h3 class="text-sm font-bold text-white uppercase mb-2 border-b border-red-900 pb-1 flex items-center gap-2">
                                <i data-lucide="crosshair" class="w-4 h-4 text-red-500"></i> Currently Equipped
                            </h3>

                            <div class="space-y-2">
                                <div v-for="item in equippedItems" :key="item._id">
                                    <equipped-card :label="formatCategory(item.type)" :icon="getItemIcon(item.type)"
                                                   :item="item">
                                    </equipped-card>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="activeTab === 'skills'" key="skills" class="space-y-4">
                        <div v-if="roleItem" @click="sendRoleRequest(roleItem.name)" class="bg-yellow-900/20 border border-yellow-600/30 p-3 clip-corner cursor-pointer">
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
                            <div class="grid grid-cols-1 gap-px bg-gray-800 border border-gray-800 cursor-pointer">
                                <div v-for="skill in group" :key="skill.name"
                                     @click="sendSkillRequest(skill.name)"
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
import {computed, onMounted, onUpdated, ref} from "vue";

const NavBtn = {
    props: ['icon', 'label', 'active'],
    template: `
        <button class="flex flex-col items-center gap-1 w-16 transition-colors"
                :class="active ? 'text-red-500' : 'text-gray-600'"><i :data-lucide="icon" class="w-6 h-6"></i><span
            class="text-[9px] font-bold uppercase tracking-wider">{{ label }}</span></button>`
};

const SimpleStat = {
    props: ['label', 'value'],
    emits: ['click'],
    template: `
        <div @click="$emit('click')" class="cpr-panel bg-[#151515] hover:bg-[#AA0A0A6B] p-2 flex flex-col items-center justify-center cpr-stat-box cursor-pointer transition-colors"><span
            class="text-xl font-bold text-white leading-none">{{ value }}</span><span
            class="text-[10px] text-[var(--cpr-red)] font-bold uppercase mt-1">{{ label }}</span></div>`
};

//asdasd

const AdjustableStat = {
    props: ['label', 'current', 'max'],
    emits: ['update:current', 'updateStat', 'sendStatRequest'],
    template: `
        <div class="cpr-panel bg-[#151515] cursor-pointer p-2 flex justify-between items-center" @click="$emit('sendStatRequest', label)">
            <button @click="$emit('update:current', current > 0 ? current - 1 : current); $emit('updateStat', label, current > 0 ? current - 1 : current);"
                    class="w-8 h-8 bg-gray-800 hover:bg-red-900 text-white font-bold rounded-sm">-
            </button>
            <div class="flex flex-col items-center"><span
                class="text-sm font-bold text-gray-400 uppercase tracking-widest">{{ label }}</span><span
                class="text-xl font-mono font-black text-white">{{ current }} <span class="text-gray-600 text-sm">/
                {{ max }}</span></span></div>
            <button @click="$emit('update:current', (current < max ? current + 1 : current)); $emit('updateStat', label, current < max ? current + 1 : current)"
                    class="w-8 h-8 bg-gray-800 hover:bg-green-900 text-white font-bold rounded-sm">+
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

const EquippedCard = {
    props: ['label', 'item', 'icon'],
    template: `
        <div class="cpr-panel cpr-equipped-box bg-[#111] p-2 flex items-center gap-3">
            <div class="w-8 h-8 bg-gray-900 flex items-center justify-center border border-gray-800 text-gray-500"><i
                :data-lucide="icon" class="w-4 h-4"></i></div>
            <div class="flex-1">
                <div class="text-[9px] text-gray-500 uppercase font-bold">{{ label }}</div>
                <div class="text-sm font-medium text-white truncate" :class="!item ? 'text-gray-600 italic' : ''">
                    {{ item ? item.name : 'Empty Slot' }}
                </div>
            </div>
        </div>`
};

export default {
    name: 'CharacterSheet',
    components: { NavBtn, SimpleStat, AdjustableStat, ResourceCounter, EquippedCard },
    setup() {
        const activeTab = ref('stats');
        const charData = ref(null);
        const loading = ref(true);
        const error = ref(null);
        const showPopup = ref(false);
        const popupMessage = ref('');

        // --- FETCH DATA ---
        onMounted(async () => {
            try {

                const getActors = await window.axios.post("/getactors");
                const actorId = await getActors.data.uuid;

                const response = await window.axios.post("/actorsheet", {"actorUuid" : actorId});
                charData.value = await response.data;
                loading.value = false;

                // Wait for DOM update then render icons
                setTimeout(() => window.lucide.createIcons(), 50);
            } catch (err) {
                console.error("Fetch Error:", err);
                error.value = err.response.data.error
                popupMessage.value = err.response.data.error;
                showPopup.value = true;
                loading.value = false;
                setTimeout(() => window.lucide.createIcons(), 50);
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

        const allCyberware = computed(() => {
            if (!charData.value) return [];
            return charData.value.data.items.filter(i =>
                ['cyberware', 'fashionware', 'cyberwareInternal', 'cyberwareExternal'].includes(i.type)
            );
        });

        const otherGear = computed(() => {
            if (!charData.value) return [];
            return charData.value.data.items.filter(i =>
                ['weapon', 'armor', 'gear', 'ammo'].includes(i.type) && i.system.usage !== 'equipped'
            );
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

        const equippedItems = computed(() => {
            if (!charData.value) return [];
            return charData.value.data.items.filter(i => i.system.usage === 'equipped');
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
            }).catch(err => {
                console.error('Error posting stat', err);
                popupMessage.value = "Neural uplink failed: Data packet lost.";
                showPopup.value = true;
                setTimeout(() => window.lucide.createIcons(), 50);
            });
        }

        //Deal with returns if needed for any kind of update or hook.
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

        onUpdated(() => {
            if (!loading.value && window.lucide) window.lucide.createIcons();
        });

        return {
            charData, loading, error, activeTab,
            stats, luck, emp, roleItem,
            groupedSkills, allCyberware, otherGear, equippedItems,
            calculateBase, findItem, formatCategory, getItemIcon, sendStatRequest, sendSkillRequest,
            sendRoleRequest, updateStat, showPopup, popupMessage
        };
    }
};
//https://www.camya.com/ddev-vite-hot-reloading-fix-2025/
</script>
