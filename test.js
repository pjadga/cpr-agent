import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// 1. Make Pusher available globally (required for Echo)
window.Pusher = Pusher;

const MODULE_ID = 'cpr-agent';

class CyberpunkBridge {
    constructor() {
        this.echo = null;
        this.isConnected = false;
    }

    /**
     * Initialize Settings
     */
    registerSettings() {
        game.settings.register(MODULE_ID, 'apiUrl', {
            name: 'Relay Controller URL',
            hint: 'The base URL of your Agent (e.g., http://cyberpunk.ddev.site). Do NOT include port 8080 here.',
            scope: 'world',
            config: true,
            type: String,
            default: 'http://localhost',
            onChange: () => this.reconnect()
        });

        game.settings.register(MODULE_ID, 'reverbPort', {
            name: 'Reverb Port',
            hint: 'The port your Reverb server is running on (default: 8080). If using DDEV with SSL, try 8081.',
            scope: 'world',
            config: true,
            type: Number,
            default: 8080,
            onChange: () => this.reconnect()
        });

        game.settings.register(MODULE_ID, 'reverbUseSSL', {
            name: 'Reverb Use SSL',
            hint: 'Enable if using https/wss for Reverb.',
            scope: 'world',
            config: true,
            type: Boolean,
            default: false,
            onChange: () => this.reconnect()
        });

        game.settings.register(MODULE_ID, 'reverbKey', {
            name: 'Reverb App Key',
            hint: 'The App Key from your .env file.',
            scope: 'world',
            config: true,
            type: String,
            default: 'itdsiqtx8cpskowawl1p',
            onChange: () => this.reconnect()
        });

        game.settings.register(MODULE_ID, 'apiKey', {
            name: 'API Key (Sanctum Token)',
            hint: 'Generate this in your Cyberpunk Agent dashboard.',
            scope: 'world', // or 'client' if each player has their own
            config: true,
            type: String,
            default: '',
            onChange: () => this.reconnect()
        });

        game.settings.register(MODULE_ID, 'debugMode', {
            name: 'Debug Mode',
            hint: 'Log all socket events to console',
            scope: 'client',
            config: true,
            type: Boolean,
            default: false
        });
    }

    /**
     * Connect to Laravel Reverb
     */
    connect() {
        const apiUrl = game.settings.get(MODULE_ID, 'apiUrl');
        const token = game.settings.get(MODULE_ID, 'apiKey');
        const debug = game.settings.get(MODULE_ID, 'debugMode');
        const reverbPort = game.settings.get(MODULE_ID, 'reverbPort') || 8080;
        const useSSL = game.settings.get(MODULE_ID, 'reverbUseSSL') || false;

        if (!token || !apiUrl) {
            ui.notifications.warn("Cyberpunk Bridge: Missing API URL or Key.");
            return;
        }

        // Clean up old connection if exists
        if (this.echo) {
            this.echo.disconnect();
        }

        // Parse host from URL for Reverb config
        let hostUrl;
        try {
            hostUrl = new URL(apiUrl);
        } catch (e) {
            console.error("Invalid URL", e);
            return;
        }

        console.log(`🔌 Connecting to Cyberpunk Agent Relay at ${hostUrl.hostname}:${reverbPort}...`);

        this.echo = new Echo({
            broadcaster: 'reverb',
            key: game.settings.get(MODULE_ID, 'reverbKey') || 'itdsiqtx8cpskowawl1p',
            wsHost: hostUrl.hostname,
            wsPort: reverbPort,
            wssPort: reverbPort,
            forceTLS: useSSL,
            enabledTransports: useSSL ? ['ws', 'wss'] : ['ws'],
            disableStats: true,
            authEndpoint: `${apiUrl.replace(/\/$/, '')}/api/broadcasting/auth`,

            auth: {
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Accept': 'application/json'
                }
            }
        });

        // Store instance for debugging
        window.CyberpunkSocket = this.echo;

