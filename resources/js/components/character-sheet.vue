<template>
    <div class="min-h-screen pb-100 text-sans bg-cpr-black-deep">

        <div v-if="loading" class="flex h-screen w-screen items-center justify-center bg-cpr-black">
            <div class="flex flex-col items-center">
                <div class="text-cpr-yellow font-mono animate-pulse text-xl">INITIALIZING AGENT...</div>
                <div class="w-64 h-1 bg-cpr-black-deep overflow-hidden relative border border-cpr-border">
                    <div class="absolute inset-0 bg-cpr-red animate-[loading-bar_2s_infinite]"></div>
                </div>
                <div class="mt-4 text-[10px] text-cpr-text-dim font-mono uppercase tracking-[0.3em] italic">Initializing
                    Headless Session
                </div>
            </div>
        </div>

        <div v-else-if="error" class="flex h-screen items-center justify-center p-4 bg-cpr-black">
            <div class="w-full max-w-md">
                <div class="border-2 border-cpr-red bg-cpr-red-dark/10 p-6 shadow-[0_0_30px_rgba(220,38,38,0.2)]">
                    <div class="flex items-center gap-4 mb-4 border-b border-cpr-red pb-4">
                        <i v-icon data-lucide="alert-octagon" class="w-8 h-8 text-cpr-red"></i>
                        <h2 class="text-cpr-red font-black text-2xl uppercase tracking-tighter">Uplink Failed</h2>
                    </div>
                    <div class="text-cpr-text-main font-mono text-sm leading-relaxed mb-6">
                        {{ error }}
                    </div>
                    <div class="flex gap-4">
                        <button @click="reloadPage"
                                class="flex-1 py-2 bg-cpr-red text-black font-bold uppercase text-xs hover:bg-cpr-red transition-colors">
                            Retry Connection
                        </button>
                        <button @click="goToSettings"
                                class="flex-1 py-2 border border-cpr-red text-cpr-red font-bold uppercase text-xs hover:bg-cpr-red/10 transition-colors">
                            Agent Config
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

            <div v-if="showImagePopup" @click="showImagePopup = false" class="fixed inset-0 z-[110] flex items-center justify-center p-4 backdrop-blur-md bg-cpr-black/80">
                <div class="relative max-w-2xl w-full max-h-[90vh] flex flex-col items-center justify-center animate-in fade-in zoom-in duration-200 p-[env(safe-area-inset-top)_env(safe-area-inset-right)_env(safe-area-inset-bottom)_env(safe-area-inset-left)]" @click.stop>
                    <div class="absolute top-0 right-0 m-4 mt-[calc(1rem+env(safe-area-inset-top))] mr-[calc(1rem+env(safe-area-inset-right))]">
                        <button @click="showImagePopup = false" class="text-cpr-yellow/70 hover:text-cpr-yellow transition-colors bg-cpr-black/50 rounded-full p-2">
                            <i v-icon data-lucide="x" class="w-6 h-6"></i>
                        </button>
                    </div>
                    <img v-cpr-img:cpr-red="getImageUrl(charData.data.img)"
                         class="max-w-full max-h-[85vh] object-contain border-2 border-cpr-red shadow-[0_0_30px_rgba(220,38,38,0.4)]"
                         :alt="charData.data.name">
                    <div class="mt-4 text-center">
                        <h2 class="text-cpr-yellow font-bold uppercase tracking-widest text-lg">
                            {{ charData.data.name }}
                        </h2>
                        <div v-if="!isWssConnected"
                             class="text-cpr-red font-black text-xs mt-1 animate-pulse tracking-widest uppercase">
                            Offline Mode - Agent Synced via Local Cache
                        </div>
                    </div>
                </div>
            </div>

            <eurobucks-panel
                v-if="showEurobucksModal"
                :value="charData.data.system.wealth.value"
                :transactions="charData.data.system.transactions"
                class="fixed inset-0 z-[120] flex items-center justify-center p-6 bg-cpr-black/70 p-[env(safe-area-inset-top)_env(safe-area-inset-right)_env(safe-area-inset-bottom)_env(safe-area-inset-left)]"
                @updated="handleEurobucksUpdated"
                @click.self="showEurobucksModal = false">
            </eurobucks-panel>

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

            <roll-result-popup></roll-result-popup>
            <dice-history></dice-history>

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

            <header
                class="pb-3 pr-3 pl-3 pt-(nativephp-safe-area) bg-cpr-black/90 sticky top-(nativephp-safe-area - --inset-top) z-50 border-b border-cpr-yellow-dark backdrop-blur-md">
                <div class="pt-3 flex items-center gap-3 justify-between">
                    <div class="relative w-12 h-12 shrink-0 cursor-pointer group" @click="showImagePopup = true">
                        <div
                            class="absolute inset-0 border border-[var(--cpr-yellow)] bg-cpr-black-deep flex items-center justify-center overflow-hidden">
                            <img v-if="charData.data.img" v-cpr-img:cpr-teal="getImageUrl(charData.data.img)"
                                 class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                                 :alt="charData.data.name">
                            <span v-else class="text-xl font-bold text-cpr-yellow">{{ charData.data.name[0] }}</span>
                        </div>
                        <div class="absolute inset-0 bg-cpr-yellow/0 group-hover:bg-cpr-yellow/20 transition-colors duration-300 flex items-center justify-center">
                            <i v-icon data-lucide="maximize-2" class="w-4 h-4 text-cpr-yellow opacity-0 group-hover:opacity-100 transition-opacity"></i>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <h1 v-if="!isWssConnected" class="text-xl font-bold uppercase truncate leading-none text-cpr-yellow">
                                <span class="px-2 py-0.5 bg-cpr-red text-black text-[10px] font-black uppercase tracking-tighter rounded-sm animate-pulse mr-2">
                                    Offline
                                </span>
                                {{ charData.data.name }}
                            </h1>
                            <h1 v-else class="text-xl font-bold uppercase truncate leading-none text-cpr-yellow">
                                {{ charData.data.name }}
                            </h1>
                        </div>
                        <span class="text-[10px] text-cpr-yellow uppercase font-bold tracking-widest">{{
                                charData.data.system.roleInfo.activeRole
                            }}</span>
                    </div>
                    <div class="shrink-0 flex items-center gap-2">
                        <button @click="() => refreshSheet({ force: true })" class="p-2 border border-cpr-border text-cpr-text-muted hover:text-cpr-yellow hover:border-cpr-yellow transition-colors" title="Refresh Sheet">
                            <i v-icon data-lucide="refresh-ccw" class="w-5 h-5 [margin-top:-3px]"></i>
                        </button>
                        <button @click="rollInitiative"
                                class="group relative flex items-center gap-2 px-2.5 py-2.5 border border-cpr-red bg-cpr-black text-cpr-red hover:bg-cpr-red hover:text-black transition-all shadow-[0_0_15px_rgba(220,38,38,0.2)] hover:shadow-[0_0_25px_rgba(220,38,38,0.5)]"
                                title="Initiative Roll">
                            <i v-icon data-lucide="swords" class="w-5 h-5 animate-pulse group-hover:animate-none"></i>
                            <span
                                class="text-xs font-black uppercase tracking-widest hidden sm:inline">Initiative</span>
                            <!-- Spooky Glow Overlay -->
                            <div
                                class="absolute inset-0 bg-cpr-red/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        </button>

                        <button @click="openRollPanel"
                                class="p-2 border border-cpr-yellow text-cpr-yellow hover:bg-cpr-yellow hover:text-black transition-colors"
                                title="Die Roller">
                            <i v-icon data-lucide="dice-6" class="w-5 h-5 [margin-top:-3px]"></i>
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
                        :grouped-injuries="groupedInjuries"
                        :collapsed-categories="collapsedCategories"
                        :refresh-all="refreshAll"
                        :grouped-armour="groupedArmour"
                        @open-resource="openResourceModal"
                        @stat-request="sendStatRequest"
                        @update-stat="updateStat"
                        @facedown="sendFaceDown"
                        @deathsave="sendDeathSave"
                        @item-click="handleItemClick"
                        @equip-click="openEquipModal"
                        @consume-click="openConsumeModal"
                        @toggle-category="toggleCategory"
                        @open-eurobucks="showEurobucksModal = true"
                    ></stats-tab>

                    <skills-tab
                        v-else-if="activeTab === 'skills'"
                        :active-tab="activeTab"
                        :role-item="roleItem"
                        :expanded-role-items="expandedRoleItems"
                        :expanded-skills="expandedSkills"
                        :grouped-skills="groupedSkills"
                        :collapsed-categories="collapsedCategories"
                        :calculate-base="calculateBase"
                        :skills-descriptions="skillsDescriptions"
                        @role-request="sendRoleRequest"
                        @role-ability-request="sendRoleAbilityRequest"
                        @toggle-role="toggleRoleExpansion"
                        @toggle-skill="toggleSkillExpansion"
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
                        :refresh-all="refreshAll"
                        @toggle-category="toggleCategory"
                        @item-click="handleItemClick"
                        @equip-click="openEquipModal"
                        @consume-click="openConsumeModal"
                    ></gear-tab>

                    <lifepath-tab
                        v-else-if="activeTab === 'lifepath'"
                        :active-tab="activeTab"
                        :char-data="charData"
                        @update-notes="updateNotes"
                    ></lifepath-tab>

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
                        :refresh-all="refreshAll"
                        @roll-hit="rollHit"
                        @roll-damage="rollDamage"
                        @reload="reloadWeapon(refreshAll)"
                        @move="moveToken"
                        @open-cyberdeck-config="showCyberdeckConfig = true"
                        @open-combat-panel="showCombatPanel = true"
                        @program-roll-request="sendRequest($event, '/programroll')"
                        @toggle-program-details="toggleProgramDetails"
                        @toggle-rez="toggleRez"
                        @roll-program="rollProgram"
                    ></control-tab>

                    <net-tab
                        v-else-if="activeTab === 'net'"
                        :net-pool-data="netPoolData"
                        :get-image-url="getImageUrl"
                    ></net-tab>
                </transition>
            </main>

            <nav
                class="fixed bottom-0 inset-x-0 bg-cpr-black-deep border-t border-cpr-red-dark/30 flex justify-around p-2 pb-6 z-50">
                <nav-btn icon="activity" label="Stats" :active="activeTab === 'stats'"
                         @click="activeTab = 'stats'"></nav-btn>
                <nav-btn icon="brain" label="Skills" :active="activeTab === 'skills'"
                         @click="activeTab = 'skills'"></nav-btn>
                <nav-btn icon="shield" label="Gear" :active="activeTab === 'gear'"
                         @click="activeTab = 'gear'"></nav-btn>
                <nav-btn icon="users" label="LP" :active="activeTab === 'lifepath'"
                         @click="activeTab = 'lifepath'"></nav-btn>
                <nav-btn icon="gamepad-2" label="Control" :active="activeTab === 'control'"
                         @click="activeTab = 'control'"></nav-btn>
                <nav-btn v-if="netPoolData.length > 0" icon="globe" label="CitiNet" :active="activeTab === 'net'"
                         @click="activeTab = 'net'"></nav-btn>
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

            <!-- Global Popup Modal -->
            <div v-if="showPopup"
                 class="fixed inset-0 z-[200] flex items-center justify-center p-6 bg-cpr-black/80 backdrop-blur-sm"
                 @click="showPopup = false">
                <div class="w-full max-w-xs bg-cpr-black-deep border border-cpr-red-dark shadow-[0_0_30px_rgba(220,38,38,0.2)]"
                     @click.stop>
                    <div class="flex items-center justify-between px-3 py-2 border-b border-cpr-red-dark/30 bg-cpr-red-dark/10">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-cpr-red">System Notification</span>
                        <button class="text-cpr-text-muted hover:text-cpr-yellow transition-colors" @click="showPopup = false">
                            <i v-icon data-lucide="x" class="w-4 h-4"></i>
                        </button>
                    </div>
                    <div class="p-6 text-center space-y-4">
                        <div class="text-lg font-black font-mono text-cpr-yellow tracking-tight leading-tight">
                            {{ popupMessage }}
                        </div>
                        <button
                            class="w-full py-2 bg-cpr-red/10 border border-cpr-red/40 text-cpr-red text-[10px] font-bold uppercase tracking-widest hover:bg-cpr-red hover:text-black transition-all"
                            @click="showPopup = false">
                            Acknowledge
                        </button>
                    </div>
                </div>
            </div>

            <!-- Item Consume Modal -->
            <div v-if="showConsumeModal"
                 class="fixed inset-0 z-[200] flex items-center justify-center p-6 bg-cpr-black/80 backdrop-blur-sm"
                 @click="showConsumeModal = false">
                <div class="w-full max-w-xs bg-cpr-black-deep border border-cpr-yellow shadow-[0_0_30px_rgba(234,179,8,0.2)]"
                     @click.stop>
                    <div class="flex items-center justify-between px-3 py-2 border-b border-cpr-yellow-dark/30 bg-cpr-yellow-dark/10">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-cpr-yellow">Consume Item</span>
                        <button class="text-cpr-text-muted hover:text-cpr-yellow transition-colors" @click="showConsumeModal = false">
                            <i v-icon data-lucide="x" class="w-4 h-4"></i>
                        </button>
                    </div>
                    <div class="p-6 text-center space-y-4">
                        <div v-if="consumingItem" class="text-lg font-black font-mono text-cpr-yellow tracking-tight leading-tight mb-4">
                            {{ consumingItem.name }}
                        </div>
                        <div class="text-sm text-cpr-text-muted mb-6">
                            Are you sure you want to consume this item?
                        </div>
                        <div class="grid grid-cols-1 gap-2">
                            <button @click="confirmConsume"
                                    class="w-full py-3 bg-cpr-yellow text-black border border-cpr-yellow transition-all flex items-center justify-center gap-3 uppercase font-bold tracking-widest text-xs hover:bg-cpr-yellow-dark">
                                <i v-icon data-lucide="check" class="w-5 h-5 pointer-events-none"></i>
                                Confirm
                            </button>
                        </div>
                        <button
                            class="w-full py-2 mt-4 bg-cpr-black-elevated border border-cpr-border text-cpr-text-muted text-[10px] font-bold uppercase tracking-widest hover:text-cpr-yellow transition-all"
                            @click="showConsumeModal = false">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>

            <!-- Item Equip Modal -->
            <div v-if="showEquipModal"
                 class="fixed inset-0 z-[200] flex items-center justify-center p-6 bg-cpr-black/80 backdrop-blur-sm"
                 @click="showEquipModal = false">
                <div
                    class="w-full max-w-xs bg-cpr-black-deep border border-cpr-teal shadow-[0_0_30px_rgba(20,184,166,0.2)]"
                    @click.stop>
                    <div
                        class="flex items-center justify-between px-3 py-2 border-b border-cpr-teal-dark/30 bg-cpr-teal-dark/10">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-cpr-teal">Update Equipment State</span>
                        <button class="text-cpr-text-muted hover:text-cpr-yellow transition-colors" @click="showEquipModal = false">
                            <i v-icon data-lucide="x" class="w-4 h-4"></i>
                        </button>
                    </div>
                    <div class="p-6 text-center space-y-4">
                        <div v-if="editingItem" class="text-lg font-black font-mono text-cpr-yellow tracking-tight leading-tight mb-4">
                            {{ editingItem.name }}
                        </div>
                        <div class="grid grid-cols-1 gap-2">
                            <button v-for="state in ['owned', 'carried', 'equipped']" :key="state"
                                    @click="updateEquipState(state)"
                                    class="w-full py-3 border transition-all flex items-center justify-center gap-3 uppercase font-bold tracking-widest text-xs"
                                    :class="editingItem?.system.equipped === state ? 'bg-cpr-teal text-black border-cpr-teal' : 'bg-cpr-black-deep border-cpr-border text-cpr-text-dim hover:text-cpr-yellow hover:border-cpr-teal-dark'">
                                <i v-icon :data-lucide="getEquipIconForState(state)" class="w-5 h-5 pointer-events-none"></i>
                                {{ state }}
                            </button>
                        </div>
                        <button
                            class="w-full py-2 mt-4 bg-cpr-black-elevated border border-cpr-border text-cpr-text-muted text-[10px] font-bold uppercase tracking-widest hover:text-cpr-yellow transition-all"
                            @click="showEquipModal = false">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>

