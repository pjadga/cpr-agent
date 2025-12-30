import './bootstrap';

import {createApp} from "vue";
import CharacterSheet from "./components/character-sheet.vue";

const app = createApp({});
app.component('character-sheet', CharacterSheet);
app.mount('#app');
