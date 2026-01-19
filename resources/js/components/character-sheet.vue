<template>
    <div class="min-h-screen pb-100 text-sans bg-[#050505]">

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

        <div v-if="!loading && charData && !error">
            <!-- Modals & Popups -->
            <cyberdeck-config
                v-if="showCyberdeckConfig"
                :active-cyberdeck="activeCyberdeck"
                :available-programs="availablePrograms"
                @close="showCyberdeckConfig = false"
                @install="installProgram"
                @uninstall="uninstallProgram">
            </cyberdeck-config>

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

            <resource-modal
                :show="showResourceModal"
                :resource-label="resourceLabel"
                :resource-value="resourceValue"
                :resource-max="resourceMax"
                :resource-color="resourceColor"
                :bulk-sign="bulkSign"
                :bulk-amount="bulkAmount"
                @close="closeResourceModal"
                @step="stepResource"
                @update:bulkSign="bulkSign = $event"
                @update:bulkAmount="bulkAmount = $event"
                @applyBulk="applyBulkChange"
                @save="commitResourceValue">
            </resource-modal>

            <combat-panel
                :show="showCombatPanel"
                :selected-weapon="controlState.selectedWeapon"
                :available-fire-modes="availableFireModes"
                :fire-mode="controlState.fireMode"
                :dv-loading="dvLoading"
                :dv-error="dvError"
                :dv-info="dvInfo"
                :reload-required="controlState.reloadRequired"
                @close="showCombatPanel = false"
                @roll-hit="rollHit"
                @roll-damage="rollDamage"
                @reload="reloadWeapon"
                @move="moveToken"
                @update:fireMode="controlState.fireMode = $event">
            </combat-panel>

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
                    <stats-tab
                        v-if="activeTab === 'stats'"
                        :active-tab="activeTab"
                        :char-data="charData"
                        :stats="stats"
                        :luck="luck"
                        :emp="emp"
                        :grouped-equipped="groupedEquipped"
                        @open-resource="openResourceModal"
                        @stat-request="sendStatRequest"
                        @update-stat="updateStat"
                        @facedown="sendFaceDown"
                        @deathsave="sendDeathSave"
                        @item-click="handleItemClick"
                    ></stats-tab>

                    <skills-tab
                        v-else-if="activeTab === 'skills'"
                        :active-tab="activeTab"
                        :role-item="roleItem"
                        :expanded-role-items="expandedRoleItems"
                        :grouped-skills="groupedSkills"
                        :collapsed-categories="collapsedCategories"
                        :calculate-base="calculateBase"
                        @role-request="sendRoleRequest"
                        @role-ability-request="sendRoleAbilityRequest"
                        @toggle-role="toggleRoleExpansion"
                        @toggle-category="toggleCategory"
                        @skill-request="sendSkillRequest"
                    ></skills-tab>

                    <gear-tab
                        v-else-if="activeTab === 'gear'"
                        :active-tab="activeTab"
                        :collapsed-categories="collapsedCategories"
                        :grouped-cyberware="groupedCyberware"
                        :grouped-gear="groupedGear"
                        :char-data="charData"
                        @toggle-category="toggleCategory"
                        @item-click="handleItemClick"
                    ></gear-tab>

                    <control-tab
                        v-else-if="activeTab === 'control'"
                        :active-tab="activeTab"
                        :control-state="controlState"
                        :mock-enemies="mockEnemies"
                        :grouped-equipped="groupedEquipped"
                        :available-fire-modes="availableFireModes"
                        :dv-loading="dvLoading"
                        :dv-error="dvError"
                        :dv-info="dvInfo"
                        :active-cyberdeck="activeCyberdeck"
                        :program-categories="programCategories"
                        :expanded-programs="expandedPrograms"
                        @roll-hit="rollHit"
                        @roll-damage="rollDamage"
                        @reload="reloadWeapon"
                        @move="moveToken"
                        @open-cyberdeck-config="showCyberdeckConfig = true"
                        @open-combat-panel="showCombatPanel = true"
                        @program-roll-request="sendRequest($event, '/programroll')"
                        @toggle-program-details="toggleProgramDetails"
                        @toggle-rez="toggleRez"
                        @roll-program="rollProgram"
                    ></control-tab>
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

            <roll-modifier-panel
                :show="showRollPanel"
                :loading="rollModsLoading"
                :error="rollModsError"
                :data="rollModsData"
                :has-weapon="!!controlState.selectedWeapon"
                @close="closeRollPanel"
                @refresh="fetchRollModifiers"
                @go-to-control="activeTab='control'; closeRollPanel()"
            ></roll-modifier-panel>

        </div>
    </div>