<script @cspNonce>
/**
 * CharacterSheet.vue
 * Main entry point for the cyberpunk character sheet.
 * Handles character data fetching, stat updates, skills, gear, and combat controls.
 */

import {computed, ref, triggerRef, watch} from "vue";

// --- COMPONENTS ---
import NavBtn from './character-sheet/NavBtn.vue';
import CyberdeckConfig from './character-sheet/CyberdeckConfig.vue';
import CombatPanel from './character-sheet/CombatPanel.vue';
import ControlTab from './character-sheet/ControlTab.vue';
import GearTab from './character-sheet/GearTab.vue';
import LifepathTab from './character-sheet/LifepathTab.vue';
import ResourceModal from './character-sheet/ResourceModal.vue';
import RollModifierPanel from './character-sheet/RollModifierPanel.vue';
import SkillsTab from './character-sheet/SkillsTab.vue';
import NetTab from './character-sheet/NetTab.vue';
import EurobucksPanel from './character-sheet/EurobucksPanel.vue';
import DiceHistory from './DiceHistory.vue';
import RollResultPopup from './RollResultPopup.vue';
import skillsDescriptions from '../../../skills.json';

// --- COMPOSABLES ---
import {useCharacterData} from '../composables/useCharacterData';
import {useCombat} from '../composables/useCombat';
import {useRollModifiers} from '../composables/useRollModifiers';
import {useResources} from '../composables/useResources';
import {useNetrunner} from '../composables/useNetrunner';
import {useNetPool} from '../composables/useNetPool';
import {useEchoListener} from '../utils/listener';

