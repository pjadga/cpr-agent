import './bootstrap';
import Alpine from 'alpinejs';
import {createApp} from "vue";
import CharacterSheet from "./components/character-sheet.vue";

window.Alpine = Alpine;
Alpine.start();

const app = createApp({});
app.component('character-sheet', CharacterSheet);
app.mount('#app');
