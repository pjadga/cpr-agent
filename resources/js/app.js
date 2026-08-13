import './bootstrap';
import {setupAxiosInterceptors} from './utils/requests';
import Alpine from 'alpinejs';
import {createApp} from "vue";
import CharacterSheet from "./components/character-sheet.vue";
import StatsTab from "./components/character-sheet/StatsTab.vue";
import ResourceCounter from "./components/character-sheet/ResourceCounter.vue";
import AdjustableStat from "./components/character-sheet/AdjustableStat.vue";
import SimpleStat from "./components/character-sheet/SimpleStat.vue";
import ResourceModal from "./components/character-sheet/ResourceModal.vue";
import ControlTab from "./components/character-sheet/ControlTab.vue";
import ProgramRow from "./components/character-sheet/ProgramRow.vue";
import CyberdeckConfig from "./components/character-sheet/CyberdeckConfig.vue";
import CombatPanel from "./components/character-sheet/CombatPanel.vue";
import RollModifierPanel from "./components/character-sheet/RollModifierPanel.vue";
import GearTab from "./components/character-sheet/GearTab.vue";
import ItemRow from "./components/character-sheet/ItemRow.vue";
import SkillsTab from "./components/character-sheet/SkillsTab.vue";
import LifepathTab from "./components/character-sheet/LifepathTab.vue";
import NavBtn from "./components/character-sheet/NavBtn.vue";
import RollResultPopup from "./components/RollResultPopup.vue";
import DiceHistory from "./components/DiceHistory.vue";
import QrLinkScanner from "./components/QrLinkScanner.vue";
import {vCprImg} from "./directives/cpr-img";
import {initTiltShadow} from "./composables/useTiltShadow";
import {RadialMenu} from "./plugins/radial-menu";
import {reduceMotionEnabled} from "./utils/uiPreferences";
import mitt from 'mitt';

window.emitter = mitt();

setupAxiosInterceptors();

window.Alpine = Alpine;
Alpine.start();

const app = createApp({});
app.component('character-sheet', CharacterSheet);
app.component('stats-tab', StatsTab);
app.component('resource-counter', ResourceCounter);
app.component('adjustable-stat', AdjustableStat);
app.component('simple-stat', SimpleStat);
app.component('resource-modal', ResourceModal);
app.component('control-tab', ControlTab);
app.component('program-row', ProgramRow);
app.component('cyberdeck-config', CyberdeckConfig);
app.component('combat-panel', CombatPanel);
app.component('roll-modifier-panel', RollModifierPanel);
app.component('gear-tab', GearTab);
app.component('item-row', ItemRow);
app.component('skills-tab', SkillsTab);
app.component('lifepath-tab', LifepathTab);
app.component('nav-btn', NavBtn);
app.component('roll-result-popup', RollResultPopup);
app.component('dice-history', DiceHistory);
app.component('qr-link-scanner', QrLinkScanner);
app.directive('cpr-img', vCprImg);
app.use(RadialMenu);

// The main agent UI mounts on #app. Some pages (e.g. settings) are Alpine-driven
// and have no #app, so guard against Vue warning on a missing root there.
if (document.getElementById('app')) {
    // Reduce Motion (UI setting): flag <html> BEFORE mount so CSS drops the
    // shadow-depth layers with no flash, and skip the gyro/pointer tilt driver.
    // Otherwise wire up the tilt/gyro parallax for the frame drop-shadows
    // (pointer fallback on desktop) once mounted.
    const reduceMotion = reduceMotionEnabled();
    if (reduceMotion) {
        document.documentElement.classList.add('cpr-reduce-motion');
    }
    app.mount('#app');
    if (!reduceMotion) {
        initTiltShadow();
    }
}

// The settings page is Alpine-land, so the QR/paste linker gets its own isolated
// Vue root that can't clash with Alpine directives elsewhere on the page.
if (document.getElementById('qr-link-app')) {
    const qrApp = createApp({});
    qrApp.component('qr-link-scanner', QrLinkScanner);
    qrApp.mount('#qr-link-app');
}