// --- DIRECTIVES & UTILS ---
import {vIcon} from '../directives/icon';
import {debounce, findItemInList, formatCategoryName} from '../utils/helpers';
import {isMyRequest} from '../utils/requests';

export default {
    name: 'CharacterSheet',
    components: {
        CyberdeckConfig,
        NavBtn,
        CombatPanel,
        ControlTab,
        GearTab,
        LifepathTab,
        ResourceModal,
        RollModifierPanel,
        SkillsTab,
        NetTab,
        EurobucksPanel,
        DiceHistory,
        RollResultPopup
    },
    directives: {icon: vIcon},
    setup() {
        // --- COMPOSABLES ---
        const {
            charData,
            loading,
            isWssConnected,
            error: dataError,
            agentLink: localAgentLink,
            isGM,
            getImageUrl,
            fetchCharacterData,
            refreshIcons,
            applyDelta,
            refreshSheet,
            rollMode
        } = useCharacterData();

        const {
            netPoolData,
            fetchNetPool,
            handleNetPoolData
        } = useNetPool();

        const activeTab = ref('stats');
        const showPopup = ref(false);
        const showEquipModal = ref(false);
        const showConsumeModal = ref(false);
        const editingItem = ref(null);
        const consumingItem = ref(null);
        const showImagePopup = ref(false);
        const showCombatPanel = ref(false);
        const showEurobucksModal = ref(false);
        const popupMessage = ref('');
        const error = computed(() => dataError.value);

        // --- INITIAL DATA LOAD ---
        fetchNetPool();

        // --- UTILS & HELPERS ---
        const throwError = (message) => {
            popupMessage.value = message;
            showPopup.value = true;
        };

        const sendRequest = (data, url) => {
            const payload = {...data, rollMode: rollMode.value};
            window.axios.post(url, payload).then(response => {
                // Automatic refresh removed as requested.
                if (response.data && response.data.data) {
                    let rollData = response.data.data;
                    // Handle list of rolls from getRolls(limit: 5)
                    if (rollData.data && Array.isArray(rollData.data) && rollData.data.length > 0) {
                        const lastRoll = rollData.data[0];
                        popupMessage.value = `Roll Result: ${lastRoll.rollTotal}`;
                        showPopup.value = true;
                    } else if (typeof rollData === 'number') {
                        popupMessage.value = `Roll Result: ${rollData}`;
                        showPopup.value = true;
                    }
                }
                return response;
            }).catch(err => {
                console.error('Error posting request', err);
                popupMessage.value = "Uplink failed: Data packet lost.";
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
            sendRequest({label: stat, value: numericValue}, "/updatestat");
        };

        const openEquipModal = (item) => {
            editingItem.value = item;
            showEquipModal.value = true;
        };

        const openConsumeModal = (item) => {
            consumingItem.value = item;
            showConsumeModal.value = true;
        };

        const getEquipIconForState = (state) => {
            switch (state) {
                case 'equipped':
                    return 'hand';
                case 'carried':
                    return 'briefcase';
                case 'owned':
                    return 'circle';
                default:
                    return 'help-circle';
            }
        };

        const updateEquipState = async (state) => {
            if (!editingItem.value) return;
            const itemId = editingItem.value._id;

            // Close modal immediately for responsiveness
            showEquipModal.value = false;
            editingItem.value = null;

            try {
                await window.axios.post('/item-equip', {
                    itemId: itemId,
                    state: state
                });
                // Don't call refreshAll() here - the SHEET_DELTA websocket event
                // from Foundry will update the UI via applyDelta() automatically
            } catch (err) {
                console.error("Error updating equip state:", err);
                // On error, refresh to restore correct state
                refreshAll();
            }
        };

        const confirmConsume = async () => {
            if (!consumingItem.value) return;
            const itemId = consumingItem.value._id;

            showConsumeModal.value = false;
            consumingItem.value = null;

            try {
                await window.axios.post("/consume-item", {
                    itemId: itemId
                });
                refreshAll();
            } catch (err) {
                console.error("Error consuming item:", err);
                refreshAll();
            }
        };

        const rollInitiative = () => {
            sendRequest({}, "/initiativeroll");
        };

        const updateNotes = (notes) => {
            if (charData.value) {
                if (!charData.value.data.system.information) {
                    charData.value.data.system.information = {};
                }
                charData.value.data.system.information.notes = notes;
            }
            sendRequest({notes: notes}, "/updatenotes");
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

        // Listen for responses from Foundry
        const handleFoundryResponse = (e) => {
            const payload = e.payload || e;

            handleNetPoolData(payload);

            if (payload.type === 'DICE_ROLL_EVENT' || payload.type === 'ROLL_RESULT') {
                const incomingLink = (payload.agentLink || payload.agent_link || '').replace(/\/$/, '').toLowerCase();
                const myLink = (localAgentLink.value || '').replace(/\/$/, '').toLowerCase();
                const isMyRoll = (myLink && incomingLink === myLink) || (payload.requestId && isMyRequest(payload.requestId));

                if (isMyRoll) {
                    // It's my roll: trigger the big popup
                    const isDamage = /\(Damage\)/i.test(payload.label || '');
                    let details = '';
                    if ((payload.label || '').includes('Attack')) {
                        if (typeof payload.dv !== 'undefined') {
                            const dvText = typeof payload.dv === 'number' ? payload.dv : String(payload.dv);
                            const outcome = payload.outcome || '';
                            details = `DV ${dvText} — ${outcome}`.trim();
                        }
                    }
                    window.emitter.emit('show-roll-result', {
                        title: payload.label || payload.rawRoll?.rollTitle || 'Roll Result',
                        result: payload.total,
                        formula: payload.rawRoll?._roll?.formula || payload.rawRoll?.formula || '',
                        flavor: payload.rawRoll?.statName || payload.rawRoll?.rollTitle || '',
                        isCriticalSuccess: payload.isCriticalSuccess,
                        isCriticalFailure: payload.isCriticalFailure,
                        raw: payload.rawRoll,
                        details,
                        type: isDamage ? 'damage' : 'default'
                    });
                } else {
                    // It's someone else's roll: trigger the small popup
                    window.emitter.emit('dice-roll-event', payload);
                }
            }

            if (payload.type === 'CONFIRMATION') {
                // Potential confirmation handling
            }
        };

        useEchoListener(handleFoundryResponse);


        watch(() => charData.value?.data.flags?.['cyberpunk-red-core']?.fightState, (newVal) => {
            if (newVal === 'Netspace') {
                controlState.value.spaceMode = 'netspace';
            } else if (newVal === 'Meatspace') {
                controlState.value.spaceMode = 'meatspace';
            }
        }, {immediate: true});

        const {
            mockEnemies,
            dvInfo,
            dvLoading,
            dvError,
            debouncedFetchDV,
            getVisibleTokens,
            debouncedGetVisibleTokens,
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

        fetchRollModifiers();

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
            rollProgram: baseRollProgram,
            toggleProgramDetails
        } = useNetrunner(charData, sendRequest, throwError);

        const rollProgram = (program, activeDeck) => {
            if (controlState) controlState.value.selectedProgram = program;
            window.axios.post('/programroll', {
                cyberdeckId: activeDeck._id,
                programId: program._id,
                programName: program.name,
                type: program.type,
                executionType: (['defender', 'booster', 'blackice'].some(c => program.system.class.includes(c)) ? "def" : "atk"),
                rollMode: rollMode.value
            }).then(response => {
                // Automatic refresh removed as requested.
                if (response.data && response.data.data) {
                    let rollData = response.data.data;
                    // Handle list of rolls from getRolls(limit: 5)
                    if (rollData.data && Array.isArray(rollData.data) && rollData.data.length > 0) {
                        const lastRoll = rollData.data[0];
                        popupMessage.value = `Roll Result: ${lastRoll.rollTotal}`;
                        showPopup.value = true;
                    } else if (typeof rollData === 'number') {
                        popupMessage.value = `Roll Result: ${rollData}`;
                        showPopup.value = true;
                    }
                }
            });
        };

        const openRollPanel = async () => {
            await openRollPanelBase();
            if (controlState.value.selectedWeapon) {
                debouncedFetchDV();
            }
        };

        const collapsedCategories = ref({});
        const expandedRoleItems = ref({});
        const expandedSkills = ref({});

        const toggleSkillExpansion = (skillId) => {
            expandedSkills.value[skillId] = !expandedSkills.value[skillId];
            setTimeout(refreshIcons, 50);
        };

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

        // --- COMPUTED PROPERTIES ---

        const stats = computed(() => {
            if (!charData.value) return {};
            const {luck, emp, ...rest} = charData.value.data.system.stats;
            return rest;
        });

        const luck = computed(() => charData.value?.data.system.stats.luck || {value: 0, max: 0});
        const emp = computed(() => charData.value?.data.system.stats.emp || {value: 0, max: 0});

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

        const groupedInjuries = computed(() => {
            if (!charData.value) return {head: [], body: []};
            const injuries = charData.value.data.items.filter(i => i.type === 'criticalInjury');
            const groups = {head: [], body: []};
            injuries.forEach(injury => {
                const location = injury.system.location || 'body';
                if (groups[location]) groups[location].push(injury);
                else groups.body.push(injury);
            });
            return groups;
        });

        const groupedGear = computed(() => {
            if (!charData.value) return {};
            const groups = {weapon: [], armor: [], ammo: [], gear: [], drug: []};
            const items = charData.value.data.items.filter(i =>
                ['weapon', 'armor', 'gear', 'ammo', 'drug'].includes(i.type) &&
                !installedIds.value.has(i._id)
            );
            items.forEach(item => {
                if (groups[item.type]) groups[item.type].push(item);
                else groups.gear.push(item);
            });
            return groups;
        });

        const groupedArmour = computed(() => {
            if (!charData.value) return {head: [], body: [], shield: [], hasArmour: false};
            const allArmour = charData.value.data.items.filter(i => i.type === 'armor');
            const groups = {head: [], body: [], shield: [], hasArmour: false}
            allArmour.forEach( armour => {
                if (armour.system.equipped !== "equipped") {
                    return;
                }
                if (armour.system.isHeadLocation === true) {
                    groups['head'].push(armour)
                    groups["hasArmour"] = true
                }
                if (armour.system.isBodyLocation === true) {
                    groups['body'].push(armour)
                    groups["hasArmour"] = true
                }
                if (armour.system.isShield === true) {
                    groups['shield'].push(armour)
                    groups["hasArmour"] = true
                }
            })
            return groups;
        });

        const groupedEquipped = computed(() => {
            if (!charData.value) return {};
            const allItems = charData.value.data.items;
            const groups = {weapon: [], armor: [], ammo: [], gear: [], drug: [], installedPrograms: []};
            const items = allItems.filter(i => {
                const isEquipped = i.system.equipped === 'equipped' || i.system.equipped === 'carried';
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
        const sendStatRequest = (stat) => sendRequest({label: stat}, "/statrollrequest");
        const sendSkillRequest = (skill) => sendRequest({label: skill}, "/skillrollrequest");
        const sendRoleRequest = (role) => sendRequest({label: role, role: role, isAbility: false}, "/rolerollrequest");
        const sendRoleAbilityRequest = (role, ability) => sendRequest({
            label: ability,
            role: role,
            isAbility: true
        }, "/rolerollrequest");
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

        const reloadPage = () => (window.location.href = '');

        const availableFireModes = computed(() => {
            const modes = [
                {id: 'Single', label: 'Single', icon: 'locate'},
                {id: 'Aimed', label: 'Aimed', icon: 'locate-fixed'}
            ];
            const weapon = controlState.value.selectedWeapon;
            if (weapon && weapon.system && weapon.system.fireModes) {
                const fm = weapon.system.fireModes;
                if (typeof fm.autoFire === 'number' && fm.autoFire !== 0) {
                    modes.push({id: 'Auto', label: `Auto (${fm.autoFire})`, icon: 'zap'});
                }
                if (fm.suppressiveFire) {
                    modes.push({id: 'Suppress', label: 'Suppress', icon: 'shield-alert'});
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

                // Ensure current fireMode is still available for the new weapon
                const availableIds = availableFireModes.value.map(m => m.id);
                if (!availableIds.includes(controlState.value.fireMode)) {
                    controlState.value.fireMode = 'Single';
                }
            } else {
                dvInfo.value = null;
            }
            // Refresh roll modifiers whether we have a weapon or not
            fetchRollModifiers();
        }, {deep: true});

        watch(() => charData.value, (newData, oldData) => {
            if (newData && controlState.value.selectedWeapon) {
                const updatedWeapon = newData.data.items.find(i => i._id === controlState.value.selectedWeapon._id);
                if (updatedWeapon) {
                    controlState.value.selectedWeapon = updatedWeapon;
                    checkReloadRequired();
                }
            }
            // Refresh roll modifiers if HP or critical injuries change
            if (newData && oldData) {
                const oldHp = oldData.data.system.derivedStats?.hp?.value;
                const newHp = newData.data.system.derivedStats?.hp?.value;
                const oldInjuries = (oldData.data.items || []).filter(i => i.type === 'criticalInjury').length;
                const newInjuries = (newData.data.items || []).filter(i => i.type === 'criticalInjury').length;

                if (oldHp !== newHp || oldInjuries !== newInjuries) {
                    fetchRollModifiers();
                }
            }
        }, {deep: true});

        const refreshAll = () => {
            fetchCharacterData();
            if (activeTab.value === 'control') {
                getVisibleTokens();
                if (controlState.value.selectedWeapon && controlState.value.selectedTarget) {
                    fetchDVForSelectedWeapon();
                }
            }
        };

        const handleItemClick = (item) => {
            if (item.system.isWeapon || item.type === 'weapon') {
                controlState.value.selectedWeapon = item;
                // activeTab.value = 'control';
            }
        };

        const handleEurobucksUpdated = () => {
            showEurobucksModal.value = false;
            refreshSheet();
        };

        return {
            // State
            charData,
            loading,
            error,
            activeTab,
            stats,
            luck,
            emp,
            roleItem,
            groupedSkills,
            groupedCyberware,
            groupedGear,
            groupedInjuries,
            groupedEquipped,
            groupedArmour,

            // Netrunner specific
            activeCyberdeck,
            availablePrograms,
            programCategories,
            showCyberdeckConfig,
            installProgram,
            uninstallProgram,
            toggleRez,
            rollProgram,
            canBeRezzed,
            isEffectivelyRezzed,
            expandedPrograms,
            toggleProgramDetails,

            // UI Helpers
            calculateBase,
            findItem,
            formatCategory,
            getImageUrl,
            skillsDescriptions,
            showPopup,
            showImagePopup,
            showCombatPanel,
            popupMessage,
            collapsedCategories,
            toggleCategory,
            toggleRoleExpansion,
            toggleSkillExpansion,
            expandedRoleItems,
            expandedSkills,
            handleItemClick,
            handleEurobucksUpdated,

            // Actions
            sendStatRequest,
            sendSkillRequest,
            sendRoleRequest,
            sendRoleAbilityRequest,
            updateStat,
            updateNotes,
            rollInitiative,
            sendDeathSave,
            sendFaceDown,
            sendRequest,
            refreshIcons,
            logout,
            close,
            goToSettings,
            refreshSheet,
            isWssConnected,
            reloadPage,
            netPoolData,

            // Combat
            mockEnemies,
            controlState,
            moveToken,
            rollHit,
            rollDamage,
            reloadWeapon,
            availableFireModes,
            dvInfo,
            dvLoading,
            dvError,
            fetchDVForSelectedWeapon,
            refreshAll,

            // Modals
            showRollPanel,
            rollModsLoading,
            rollModsError,
            rollModsData,
            openRollPanel,
            closeRollPanel,
            fetchRollModifiers,
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
            commitResourceValue,
            showEquipModal,
            editingItem,
            openEquipModal,
            updateEquipState,
            getEquipIconForState,
            showConsumeModal,
            consumingItem,
            openConsumeModal,
            confirmConsume,
            showEurobucksModal
        };
    }
};
</script>

<style @cspNonce scoped src="../../css/components/character-sheet.css"></style>

<style @cspNonce scoped>
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
    color: var(--cpr-red);
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