</template>

<script @cspNonce>
/**
 * CharacterSheet.vue
 * Main entry point for the cyberpunk character sheet.
 * Handles character data fetching, stat updates, skills, gear, and combat controls.
 */

import { computed, ref, triggerRef, watch } from "vue";

// --- COMPONENTS ---
import NavBtn from './character-sheet/NavBtn.vue';
import CyberdeckConfig from './character-sheet/CyberdeckConfig.vue';
import CombatPanel from './character-sheet/CombatPanel.vue';
import ControlTab from './character-sheet/ControlTab.vue';
import GearTab from './character-sheet/GearTab.vue';
import ResourceModal from './character-sheet/ResourceModal.vue';
import RollModifierPanel from './character-sheet/RollModifierPanel.vue';
import SkillsTab from './character-sheet/SkillsTab.vue';
import StatsTab from './character-sheet/StatsTab.vue';

// --- COMPOSABLES ---
import { useCharacterData } from '../composables/useCharacterData';
import { useCombat } from '../composables/useCombat';
import { useRollModifiers } from '../composables/useRollModifiers';
import { useResources } from '../composables/useResources';
import { useNetrunner } from '../composables/useNetrunner';

// --- DIRECTIVES & UTILS ---
import { vIcon } from '../directives/icon';
import { debounce, findItemInList, formatCategoryName } from '../utils/helpers';

