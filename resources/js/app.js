import './bootstrap';

import {createApp, ref, computed, onMounted, onUpdated} from "vue";
import CharacterSheet from "./components/character-sheet.vue";
// --- COMPONENTS ---
const NavBtn = {
    props: ['icon', 'label', 'active'],
    template: `
        <button class="flex flex-col items-center gap-1 w-16 transition-colors"
                :class="active ? 'text-red-500' : 'text-gray-600'"><i :data-lucide="icon" class="w-6 h-6"></i><span
            class="text-[9px] font-bold uppercase tracking-wider">{{ label }}</span></button>`
};

const SimpleStat = {
    props: ['label', 'value'],
    template: `
        <div class="cpr-panel bg-[#151515] p-2 flex flex-col items-center justify-center cpr-stat-box"><span
            class="text-xl font-bold text-white leading-none">{{ value }}</span><span
            class="text-[10px] text-[var(--cpr-red)] font-bold uppercase mt-1">{{ label }}</span></div>`
};

const AdjustableStat = {
    props: ['label', 'current', 'max'],
    emits: ['update:current'],
    template: `
        <div class="cpr-panel bg-[#151515] p-2 flex justify-between items-center">
            <button @click="$emit('update:current', current - 1)"
                    class="w-8 h-8 bg-gray-800 hover:bg-red-900 text-white font-bold rounded-sm">-
            </button>
            <div class="flex flex-col items-center"><span
                class="text-sm font-bold text-gray-400 uppercase tracking-widest">{{ label }}</span><span
                class="text-xl font-mono font-black text-white">{{ current }} <span class="text-gray-600 text-sm">/
                {{ max }}</span></span></div>
            <button @click="$emit('update:current', current + 1)"
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

const app = createApp({
    components: {NavBtn, SimpleStat, AdjustableStat, ResourceCounter, EquippedCard},
    setup() {

        const activeTab = ref('stats');
        //const charData = ref(null);
        const loading = ref(true);
        const error = ref(null);

// --- FETCH DATA ---
//         onMounted(async () => {
//             try {
//                 // Point this to your new "Glue" PHP file
//                 const response = await fetch('src/sheet-api.php');
//
//                 if (!response.ok) throw new Error('Network response was not ok');
//
//                 const json = await response.json();
//                 charData.value = json;
//                 loading.value = false;
//
//                 // Wait for DOM update then render icons
//                 setTimeout(() => lucide.createIcons(), 50);
//             } catch (err) {
//                 console.error("Fetch Error:", err);
//                 error.value = "Failed to load character data.";
//                 loading.value = false;
//             }
//         });

        const charData = ref({
            "requestId": "entity_1766946329442",
            "clientId": "foundry-xECODN3n8rO4E5Cj",
            "type": "entity-result",
            "uuid": "Actor.UGkNHSnOykhJEjPm",
            "data": {
                "name": "Jim",
                "system": {
                    "stats": {
                        "int": { "value": 6 }, "ref": { "value": 8 }, "dex": { "value": 7 },
                        "tech": { "value": 4 }, "cool": { "value": 6 }, "will": { "value": 5 },
                        "luck": { "value": 6, "max": 6 }, "move": { "value": 6 },
                        "body": { "value": 7 }, "emp": { "value": 4, "max": 6 }
                    },
                    "derivedStats": {
                        "hp": { "value": 35, "max": 40 },
                        "humanity": { "value": 42, "max": 60 },
                        "deathSave": { "value": 0 }
                    },
                    "reputation": { "value": 2 },
                    "wealth": { "value": 1500 },
                    "improvementPoints": { "value": 80 },
                    "roleInfo": { "activeRole": "Rockerboy", "activeNetRole": "lFspsxudEli10fYh" },
                    "externalData": {
                        "currentWeapon": { "id": "wep123", "value": 0 },
                        "currentArmorBody": { "id": "arm123", "value": 0 },
                        "currentArmorHead": { "id": "", "value": 0 },
                        "currentArmorShield": { "id": "", "value": 0 }
                    }
                },
                "items": [
                    { "_id": "lFspsxudEli10fYh", "name": "Rockerboy", "type": "role", "system": { "rank": 4, "mainRoleAbility": "Charismatic Impact" } },
                    { "_id": "wep123", "name": "Heavy Pistol", "type": "weapon", "system": { "damage": "3d6", "weaponSkill": "Handgun", "rof": 2, "weaponType": "heavyPistol" } },
                    { "_id": "arm123", "name": "Light Armorjack", "type": "armor", "system": { "sp": 11 } },
                    // Cyberware
                    { "name": "External (7 Option Slots)", "type": "cyberwareExternal", "system": { "installLocation": "hospital", "installedItems": { "slots": 7 } } },
                    { "name": "Internal (7 Option Slots)", "type": "cyberwareInternal", "system": { "installLocation": "hospital", "installedItems": { "slots": 7 } } },
                    { "name": "Fashionware (7 Option Slots)", "type": "fashionware", "system": { "installLocation": "mall", "installedItems": { "slots": 7 } } },
                    { "name": "Cyberaudio Suite", "type": "cyberware", "system": { "installLocation": "head" } },
                    { "name": "Wolvers", "type": "cyberware", "system": { "installLocation": "arm" } },
                    // Skills
                    { "name": "Athletics", "type": "skill", "system": { "level": 4, "stat": "dex", "category": "bodySkills" } },
                    { "name": "Brawling", "type": "skill", "system": { "level": 6, "stat": "dex", "category": "fightingSkills" } },
                    { "name": "Handgun", "type": "skill", "system": { "level": 6, "stat": "ref", "category": "rangedweaponSkills" } },
                    { "name": "Perception", "type": "skill", "system": { "level": 2, "stat": "int", "category": "awarenessSkills" } },
                    { "name": "Stealth", "type": "skill", "system": { "level": 2, "stat": "dex", "category": "bodySkills" } },
                    { "name": "Basic Tech", "type": "skill", "system": { "level": 0, "stat": "tech", "category": "techniqueSkills" } },
                    { "name": "Persuasion", "type": "skill", "system": { "level": 5, "stat": "cool", "category": "socialSkills" } },
                    { "name": "Education", "type": "skill", "system": { "level": 2, "stat": "int", "category": "educationSkills" } },
                    { "name": "Drive Land Vehicle", "type": "skill", "system": { "level": 0, "stat": "ref", "category": "controlSkills" } },
                    { "name": "Play Instrument", "type": "skill", "system": { "level": 6, "stat": "tech", "category": "performanceSkills" } },
                ]
            }
        });

        // --- COMPUTED HELPERS ---
        // Note: We use optional chaining (?.) extensively because charData is null initially
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

        const allCyberware = computed(() => {
            if (!charData.value) return [];
            return charData.value.data.items.filter(i =>
                ['cyberware', 'fashionware', 'cyberwareInternal', 'cyberwareExternal'].includes(i.type)
            );
        });

        const otherGear = computed(() => {
            if (!charData.value) return [];
            return charData.value.data.items.filter(i =>
                ['weapon', 'armor', 'gear', 'ammo'].includes(i.type)
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

        onUpdated(() => {
            if (!loading.value) lucide.createIcons();
        });

        return {
            charData, loading, error, activeTab,
            stats, luck, emp, roleItem,
            groupedSkills, allCyberware, otherGear,
            calculateBase, findItem, formatCategory
        };
    }
})
app.component('character-sheet', CharacterSheet)
app.mount('#app');