        // Monitor Connection State
        this.echo.connector.pusher.connection.bind('connected', () => {
            this.isConnected = true;
            ui.notifications.info("Cyberpunk Bridge: Online 🟢");
            if(debug) console.log("Cyberpunk Bridge: Connection Established");
        });

        this.echo.connector.pusher.connection.bind('disconnected', () => {
            this.isConnected = false;
            if(debug) console.warn("Cyberpunk Bridge: Disconnected 🔴");
        });

        this.listen();
    }

    /**
     * Listen for Private Channel Events
     */
    listen() {
        const userId = game.user.id; // Or map this to a specific ID from your Laravel App

        // We listen on a private channel named 'cyberpunk.{foundry_user_id}'
        // You might want to use the API Key to determine the channel name in a real app
        this.echo.private(`cpr-agent`)
            .listen('.GameUpdate', (e) => {
                console.log("⚡ Incoming Data:", e);
                this.handleIncomingData(e);
            });
    }

    /**
     * Send results back to Laravel
     */
    async sendResponse(data) {
        let apiUrl = game.settings.get(MODULE_ID, 'apiUrl');
        const token = game.settings.get(MODULE_ID, 'apiKey');

        if (!apiUrl || !token) return;

        // Ensure no trailing slash
        if (apiUrl.endsWith('/')) {
            apiUrl = apiUrl.slice(0, -1);
        }

        try {
            const response = await fetch(`${apiUrl}/api/foundry/callback`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            });

            if (!response.ok) {
                console.error("Cyberpunk Bridge: Callback failed", response.statusText);
            }
        } catch (e) {
            console.error("Cyberpunk Bridge: Error sending callback", e);
        }
    }

    /**
     * Handle Logic (The Tunnel Output)
     */
    async handleIncomingData(payload) {
        // 1. Unwrap the data (Laravel sometimes wraps it in a 'payload' property)
        const data = payload.payload || payload;
        console.log("📥 Received Order:", data);

        const type = data.type;
        const actorId = data.actorId;
        const tokenId = data.tokenId;

        switch (type) {
            case 'UPDATE_ACTOR': {
                const actor = game.actors.get(actorId);
                if (!actor) {
                    console.warn(`⚠️ Actor ID ${actorId} not found in this world.`);
                    return;
                }
                await actor.update(data.changes);
                ui.notifications.info(`💾 Sync: ${actor.name} updated.`);
                await this.sendResponse({ type: 'CONFIRMATION', action: type, actorId, success: true });
                break;
            }

            case 'ITEM_EQUIP': {
                const actor = game.actors.get(actorId);
                if (actor) {
                    const item = actor.items.get(data.itemId);
                    if (item) {
                        await item.update({'system.equipped': data.state});
                        await this.sendResponse({ type: 'CONFIRMATION', action: type, actorId, itemId: data.itemId, success: true });
                    }
                }
                break;
            }

            case 'INSTALL_PROGRAM': {
                const actor = game.actors.get(actorId);
                if (!actor) return;

                const deck = actor.items.get(data.cyberdeckId);
                const program = actor.items.get(data.programId);
                if (!deck || !program) return;

                const currentSlots = deck.system.installedItems.usedSlots || 0;
                const maxSlots = deck.system.installedItems.slots || 0;
                const progSize = program.system.size || 1;

                if (currentSlots + progSize > maxSlots) {
                    ui.notifications.warn("Not enough memory slots on cyberdeck.");
                    return;
                }

                const currentList = deck.system.installedItems.list || [];
                if (currentList.includes(data.programId)) return;

                const newList = [...currentList, data.programId];

                await deck.update({
                    'system.installedItems.list': newList,
                    'system.installedItems.usedSlots': currentSlots + progSize
                });
                await this.sendResponse({ type: 'CONFIRMATION', action: type, actorId, success: true });
                break;
            }

            case 'REMOVE_PROGRAM': {
                const actor = game.actors.get(actorId);
                if (!actor) return;

                const deck = actor.items.get(data.cyberdeckId);
                const program = actor.items.get(data.programId);
                if (!deck) return;

                const currentList = deck.system.installedItems.list || [];
                if (!currentList.includes(data.programId)) return;

                const newList = currentList.filter(id => id !== data.programId);
                const progSize = program ? (program.system.size || 1) : 1;
                const currentSlots = deck.system.installedItems.usedSlots || 0;
                const newSlots = Math.max(0, currentSlots - progSize);

                await deck.update({
                    'system.installedItems.list': newList,
                    'system.installedItems.usedSlots': newSlots
                });

                if (program && program.system.isRezzed) {
                    await program.update({ 'system.isRezzed': false });
                }
                await this.sendResponse({ type: 'CONFIRMATION', action: type, actorId, success: true });
                break;
            }

            case 'TOGGLE_REZ': {
                const actor = game.actors.get(actorId);
                if (actor) {
                    const program = actor.items.get(data.programId);
                    if (program) {
                        await program.update({'system.isRezzed': data.targetState});
                        await this.sendResponse({ type: 'CONFIRMATION', action: type, actorId, programId: data.programId, success: true });
                    }
                }
                break;
            }

            case 'DAMAGE_ROLL': {
                const targetToken = canvas.tokens.get(data.targetId);
                const actor = game.actors.get(actorId);
                if (targetToken && actor) {
                    targetToken.setTarget(true, { releaseOthers: true, groupSelection: true });
                    const weaponId = data.weaponId;
                    const weapon = actor.items.get(weaponId);
                    await actor.sheet._onRoll({
                        'data-roll-type': 'damage',
                        'data-roll-title': 'Damage Roll',
                        'data-item-id': weapon.id,
                        type: 'click',
                        currentTarget: {
                            'data-roll-type': 'damage',
                            'data-roll-title': 'Damage Roll',
                            'data-item-id': weapon.id
                        }
                    });
                    targetToken.setTarget(false);
                    await this.sendResponse({ type: 'CONFIRMATION', action: type, actorId, success: true });
                }
                break;
            }

            case 'DEATH_SAVE': {
                const actor = game.actors.get(actorId);
                if (actor) {
                    await actor.sheet._onRoll({
                        'data-roll-type': 'deathsave',
                        'data-roll-title': 'Death Save',
                        type: 'click',
                        currentTarget: {
                            'data-roll-type': 'deathsave',
                            'data-roll-title': 'Death Save'
                        }
                    });
                    await this.sendResponse({ type: 'CONFIRMATION', action: type, actorId, success: true });
                }
                break;
            }

            case 'FACEDOWN_ROLL': {
                const actor = game.actors.get(actorId);
                if (actor) {
                    await actor.sheet._onRoll({
                        'data-roll-type': 'facedown',
                        'data-roll-title': 'Face Down',
                        type: 'click',
                        currentTarget: {
                            'data-roll-type': 'facedown',
                            'data-roll-title': 'Face Down'
                        }
                    });
                    await this.sendResponse({ type: 'CONFIRMATION', action: type, actorId, success: true });
                }
                break;
            }

            case 'INITIATIVE_ROLL': {
                const actor = game.actors.get(actorId);
                if (actor) {
                    await actor.rollInitiative();
                    await this.sendResponse({ type: 'CONFIRMATION', action: type, actorId, success: true });
                }
                break;
            }

            case 'PROGRAM_ROLL': {
                const actor = game.actors.get(actorId);
                if (actor) {
                    const dummyBtn = document.createElement('a');
                    dummyBtn.dataset.rollType = data.rollType;
                    dummyBtn.dataset.cyberdeckId = data.cyberdeckId;
                    dummyBtn.dataset.rollTitle = data.programName;
                    dummyBtn.dataset.interfaceAbility = data.programName;
                    dummyBtn.dataset.itemId = data.cyberdeckId;
                    dummyBtn.dataset.programId = data.programId;
                    dummyBtn.dataset.executionType = data.executionType;

                    await actor.sheet._onRoll({
                        type: 'click',
                        currentTarget: dummyBtn
                    });
                    await this.sendResponse({ type: 'CONFIRMATION', action: type, actorId, success: true });
                }
                break;
            }

            case 'ROLE_ROLL': {
                const actor = game.actors.get(actorId);
                if (actor) {
                    const item = actor.items.getName(data.itemName);
                    if (item) {
                        const rollData = {
                            'data-roll-type': 'roleAbility',
                            'data-roll-title': data.label,
                            'data-item-id': item.id,
                            'data-item-subtype': data.label,
                            type: 'click',
                            currentTarget: {
                                'data-roll-type': 'roleAbility',
                                'data-roll-title': data.label,
                                'data-item-id': item.id,
                                'data-item-subtype': data.label
                            }
                        };
                        if (data.isAbility) {
                            rollData['data-roll-subtype'] = 'subRoleAbility';
                            rollData['data-role-name'] = data.label;
                            rollData.currentTarget['data-roll-subtype'] = 'subRoleAbility';
                            rollData.currentTarget['data-role-name'] = data.label;
                        }
                        await actor.sheet._onRoll(rollData);
                        await this.sendResponse({ type: 'CONFIRMATION', action: type, actorId, success: true });
                    }
                }
                break;
            }

            case 'SKILL_ROLL': {
                const actor = game.actors.get(actorId);
                if (actor) {
                    const item = actor.items.getName(data.label);
                    if (item) {
                        await actor.sheet._onRoll({
                            'data-roll-type': 'skill',
                            'data-roll-title': data.label,
                            'data-item-id': item.id,
                            type: 'click',
                            currentTarget: {
                                'data-roll-type': 'skill',
                                'data-roll-title': data.label,
                                'data-item-id': item.id
                            }
                        });
                        await this.sendResponse({ type: 'CONFIRMATION', action: type, actorId, success: true });
                    }
                }
                break;
            }

            case 'STAT_ROLL': {
                const actor = game.actors.get(actorId);
                if (actor) {
                    await actor.sheet._onRoll({
                        'data-roll-type': 'stat',
                        'data-roll-title': data.label,
                        type: 'click',
                        currentTarget: {
                            'data-roll-type': 'stat',
                            'data-roll-title': data.label
                        }
                    });
                    await this.sendResponse({ type: 'CONFIRMATION', action: type, actorId, success: true });
                }
                break;
            }

            case 'MOVE_TOKEN': {
                const token = canvas.tokens.get(tokenId);
                if (token) {
                    let newPos = {};
                    if (data.direction === "up") newPos.y = token.document.y - canvas.grid.size;
                    else if (data.direction === "down") newPos.y = token.document.y + canvas.grid.size;
                    else if (data.direction === "left") newPos.x = token.document.x - canvas.grid.size;
                    else if (data.direction === "right") newPos.x = token.document.x + canvas.grid.size;
                    await token.document.update(newPos);
                    await this.sendResponse({ type: 'CONFIRMATION', action: type, tokenId, success: true });
                }
                break;
            }

            case 'RELOAD_WEAPON': {
                const actor = game.actors.get(actorId);
                if (actor) {
                    const weapon = actor.items.get(data.weaponId);
                    const selectedAmmo = data.ammoId ? actor.items.get(data.ammoId) : null;
                    if (selectedAmmo) {
                        let currentAmmo = weapon.getInstalledItems('ammo');
                        if (currentAmmo && currentAmmo.length > 0) {
                            weapon.uninstallItems(currentAmmo);
                        }
                        setTimeout(function(){
                            weapon.installItems([selectedAmmo]);
                        }, 500);
                        setTimeout(async () => {
                            await weapon.reload();
                            await this.sendResponse({ type: 'CONFIRMATION', action: type, actorId, success: true });
                        }, 1500);
                    } else {
                        await weapon.reload();
                        await this.sendResponse({ type: 'CONFIRMATION', action: type, actorId, success: true });
                    }
                }
                break;
            }

            case 'TARGET_TOKEN': {
                const token = canvas.tokens.get(data.tokenId);
                if (token) {
                    token.setTarget(true, { releaseOthers: true, groupSelection: true });
                    await this.sendResponse({ type: 'CONFIRMATION', action: type, tokenId: data.tokenId, success: true });
                }
                break;
            }

            case 'UPDATE_STAT': {
                const actor = game.actors.get(actorId);
                if (actor) {
                    await actor.update({[`system.${data.label}`]: data.value});
                    await this.sendResponse({ type: 'CONFIRMATION', action: type, actorId, success: true });
                }
                break;
            }

            case 'ATTACK_ROLL': {
                const actor = game.actors.get(actorId);
                if (actor) {
                    const weaponId = data.weaponId;
                    const fireMode = data.fireMode;
                    const targetToken = canvas.tokens.get(data.targetId);

                    const flagKey = 'firetype-' + weaponId;
                    const scope = 'cyberpunk-red-core';
                    let flagValue = '';

                    switch (fireMode) {
                        case 'Auto': flagValue = 'autofire'; break;
                        case 'Suppress': flagValue = 'suppressive'; break;
                        case 'Aimed':
                            flagValue = 'aimed';
                            await actor.setFlag(scope, 'aimedLocation', 'head');
                            break;
                        default: flagValue = ''; break;
                    }

                    if (targetToken) {
                        targetToken.setTarget(true, { releaseOthers: true, groupSelection: true });
                    }

                    if (flagValue === '') {
                        await actor.unsetFlag(scope, flagKey);
                    } else {
                        await actor.setFlag(scope, flagKey, flagValue);
                    }

                    const dummyBtn = document.createElement('a');
                    dummyBtn.dataset.rollType = 'attack';
                    dummyBtn.dataset.itemId = weaponId;
                    dummyBtn.dataset.rollTitle = fireMode + ' Attack';

                    await actor.sheet._onRoll({
                        preventDefault: () => {},
                        currentTarget: dummyBtn,
                        type: 'click'
                    }, { showDialog: false });
                    await this.sendResponse({ type: 'CONFIRMATION', action: type, actorId, success: true });
                }
                break;
            }

            case 'GET_ROLL_MODIFIERS': {
                const actor = game.actors.get(actorId);
                if (!actor) return;
                const weaponId = data.weaponId;
                const weapon = weaponId ? actor.items.get(weaponId) : null;

                let weaponSkillName = 'None';
                let skillLevel = 0;
                let statName = 'None';
                let statValue = 0;
                let weaponAcc = 0;

                if (weapon) {
                    weaponSkillName = weapon.system.weaponSkill;
                    const skillItem = actor.items.find(i => i.type === 'skill' && i.name === weaponSkillName);
                    skillLevel = skillItem ? (Number(skillItem.system.level)||0) : 0;
                    statName = skillItem ? (skillItem.system.stat || 'ref') : 'ref';
                    statValue = Number(actor.system.stats[statName]?.value) || 0;
                    weaponAcc = Number(weapon.system.attackmod) || 0;
                }

                let totalPenalty = 0;
                const effectList = [];

                const hp = Number(actor.system.derivedStats.hp.value) || 0;
                const seriouslyThreshold = Number(actor.system.derivedStats.seriouslyWounded) || 0;
                const stim = actor.items.find(i => i.name === 'Stim' && i.type === 'drug');
                const stimEnabled = stim && stim.effects.some(e => e.disabled === false);

                if (hp <= 0) {
                    totalPenalty -= 4;
                    effectList.push({ title: 'Mortally Wounded', penalty: -4, description: 'All actions at -4.' });
                } else if (hp <= seriouslyThreshold) {
                    const penaltyValue = stimEnabled ? 0 : -2;
                    totalPenalty += penaltyValue;
                    effectList.push({
                        title: stimEnabled ? 'Seriously Wounded (Stim Active)' : 'Seriously Wounded',
                        penalty: penaltyValue,
                        description: stimEnabled ? 'Ignoring penalty due to Stim drug.' : 'All actions at -2.'
                    });
                }

                const critInjuries = actor.items.filter(i => i.type === 'criticalInjury');
                critInjuries.forEach(injury => {
                    const title = injury.name || 'Injury';
                    const description = (injury.system.description?.value || '').replace(/<[^>]*>/g, '');

                    let injuryPenalty = 0;
                    if (injury.effects && injury.effects.length > 0) {
                        injury.effects.forEach(eff => {
                            if (!eff.disabled && eff.changes) {
                                eff.changes.forEach(change => {
                                    const val = parseInt(change.value);
                                    if (!isNaN(val)) {
                                        injuryPenalty += val;
                                    }
                                });
                            }
                        });
                    }

                    if (injuryPenalty === 0) {
                        const lowerTitle = String(title).toLowerCase();
                        if (description.includes('-2') || lowerTitle.includes('arm') || lowerTitle.includes('eye')) {
                            injuryPenalty = -2;
                        }
                    }

                    totalPenalty += injuryPenalty;
                    effectList.push({ title, description, penalty: injuryPenalty });
                });

                const result = {
                    actorName: actor.name,
                    baseBonus: statValue + skillLevel + weaponAcc,
                    breakdown: {
                        statName,
                        statValue,
                        skillName: weaponSkillName,
                        skillLevel,
                        weaponAcc
                    },
                    totalPenalty: totalPenalty,
                    finalModifier: statValue + skillLevel + weaponAcc + totalPenalty,
                    injuries: effectList
                };
                console.log("GET_ROLL_MODIFIERS result:", result);
                await this.sendResponse({ type: 'ROLL_MODIFIERS_DATA', result });
                break;
            }

            case 'GET_DV_INFO': {
                const jim = canvas.tokens.get(tokenId);
                const target = Array.from(game.user.targets)[0];
                const weaponId = data.weaponId;

                if (jim && target) {
                    const RANGE_DV_TABLE = {
                      'Pistol':           [13, 15, 20, 25, 30, 30, 99, 99],
                      'SMG':              [15, 13, 15, 20, 25, 25, 30, 99],
                      'Shotgun (Slug)':   [13, 15, 20, 25, 30, 35, 99, 99],
                      'Assault Rifle':    [17, 16, 15, 13, 15, 20, 25, 30],
                      'Sniper Rifle':     [30, 25, 25, 20, 15, 16, 17, 20],
                      'Bows & Crossbow':  [15, 13, 15, 17, 20, 22, 99, 99],
                      'Grenade Launcher': [16, 15, 15, 17, 20, 22, 25, 99],
                      'Rocket Launcher':  [17, 16, 15, 15, 20, 20, 25, 30],
                      'Martial Arts': ['VS'],
                      'Unarmed': ['VS'],
                      'Light Melee Weapon' : ['VS'],
                      'Medium Melee Weapon' : ['VS'],
                      'Heavy Melee Weapon' : ['VS'],
                      'Very Heavy Melee Weapon' : ['VS']
                    };
                    const DISTANCE_BRACKETS = [6, 12, 25, 50, 100, 200, 400, 800];

                    const distX = target.center.x - jim.center.x;
                    const distY = target.center.y - jim.center.y;
                    const distancePx = Math.sqrt(Math.pow(distX, 2) + Math.pow(distY, 2));
                    const distanceM = Math.ceil(distancePx / 128);

                    const weaponData = jim.actor.items.get(weaponId);
                    const typeMap = {
                        'heavyPistol': 'Pistol',
                        'assaultRifle': 'Assault Rifle',
                        'smg': 'SMG',
                        'shotgun': 'Shotgun (Slug)',
                        'sniperRifle': 'Sniper Rifle',
                        'unarmed' : 'Unarmed',
                        'martialArts' : 'Martial Arts',
                        'grenade' : 'Grenade Launcher',
                        'thrownWeapon': 'Grenade Launcher',
                        'lightMelee' : 'Light Melee Weapon',
                        'mediumMelee' : 'Medium Melee Weapon',
                        'heavyMelee' : 'Heavy Melee Weapon',
                        'vHeavyMelee' : 'Very Heavy Melee Weapon'
                    };
                    const category = (typeMap[weaponData.system.weaponType] || typeMap[weaponData.system.variety]) || 'Pistol';

                    const bracketIndex = DISTANCE_BRACKETS.findIndex(limit => distanceM <= limit);
                    let targetDV = bracketIndex !== -1 ? RANGE_DV_TABLE[category][bracketIndex] : 99;

                    if (Array.isArray(RANGE_DV_TABLE[category]) && typeof RANGE_DV_TABLE[category][0] === 'string') {
                        targetDV = RANGE_DV_TABLE[category][Math.min(bracketIndex !== -1 ? bracketIndex : 0, RANGE_DV_TABLE[category].length - 1)];
                    }

                    const result = {
                        targetName: target.name,
                        distanceMeters: distanceM,
                        weaponCategory: category,
                        dv: targetDV === 99 ? 'Out of Range' : targetDV
                    };
                    console.log("GET_DV_INFO result:", result);
                    await this.sendResponse({ type: 'DV_INFO_DATA', result });
                }
                break;
            }

            case 'LIST_VISIBLE_TOKENS': {
                const jim = canvas.tokens.get(tokenId);
                if (jim) {
                    const PIXELS_PER_METER = canvas.grid.size;
                    const d = canvas.dimensions;
                    const jimX = jim.center.x;
                    const jimY = jim.center.y;

                    const nearbyTokens = canvas.tokens.placeables
                        .filter(t => {
                            const isVisible = !t.document.hidden;
                            const isActorType = t.actor && (t.actor.type === 'character' || t.actor.type === 'mook');
                            const isOnMap = t.x >= d.sceneX && t.x <= (d.sceneX + d.sceneWidth) &&
                                t.y >= d.sceneY && t.y <= (d.sceneY + d.sceneHeight);
                            return isVisible && isActorType && isOnMap && t.id !== jim.id;
                        })
                        .map(t => {
                            const distX = t.center.x - jimX;
                            const distY = t.center.y - jimY;
                            const distancePx = Math.sqrt(Math.pow(distX, 2) + Math.pow(distY, 2));
                            const distanceMeters = (distancePx / PIXELS_PER_METER).toFixed(2);
                            return {
                                name: t.name,
                                id: t.id,
                                distanceM: parseFloat(distanceMeters),
                                type: t.actor.type
                            };
                        })
                        .sort((a, b) => a.distanceM - b.distanceM);
                    console.log("LIST_VISIBLE_TOKENS result:", nearbyTokens);
                    await this.sendResponse({ type: 'VISIBLE_TOKENS_DATA', tokens: nearbyTokens });
                }
                break;
            }

            case 'GET_ACTORS': {
                let tokens = canvas.tokens.placeables
                    .filter(t => t.actor && t.actor.hasPlayerOwner)
                    .map(t => ({
                        name: t.name,
                        actorID: t.actor.id,
                        tokenID: t.id
                    }));
                console.log("GET_ACTORS result:", tokens);
                await this.sendResponse({ type: 'ACTORS_DATA', actors: tokens });
                break;
            }

            default:
                console.log("Unknown DataType: " + type);
                break;
        }
    }

    reconnect() {
        if (this.isConnected) {
            this.connect();
        }
    }
}

// Instantiate and Hook
const bridge = new CyberpunkBridge();

Hooks.once('init', () => {
    bridge.registerSettings();
});

Hooks.once('ready', () => {
    // Only GM usually manages the global bridge, but players might need their own
    if (game.user.isGM) {
        bridge.connect();
    }
});