export default {
    name: 'CharacterSheet',
    components: {CyberdeckConfig, NavBtn, CombatPanel, ControlTab, GearTab, ResourceModal, RollModifierPanel, SkillsTab, StatsTab },
    directives: { icon: vIcon },
    setup() {
        // --- COMPOSABLES ---
        const {
            charData,
            loading,
            error: dataError,
            getImageUrl,
            fetchCharacterData,
            refreshIcons
        } = useCharacterData();

        const activeTab = ref('stats');
        const showPopup = ref(false);
        const showImagePopup = ref(false);
        const showCombatPanel = ref(false);
        const popupMessage = ref('');
        const error = computed(() => dataError.value);

        // --- UTILS & HELPERS ---
        const throwError = (message) => {
            popupMessage.value = message;
            showPopup.value = true;
        };

        const debouncedFetchCharacterData = debounce(() => fetchCharacterData(), 1000);

        const sendRequest = (data, url) => {
            window.axios.post(url, data).then(response => {
                debouncedFetchCharacterData(); // Throttled refresh
                return response;
            }).catch(err => {
                console.error('Error posting request', err);
                popupMessage.value = "Neural uplink failed: Data packet lost.";
                showPopup.value = true;
                return err;
            });
        };

        const updateStat = (stat, value) => {
            const numericValue = Number(value);
            if (charData.value) {
                const key = stat.toLowerCase();
                const targetStat = charData.value.data.system.stats[key];
                if (targetStat) {
                    targetStat.value = numericValue;
                    triggerRef(charData);
                }
            }
            sendRequest({ label: stat, value: numericValue }, "/updatestat");
        };

        // --- CONTROL PANEL STATE ---
        const controlState = ref({
            spaceMode: 'meatspace',
            selectedTarget: null,
            selectedWeapon: null,
            selectedProgram: null,
            fireMode: 'Single',
            reloadRequired: false
        });

        watch(() => charData.value?.data.flags?.['cyberpunk-red-core']?.fightState, (newVal) => {
            if (newVal === 'Netspace') {
                controlState.value.spaceMode = 'netspace';
            } else if (newVal === 'Meatspace') {
                controlState.value.spaceMode = 'meatspace';
            }
        }, { immediate: true });

        const {
            mockEnemies,
            dvInfo,
            dvLoading,
            dvError,
            debouncedFetchDV,
            getVisibleTokens,
            moveToken,
            rollHit,
            rollDamage,
            checkReloadRequired,
            reloadWeapon,
            fetchDVForSelectedWeapon
        } = useCombat(controlState, charData, fetchCharacterData, throwError);

        const {
            showRollPanel,
            rollModsLoading,
            rollModsError,
            rollModsData,
            openRollPanel: openRollPanelBase,
            closeRollPanel,
            fetchRollModifiers
        } = useRollModifiers(controlState);

        const {
            activeCyberdeck,
            availablePrograms,
            programCategories,
            showCyberdeckConfig,
            expandedPrograms,
            isEffectivelyRezzed,
            canBeRezzed,
            installProgram,
            uninstallProgram,
            toggleRez,
            rollProgram: rollProgramBase,
            toggleProgramDetails
        } = useNetrunner(charData, sendRequest, throwError);

        const rollProgram = (program, activeCyberdeck) => rollProgramBase(program, activeCyberdeck, controlState);

        const openRollPanel = async () => {
            await openRollPanelBase();
            if (controlState.value.selectedWeapon) {
                debouncedFetchDV();
            }
        };

        const collapsedCategories = ref({});
        const expandedRoleItems = ref({});

        const logout = () => {
            window.axios.post('/logout').then(() => {
                window.location.href = '/login';
            });
        };

        const close = () => {
            if (charData.value) showPopup.value = false;
            else logout();
        };

        const {
            showResourceModal,
            resourceLabel,
            resourceValue,
            resourceMax,
            resourceColor,
            bulkSign,
            bulkAmount,
            openResourceModal,
            closeResourceModal,
            stepResource,
            applyBulkChange,
            commitResourceValue
        } = useResources(charData, updateStat);

        const debouncedGetVisibleTokens = debounce(getVisibleTokens, 200);

        // --- COMPUTED PROPERTIES ---

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

        const installedIds = computed(() => {
            const ids = new Set();
            if (!charData.value) return ids;
            charData.value.data.items.forEach(item => {
                if (item.system.installedItems && item.system.installedItems.list) {
                    item.system.installedItems.list.forEach(id => ids.add(id));
                }
            });
            return ids;
        });

        const groupedCyberware = computed(() => {
            if (!charData.value) return {};
            const cyberware = charData.value.data.items.filter(i =>
                ['cyberware', 'fashionware', 'cyberwareInternal', 'cyberwareExternal', 'cyberdeck'].includes(i.type) &&
                !installedIds.value.has(i._id)
            );
            const groups = {};
            cyberware.forEach(cw => {
                const type = cw.system.cyberwareType || 'Cyberdeck';
                if (!groups[type]) groups[type] = [];
                groups[type].push(cw);
            });
            return groups;
        });

        const groupedGear = computed(() => {
            if (!charData.value) return {};
            const groups = { weapon: [], armor: [], ammo: [], gear: [] };
            const items = charData.value.data.items.filter(i =>
                ['weapon', 'armor', 'gear', 'ammo'].includes(i.type) &&
                !installedIds.value.has(i._id)
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
            const groups = { weapon: [], armor: [], ammo: [], gear: [], installedPrograms: [] };

            const items = allItems.filter(i => {
                const isEquipped = i.system.equipped === 'equipped' || i.system.equipped === 'owned' || i.system.equipped === 'carried';
                const isToggledWeapon = i.system.isWeapon && i.system.usage === 'toggled';
                const isProgram = i.type === 'program';
                const isCyberdeck = i.type === 'cyberdeck';
                return (isEquipped || isToggledWeapon || isProgram || isCyberdeck) && !installedIds.value.has(i._id);
            });

            items.forEach(item => {
                if (item.system.isWeapon) {
                    groups['weapon'].push(item);
                    return;
                }
                if (groups[item.type]) groups[item.type].push(item);
                else if (!['cyberware', 'fashionware', 'cyberwareInternal', 'cyberwareExternal', 'cyberdeck', 'program'].includes(item.type)) {
                    groups.gear.push(item);
                }
            });

            // Ensure cyberdeck itself shows up in Gear if equipped and not installed in something else
            items.forEach(item => {
                if (item.type === 'cyberdeck' && !installedIds.value.has(item._id)) {
                    groups.gear.push(item);
                }
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

        // --- HELPER FUNCTIONS ---

        const calculateBase = (skill) => {
            if (!charData.value) return 0;
            const statVal = charData.value.data.system.stats[skill.system.stat]?.value || 0;
            return skill.system.level + statVal;
        };

        const findItem = (id) => findItemInList(id, charData.value?.data.items);

        const formatCategory = formatCategoryName;

        // --- REQUEST WRAPPERS ---
        const sendStatRequest = (stat) => sendRequest({ label: stat }, "/statrollrequest");
        const sendSkillRequest = (skill) => sendRequest({ label: skill }, "/skillrollrequest");
        const sendRoleRequest = (role) => sendRequest({ label: role, role: role, isAbility: false  }, "/rolerollrequest");
        const sendRoleAbilityRequest = (role, ability) => sendRequest({ label: ability, role: role, isAbility: true }, "/rolerollrequest");
        const sendDeathSave = () => sendRequest({}, "/deathsave");
        const sendFaceDown = () => sendRequest({}, "/facedown");

        const toggleCategory = (category) => {
            collapsedCategories.value[category] = !collapsedCategories.value[category];
            setTimeout(refreshIcons, 50);
        };

        const toggleRoleExpansion = (id) => {
            expandedRoleItems.value[id] = !expandedRoleItems.value[id];
            setTimeout(refreshIcons, 50);
        };

        const goToSettings = () => (window.location.href = '/settings');

        const availableFireModes = computed(() => {
            const modes = [
                { id: 'Single', label: 'Single', icon: 'locate' },
                { id: 'Aimed', label: 'Aimed', icon: 'locate-fixed' }
            ];
            const weapon = controlState.value.selectedWeapon;
            if (weapon && weapon.system && weapon.system.fireModes) {
                const fm = weapon.system.fireModes;
                if (typeof fm.autoFire === 'number' && fm.autoFire !== 0) {
                    modes.push({ id: 'Auto', label: `Auto (${fm.autoFire})`, icon: 'zap' });
                }
                if (fm.suppressiveFire) {
                    modes.push({ id: 'Suppress', label: 'Suppress', icon: 'shield-alert' });
                }
            }
            return modes;
        });

        // --- WATCHERS ---
        watch(activeTab, (newTab) => {
            if (newTab === 'control') {
                const prevSelectedId = controlState.value.selectedTarget?.id || null;
                if (prevSelectedId) {
                    const match = mockEnemies.value.find(t => t.id === prevSelectedId);
                    if (match) controlState.value.selectedTarget = match;
                }
                debouncedGetVisibleTokens();
            }
        });

        watch(() => controlState.value.selectedWeapon, (newWeapon) => {
            checkReloadRequired();
            if (newWeapon) {
                debouncedFetchDV();
                // Pre-fetch roll modifiers without opening the panel
                fetchRollModifiers();

                // Ensure current fireMode is still available for the new weapon
                const availableIds = availableFireModes.value.map(m => m.id);
                if (!availableIds.includes(controlState.value.fireMode)) {
                    controlState.value.fireMode = 'Single';
                }
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

        const handleItemClick = (item) => {
            if (item.system.isWeapon || item.type === 'weapon') {
                controlState.value.selectedWeapon = item;
                // activeTab.value = 'control';
            }
        };

        return {
            // State
            charData, loading, error, activeTab,
            stats, luck, emp, roleItem,
            groupedSkills, groupedCyberware, groupedGear, groupedEquipped,

            // Netrunner specific
            activeCyberdeck, availablePrograms, programCategories, showCyberdeckConfig,
            installProgram, uninstallProgram, toggleRez, rollProgram, canBeRezzed, isEffectivelyRezzed,
            expandedPrograms, toggleProgramDetails,

            // UI Helpers
            calculateBase, findItem, formatCategory, getImageUrl,
            showPopup, showImagePopup, showCombatPanel, popupMessage, collapsedCategories, toggleCategory, toggleRoleExpansion, expandedRoleItems,
            handleItemClick,

            // Actions
            sendStatRequest, sendSkillRequest, sendRoleRequest, sendRoleAbilityRequest, updateStat,
            sendDeathSave, sendFaceDown, sendRequest, refreshIcons, logout, close, goToSettings,

            // Combat
            mockEnemies, controlState, moveToken, rollHit, rollDamage, reloadWeapon, availableFireModes,
            dvInfo, dvLoading, dvError, fetchDVForSelectedWeapon,

            // Modals
            showRollPanel, rollModsLoading, rollModsError, rollModsData, openRollPanel, closeRollPanel, fetchRollModifiers,
            showResourceModal, resourceLabel, resourceValue, resourceMax, resourceColor, bulkSign, bulkAmount,
            openResourceModal, closeResourceModal, stepResource, applyBulkChange, commitResourceValue
        };
    }
};
</script>

<style scoped src="../../css/components/character-sheet.css"></style>

<style scoped>
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

/* Firing Mode List Animation */
.mode-list-move,
.mode-list-enter-active,
.mode-list-leave-active {
    transition: all 0.3s ease;
}
.mode-list-enter-from,
.mode-list-leave-to {
    opacity: 0;
    transform: scale(0.8);
}
.mode-list-leave-active {
    position: absolute;
    opacity: 0;
}
</style>
