import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// 1. Make Pusher available globally (required for Echo)
window.Pusher = Pusher;

const MODULE_ID = 'cpr-agent';

class CyberpunkBridge {
    constructor() {
        this.echo = null;
        this.isConnected = false;
        this.activeRequests = new Map();
        this.hookIds = {};
    }

    /**
     * INTERCEPTOR: Wraps renderTemplate to steal the CPRRoll object before it vanishes.
     */
    async rollAndCapture(actorId, triggerFunction) {
        console.log(`%c[Bridge] ‍Starting Render Interceptor for Actor ${actorId}`, "color: #00ffff; font-weight: bold;");

        return new Promise(async (resolve, reject) => {
            let rollFound = false;

            // 1. Save the original function
            const originalRender = renderTemplate;

            // 2. Define our "Spy" function
            const spyRender = function(path, data) {
                // Check if this is a CPR Roll Card
                if (path && typeof path === 'string' && path.includes('rollcard')) {
                    console.log(`%c[Bridge] Intercepted Render: ${path}`, "color: #00ff00;", data);

                    // Verify it belongs to our actor (optional, but good safety)
                    // Most CPR roll objects have an 'actor' or 'alias' property, or we assume timing is correct

                    rollFound = true;

                    // RESTORE the original immediately so we don't break anything else
                    renderTemplate = originalRender;

                    // RESOLVE with the stolen data
                    resolve({
                        success: true,
                        total: data.resultTotal,
                        isCriticalSuccess: data.wasCritSuccess ? data.wasCritSuccess() : (data.isCriticalSuccess || false),
                        isCriticalFailure: data.wasCritFail ? data.wasCritFail() : (data.isCriticalFailure || false),
                        label: data.rollTitle,
                        rawRoll: data // <--- HERE IS YOUR FULL OBJECT
                    });
                }

                // Pass execution back to the original function so the chat card actually appears
                return originalRender.apply(this, arguments);
            };

            // 3. Install the Spy
            renderTemplate = spyRender;

            // 4. Safety Timeout (Restores original function if render never happens)
            setTimeout(() => {
                if (!rollFound) {
                    renderTemplate = originalRender;
                    console.warn("[Bridge] ❌ Interceptor timed out. No rollcard rendered.");
                    resolve({ success: false, error: "Timed out waiting for render" });
                }
            }, 5000); // 5 seconds max wait

            // 5. Trigger the Roll
            try {
                await triggerFunction();
            } catch (e) {
                renderTemplate = originalRender;
                resolve({ success: false, error: "Trigger failed" });
            }
        });
    }
    /**
     * Clean up before reloading
     */
    destroy() {
        if (this.echo) {
            this.echo.disconnect();
        }
        for (let [hookName, id] of Object.entries(this.hookIds)) {
            Hooks.off(hookName, id);
        }
        // Restore original Roll.toMessage
        if (this._originalToMessage) {
            Roll.prototype.toMessage = this._originalToMessage;
        }
        console.log("Cyberpunk Bridge: Destroyed and hooks removed.");
    }

    /**
     * Initialize Settings
     */
    registerSettings() {
        game.settings.register(MODULE_ID, 'apiUrl', {
            name: 'Relay Controller URL',
            hint: 'The base URL of your Agent (e.g., https://agent.surrealdisco.co.uk). Do NOT include port 8080 here.',
            scope: 'client',
            config: true,
            type: String,
            default: 'https://agent.surrealdisco.co.uk',
            onChange: () => this.reconnect()
        });

        game.settings.register(MODULE_ID, 'reverbHost', {
            name: 'Reverb Host (override)',
            hint: 'Optional. Override the WebSocket host used for Reverb (e.g., my-tunnel.ngrok-free.dev). Leave blank to derive from Foundry URL.',
            scope: 'world',
            config: true,
            type: String,
            default: '',
            onChange: () => this.reconnect()
        });

        game.settings.register(MODULE_ID, 'reverbPort', {
            name: 'Reverb Port',
            hint: 'Optional. If blank, auto-detects: 443 for https/wss, 80 for http/ws, or use your custom value.',
            scope: 'world',
            config: true,
            type: Number,
            default: 0,
            onChange: () => this.reconnect()
        });

        game.settings.register(MODULE_ID, 'reverbUseSSL', {
            name: 'Reverb Use SSL (wss)',
            hint: 'If enabled, uses secure WebSockets (wss). If left off, auto-detects from Relay URL protocol.',
            scope: 'world',
            config: true,
            type: Boolean,
            default: true,
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
        const reverbPortSetting = Number(game.settings.get(MODULE_ID, 'reverbPort') || 0) || 0;
        const useSSLSetting = game.settings.get(MODULE_ID, 'reverbUseSSL');
        const reverbHostOverride = (game.settings.get(MODULE_ID, 'reverbHost') || '').trim();

        if (!token || !apiUrl) {
            ui.notifications.warn("Cyberpunk Bridge: Missing API URL or Key.");
            return;
        }

        // Clean up old connection if exists
        if (this.echo) {
            this.echo.disconnect();
        }

        // Parse host from URL for Reverb config and infer ports/TLS for ngrok/cloudflared
        let baseUrl;
        try {
            baseUrl = new URL(apiUrl);
        } catch (e) {
            console.error("Invalid Relay URL", e);
            return;
        }

        // Determine host used for websocket
        const wsHost = reverbHostOverride || baseUrl.hostname;

        // Auto-detect TLS from apiUrl protocol when not explicitly set
        const inferredSecure = baseUrl.protocol === 'https:';
        const forceTLS = (typeof useSSLSetting === 'boolean' ? useSSLSetting : undefined);
        const useTLS = (forceTLS !== undefined && forceTLS !== null) ? forceTLS : inferredSecure;

        // Auto ports: if user provided non-zero, use it; else default 443 for secure, 80 for insecure
        const defaultPort = useTLS ? 443 : 80;
        const wsPort = reverbPortSetting > 0 ? reverbPortSetting : defaultPort;

        console.log(`[Bridge] Connecting to Agent Relay via Reverb host=${wsHost} port=${wsPort} secure=${useTLS}`);

        this.echo = new Echo({
            broadcaster: 'reverb',
            key: game.settings.get(MODULE_ID, 'reverbKey') || 'itdsiqtx8cpskowawl1p',
            wsHost: wsHost,
            wsPort: wsPort,
            wssPort: wsPort,
            forceTLS: useTLS,
            enabledTransports: ['ws', 'wss'],
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
            ui.notifications.info("Cyberpunk Bridge: Online");
            if(debug) console.log("Cyberpunk Bridge: Connection Established");
        });

        this.echo.connector.pusher.connection.bind('disconnected', () => {
            this.isConnected = false;
            if(debug) console.warn("Cyberpunk Bridge: Disconnected");
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
                console.log("Incoming Data:", e);
                this.handleIncomingData(e);
            });

        // REGISTER GLOBAL HOOKS to detect manual changes in Foundry
        this.registerGlobalHooks();
    }

    registerGlobalHooks() {
        // Debounce map for batching updates if needed, though SHEET_DELTA is usually fine per-event
        const syncUpdate = async (actor, delta) => {
            if (!this.isConnected) return;
            // We only sync if the actor is a character or mook and has owners
            if (!actor || !['character', 'mook'].includes(actor.type)) return;

            console.log(`%c[Bridge] 🔄 Syncing change for ${actor.name}`, "color: #ffaa00;", delta);
            await this.sendResponse({
                type: 'SHEET_DELTA',
                actorId: actor.id,
                delta: delta,
                success: true
            });
        };

        // 1. Hook into Actor updates (stats, derived stats, etc.)
        this.hookIds.updateActor = Hooks.on('updateActor', (actor, changes, options, userId) => {
            // Ignore updates triggered by the Agent itself to avoid loops (though Agent updates usually go through API)
            // But we should check if 'changes.system' exists
            if (changes.system) {
                // Flatten the changes.system to dot notation for SHEET_DELTA
                const delta = {};
                const flatten = (obj, prefix = 'system.') => {
                    for (let key in obj) {
                        const val = obj[key];
                        if (val !== null && typeof val === 'object' && !Array.isArray(val) && !(val instanceof foundry.utils.Color)) {
                            flatten(val, `${prefix}${key}.`);
                        } else {
                            delta[`${prefix}${key}`] = val;
                        }
                    }
                };
                flatten(changes.system);
                syncUpdate(actor, delta);
            }
        });

        // 2. Hook into Item updates (inventory, weapons, etc.)
        this.hookIds.updateItem = Hooks.on('updateItem', (item, changes, options, userId) => {
            const actor = item.parent;
            if (actor instanceof Actor) {
                // Flatten changes.system if it exists, similar to updateActor
                const delta = {};
                const prefix = `items.${item.id}.`;

                if (changes.system) {
                    const flatten = (obj, p = `${prefix}system.`) => {
                        for (let key in obj) {
                            const val = obj[key];
                            if (val !== null && typeof val === 'object' && !Array.isArray(val) && !(val instanceof foundry.utils.Color)) {
                                flatten(val, `${p}${key}.`);
                            } else {
                                delta[`${p}${key}`] = val;
                            }
                        }
                    };
                    flatten(changes.system);
                }

                // Handle top-level item changes like name, img, etc.
                for (let key in changes) {
                    if (key !== 'system' && key !== '_id') {
                        delta[`${prefix}${key}`] = changes[key];
                    }
                }

                if (Object.keys(delta).length > 0) {
                    syncUpdate(actor, delta);
                }
            }
        });

        this.hookIds.createItem = Hooks.on('createItem', (item, options, userId) => {
            const actor = item.parent;
            if (actor instanceof Actor) {
                // When creating, we send the whole object so the Agent knows all its initial stats
                // Use a special key 'items.+' to signal an append operation
                const delta = {
                    [`items.+${item.id}`]: item.toObject()
                };
                syncUpdate(actor, delta);
            }
        });

        this.hookIds.deleteItem = Hooks.on('deleteItem', (item, options, userId) => {
            const actor = item.parent;
            if (actor instanceof Actor) {
                // Send a null value for this item ID to signal deletion
                const delta = {
                    [`items.${item.id}`]: null
                };
                syncUpdate(actor, delta);
            }
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
        console.log("Received Order:", data);

        const type = data.type;
        const actorId = data.actorId;
        const tokenId = data.tokenId;
        const requestId = data.requestId;

        switch (type) {
            case 'UPDATE_ACTOR': {
                const actor = game.actors.get(actorId);
                if (!actor) {
                    console.warn(`⚠️ Actor ID ${actorId} not found in this world.`);
                    return;
                }
                await actor.update(data.changes);
                ui.notifications.info(`💾 Sync: ${actor.name} updated.`);
                await this.sendResponse({ type: 'CONFIRMATION', action: type, actorId, success: true, requestId });
                break;
            }

            case 'ITEM_EQUIP': {
                const actor = game.actors.get(actorId);
                if (actor) {
                    const item = actor.items.get(data.itemId);
                    if (item) {
                        const path = `items.${data.itemId}.system.equipped`;
                        await item.update({'system.equipped': data.state});
                        await this.sendResponse({ type: 'SHEET_DELTA', actorId, delta: { [path]: data.state }, success: true, requestId });
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
                const newUsedSlots = currentSlots + progSize;

                await deck.update({
                    'system.installedItems.list': newList,
                    'system.installedItems.usedSlots': newUsedSlots
                });

                await this.sendResponse({
                    type: 'SHEET_DELTA',
                    actorId,
                    delta: {
                        [`items.${data.cyberdeckId}.system.installedItems.list`]: newList,
                        [`items.${data.cyberdeckId}.system.installedItems.usedSlots`]: newUsedSlots
                    },
                    success: true,
                    requestId
                });
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

                const delta = {
                    [`items.${data.cyberdeckId}.system.installedItems.list`]: newList,
                    [`items.${data.cyberdeckId}.system.installedItems.usedSlots`]: newSlots
                };

                if (program && program.system.isRezzed) {
                    await program.update({ 'system.isRezzed': false });
                    delta[`items.${data.programId}.system.isRezzed`] = false;
                }

                await this.sendResponse({ type: 'SHEET_DELTA', actorId, delta, success: true, requestId });
                break;
            }

            case 'TOGGLE_REZ': {
                const actor = game.actors.get(actorId);
                if (actor) {
                    const program = actor.items.get(data.programId);
                    if (program) {
                        await program.update({'system.isRezzed': data.targetState});
                        await this.sendResponse({
                            type: 'SHEET_DELTA',
                            actorId,
                            delta: { [`items.${data.programId}.system.isRezzed`]: data.targetState },
                            success: true,
                            requestId
                        });
                    }
                }
                break;
            }

            case 'DAMAGE_ROLL': {
                const actor = game.actors.get(actorId);
                const weapon = actor.items.get(data.weaponId);

                if (actor && weapon) {
                    const trigger = async () => {
                        await actor.sheet._onRoll({
                            'data-roll-type': 'damage',
                            'data-item-id': weapon.id,
                            type: 'click',
                            currentTarget: {
                                'data-roll-type': 'damage',
                                'data-item-id': weapon.id
                            }
                        });
                    };

                    const result = await this.rollAndCapture(actorId, trigger);

                    if (result.success) {
                        await this.sendResponse({
                            type: 'ROLL_RESULT',
                            requestId: requestId,
                            actorId,
                            total: result.total,
                            isCriticalSuccess: result.isCriticalSuccess,
                            isCriticalFailure: result.isCriticalFailure,
                            label: `${weapon.name} (Damage)`,
                            rawRoll: {
                                total: result.total,
                                isCriticalSuccess: result.isCriticalSuccess,
                                isCriticalFailure: result.isCriticalFailure,
                                label: result.label,
                                resultTotal: result.rawRoll?.resultTotal,
                                rollTitle: result.rawRoll?.rollTitle
                            },
                            success: true
                        });
                    }
                }
                break;
            }

            case 'DEATH_SAVE': {
                const actor = game.actors.get(actorId);
                if (actor) {
                    const trigger = async () => {
                        await actor.sheet._onRoll({
                            'data-roll-type': 'deathsave',
                            'data-roll-title': 'Death Save',
                            type: 'click',
                            currentTarget: {
                                'data-roll-type': 'deathsave',
                                'data-roll-title': 'Death Save'
                            }
                        });
                    };

                    const result = await this.rollAndCapture(actorId, trigger);

                    if (result.success) {
                        // After roll, sync the updated death saves counter
                        const deathSaves = actor.system.derivedStats.deathsave || {};

                        await this.sendResponse({
                            type: 'ROLL_RESULT',
                            requestId: requestId,
                            actorId,
                            total: result.total,
                            isCriticalSuccess: result.isCriticalSuccess,
                            isCriticalFailure: result.isCriticalFailure,
                            label: 'Death Save',
                            rawRoll: {
                                total: result.total,
                                isCriticalSuccess: result.isCriticalSuccess,
                                isCriticalFailure: result.isCriticalFailure,
                                label: result.label,
                                resultTotal: result.rawRoll?.resultTotal,
                                rollTitle: result.rawRoll?.rollTitle
                            },
                            success: true
                        });

                        // Also send a delta for the death save counter
                        await this.sendResponse({
                            type: 'SHEET_DELTA',
                            actorId,
                            delta: { 'system.derivedStats.deathsave': deathSaves },
                            tab: 'stats',
                            success: true
                        });
                    }
                }
                break;
            }

            case 'FACEDOWN_ROLL': {
                const actor = game.actors.get(actorId);

                // Define the UI Trigger
                const trigger = async () => {
                    await actor.sheet._onRoll({
                        'data-roll-type': 'facedown',
                        'data-roll-title': 'Face Down',
                        type: 'click',
                        currentTarget: {
                            'data-roll-type': 'facedown',
                            'data-roll-title': 'Face Down'
                        }
                    });
                };

                // Use the Interceptor
                const result = await this.rollAndCapture(actorId, trigger);

                if (result.success) {
                    await this.sendResponse({
                        type: 'ROLL_RESULT',
                        requestId,
                        actorId,
                        total: result.total,
                        isCriticalSuccess: result.isCriticalSuccess,
                        isCriticalFailure: result.isCriticalFailure,
                        rawRoll: {
                            total: result.total,
                            isCriticalSuccess: result.isCriticalSuccess,
                            isCriticalFailure: result.isCriticalFailure,
                            label: result.label,
                            resultTotal: result.rawRoll?.resultTotal,
                            rollTitle: result.rawRoll?.rollTitle
                        },
                        success: true
                    });
                }
                break;
            }

            case 'INITIATIVE_ROLL': {
                const actor = game.actors.get(actorId);
                if (actor) {
                    const trigger = async () => {
                        await actor.rollInitiative();
                    };

                    const result = await this.rollAndCapture(actorId, trigger);

                    if (result.success) {
                        await this.sendResponse({
                            type: 'ROLL_RESULT',
                            requestId,
                            actorId,
                            total: result.total,
                            isCriticalSuccess: result.isCriticalSuccess,
                            isCriticalFailure: result.isCriticalFailure,
                            label: 'Initiative',
                            rawRoll: {
                                total: result.total,
                                isCriticalSuccess: result.isCriticalSuccess,
                                isCriticalFailure: result.isCriticalFailure,
                                label: result.label,
                                resultTotal: result.rawRoll?.resultTotal,
                                rollTitle: result.rawRoll?.rollTitle
                            },
                            success: true
                        });
                    }
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

                    const trigger = async () => {
                        await actor.sheet._onRoll({
                            type: 'click',
                            currentTarget: dummyBtn
                        });
                    };

                    const result = await this.rollAndCapture(actorId, trigger);

                    if (result.success) {
                        await this.sendResponse({
                            type: 'ROLL_RESULT',
                            requestId,
                            actorId,
                            total: result.total,
                            isCriticalSuccess: result.isCriticalSuccess,
                            isCriticalFailure: result.isCriticalFailure,
                            label: data.programName,
                            rawRoll: {
                                total: result.total,
                                isCriticalSuccess: result.isCriticalSuccess,
                                isCriticalFailure: result.isCriticalFailure,
                                label: result.label,
                                resultTotal: result.rawRoll?.resultTotal,
                                rollTitle: result.rawRoll?.rollTitle
                            },
                            success: true
                        });
                    }
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

                        const trigger = async () => {
                            await actor.sheet._onRoll(rollData);
                        };

                        const result = await this.rollAndCapture(actorId, trigger);

                        if (result.success) {
                            await this.sendResponse({
                                type: 'ROLL_RESULT',
                                requestId,
                                actorId,
                                total: result.total,
                                isCriticalSuccess: result.isCriticalSuccess,
                                isCriticalFailure: result.isCriticalFailure,
                                label: data.label,
                                rawRoll: {
                                    total: result.total,
                                    isCriticalSuccess: result.isCriticalSuccess,
                                    isCriticalFailure: result.isCriticalFailure,
                                    label: result.label,
                                    resultTotal: result.rawRoll?.resultTotal,
                                    rollTitle: result.rawRoll?.rollTitle
                                },
                                success: true
                            });
                        }
                    }
                }
                break;
            }

            case 'SKILL_ROLL': {
                const actor = game.actors.get(actorId);
                if (actor) {
                    const item = actor.items.getName(data.label);
                    if (item) {
                        const trigger = async () => {
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
                        };

                        const result = await this.rollAndCapture(actorId, trigger);

                        if (result.success) {
                            await this.sendResponse({
                                type: 'ROLL_RESULT',
                                requestId,
                                actorId,
                                total: result.total,
                                isCriticalSuccess: result.isCriticalSuccess,
                                isCriticalFailure: result.isCriticalFailure,
                                label: data.label,
                                rawRoll: {
                                    total: result.total,
                                    isCriticalSuccess: result.isCriticalSuccess,
                                    isCriticalFailure: result.isCriticalFailure,
                                    label: result.label,
                                    resultTotal: result.rawRoll?.resultTotal,
                                    rollTitle: result.rawRoll?.rollTitle
                                },
                                success: true
                            });
                        }
                    }
                }
                break;
            }

            case 'STAT_ROLL': {
                const actor = game.actors.get(actorId);
                if (actor) {
                    const trigger = async () => {
                        await actor.sheet._onRoll({
                            'data-roll-type': 'stat',
                            'data-roll-title': data.label,
                            type: 'click',
                            currentTarget: {
                                'data-roll-type': 'stat',
                                'data-roll-title': data.label
                            }
                        });
                    };

                    const result = await this.rollAndCapture(actorId, trigger);

                    if (result.success) {
                        await this.sendResponse({
                            type: 'ROLL_RESULT',
                            requestId,
                            actorId,
                            total: result.total,
                            isCriticalSuccess: result.isCriticalSuccess,
                            isCriticalFailure: result.isCriticalFailure,
                            label: data.label,
                            rawRoll: {
                                total: result.total,
                                isCriticalSuccess: result.isCriticalSuccess,
                                isCriticalFailure: result.isCriticalFailure,
                                label: result.label,
                                resultTotal: result.rawRoll?.resultTotal,
                                rollTitle: result.rawRoll?.rollTitle
                            },
                            success: true
                        });
                    }
                }
                break;
            }

            case 'DICE_ROLL': {
                if (requestId) this.activeRequests.set(requestId, { actorId, timestamp: Date.now() });
                const roll = new Roll(data.formula);
                await roll.evaluate();
                await roll.toMessage({
                    speaker: ChatMessage.getSpeaker({actor: game.actors.get(actorId)}),
                    flavor: data.flavor
                });
                await this.sendResponse({
                    type: 'ROLL_RESULT',
                    requestId,
                    actorId,
                    total: roll.total,
                    isCriticalSuccess: roll.isCriticalSuccess || false,
                    isCriticalFailure: roll.isCriticalFailure || false,
                    label: data.flavor || 'Dice Roll',
                    rawRoll: {
                        rollTitle: data.flavor || 'Dice Roll',
                        _roll: roll,
                        formula: roll.formula,
                        resultTotal: roll.total
                    },
                    success: true
                });
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

                    // After movement, immediately push new proximity and DV info
                    // This avoids waiting for Agent-side timers
                    setTimeout(async () => {
                        // Re-trigger visible tokens and DV logic (mimics the cases below)
                        const dummyRequest = { tokenId, requestId: (requestId || 'move-refresh') };
                        await this.handleIncomingData({ type: 'LIST_VISIBLE_TOKENS', ...dummyRequest });

                        const target = Array.from(game.user.targets)[0];
                        if (target && data.weaponId) {
                            await this.handleIncomingData({ type: 'GET_DV_INFO', ...dummyRequest, weaponId: data.weaponId });
                        }
                    }, 200);

                    await this.sendResponse({ type: 'CONFIRMATION', action: type, tokenId, success: true, requestId });
                }
                break;
            }

            case 'RELOAD_WEAPON': {
                const actor = game.actors.get(actorId);
                if (actor) {
                    const weapon = actor.items.get(data.weaponId);
                    const selectedAmmo = data.ammoId ? actor.items.get(data.ammoId) : null;

                    const performReload = async () => {
                        await weapon.reload();

                        // Send delta for weapon and potentially ammo
                        const delta = {
                            [`items.${data.weaponId}.system.magazine`]: {
                                value: weapon.system.magazine.value,
                                max: weapon.system.magazine.max
                            },
                            [`items.${data.weaponId}.system.installedItems`]: {
                                list: [...weapon.system.installedItems.list],
                                slots: weapon.system.installedItems.slots,
                                usedSlots: weapon.system.installedItems.usedSlots
                            }
                        };

                        // If we have specific ammo being tracked, update it too
                        if (selectedAmmo) {
                            delta[`items.${selectedAmmo.id}.system.amount`] = selectedAmmo.system.amount;
                        }

                        // Get current ammo list to ensure any uninstalled ammo is also updated
                        const actorItems = actor.items;
                        actorItems.filter(i => i.type === 'ammo').forEach(i => {
                            delta[`items.${i.id}.system.amount`] = i.system.amount;
                        });

                        // Also update any other ammo that might have been changed/uninstalled
                        // Since we don't know which one was uninstalled easily without more logic,
                        // we can either refresh all items or just let standard delta handle it.
                        // But for now, ensuring weapon.magazine and weapon.installedItems is a good start.

                        await this.sendResponse({ type: 'SHEET_DELTA', actorId, delta, success: true, requestId });
                    };

                    if (selectedAmmo) {
                        let currentAmmo = weapon.getInstalledItems('ammo');
                        if (currentAmmo && currentAmmo.length > 0) {
                            weapon.uninstallItems(currentAmmo);
                        }
                        setTimeout(function(){
                            weapon.installItems([selectedAmmo]);
                        }, 500);
                        setTimeout(async () => {
                            await performReload();
                        }, 1500);
                    } else {
                        await performReload();
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
                    const path = `system.${data.label}`;
                    let newValue;
                    if (data.operation === 'inc') {
                        // Resolve current value from nested path and add delta
                        const parts = path.split('.');
                        let cur = actor;
                        for (let i = 0; i < parts.length; i++) {
                            cur = (i === 0 ? cur[parts[i]] : cur?.[parts[i]]);
                        }
                        const currentVal = Number(cur) || 0;
                        const delta = Number(data.delta) || 0;
                        newValue = currentVal + delta;
                    } else {
                        // Check if value is a string or number
                        newValue = (typeof data.value === 'string') ? data.value : (Number(data.value) || 0);
                    }
                    await actor.update({ [path]: newValue });
                    // Send a focused sheet delta back to Agent and persist via API
                    await this.sendResponse({ type: 'SHEET_DELTA', actorId, delta: { [path]: newValue }, tab: 'stats', success: true });
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

                    const trigger = async () => {
                        await actor.sheet._onRoll({
                            preventDefault: () => {},
                            currentTarget: dummyBtn,
                            type: 'click'
                        }, { showDialog: true });
                    };

                    // Compute DV against current target if available
                    let computedDV = null;
                    if (targetToken) {
                        const shooter = actor.getActiveTokens()?.[0] || canvas.tokens.placeables.find(t => t.actor?.id === actor.id);
                        if (shooter) {
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
                            const distX = targetToken.center.x - shooter.center.x;
                            const distY = targetToken.center.y - shooter.center.y;
                            const distancePx = Math.sqrt(Math.pow(distX, 2) + Math.pow(distY, 2));
                            const distanceM = Math.ceil(distancePx / (canvas.grid?.size || 128));

                            const weaponData = actor.items.get(weaponId);
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
                            const category = (typeMap[weaponData?.system?.weaponType] || typeMap[weaponData?.system?.variety]) || 'Pistol';
                            const bracketIndex = DISTANCE_BRACKETS.findIndex(limit => distanceM <= limit);
                            let targetDV = bracketIndex !== -1 ? RANGE_DV_TABLE[category][bracketIndex] : 99;
                            if (Array.isArray(RANGE_DV_TABLE[category]) && typeof RANGE_DV_TABLE[category][0] === 'string') {
                                targetDV = RANGE_DV_TABLE[category][Math.min(bracketIndex !== -1 ? bracketIndex : 0, RANGE_DV_TABLE[category].length - 1)];
                            }
                            computedDV = (targetDV === 99 ? 'Out of Range' : targetDV);
                        }
                    }

                    const result = await this.rollAndCapture(actorId, trigger);

                    if (result.success) {
                        let outcome = null;
                        if (typeof computedDV === 'number') {
                            outcome = result.total >= computedDV ? 'Hit' : 'Miss';
                        } else if (computedDV === 'Out of Range') {
                            outcome = 'Miss';
                        }
                        await this.sendResponse({
                            type: 'ROLL_RESULT',
                            requestId,
                            actorId,
                            total: result.total,
                            isCriticalSuccess: result.isCriticalSuccess,
                            isCriticalFailure: result.isCriticalFailure,
                            label: fireMode + ' Attack',
                            rawRoll: {
                                total: result.total,
                                isCriticalSuccess: result.isCriticalSuccess,
                                isCriticalFailure: result.isCriticalFailure,
                                label: result.label,
                                resultTotal: result.rawRoll?.resultTotal,
                                rollTitle: result.rawRoll?.rollTitle
                            },
                            dv: computedDV,
                            outcome,
                            success: true
                        });
                    }
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
                const jim = canvas.tokens.get(data.tokenId);
                const targetId = data.targetTokenId || Array.from(game.user.targets)[0]?.id;
                const target = canvas.tokens.get(targetId);
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
                    await this.sendResponse({ type: 'DV_INFO_DATA', result, requestId });
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
                    await this.sendResponse({ type: 'VISIBLE_TOKENS_DATA', tokens: nearbyTokens, requestId });
                }
                break;
            }

            case 'GET_ACTOR': {
                let actorId = data.actorId;
                if (actorId !== null) {
                    const actor = game.actors.get(actorId);
                    if (actor) {
                        const obj = actor.toObject();
                        const slim = {
                            _id: actor.id,
                            name: actor.name,
                            type: actor.type,
                            img: obj.img,
                            system: obj.system,
                            items: (obj.items || []).map(i => ({
                                _id: i._id,
                                name: i.name,
                                type: i.type,
                                img: i.img,
                                system: i.system
                            })),
                            flags: obj.flags && obj.flags['cyberpunk-red-core'] ? { 'cyberpunk-red-core': obj.flags['cyberpunk-red-core'] } : {}
                        };
                        await this.sendResponse({ type: 'ACTOR_DATA', actor: slim, requestId });
                        break;
                    }
                }
                await this.sendResponse({ type: 'ACTOR_DATA', actor: false, requestId });
                break;
            }

            case 'GET_ACTORS': {
                let tokens = canvas.tokens.placeables
                    .filter(t => t.actor && t.actor.hasPlayerOwner)
                    .map(t => ({
                        name: t.name,
                        actorId: t.actor.id,
                        tokenId: t.id
                    }));
                console.log("GET_ACTORS result:", tokens);
                await this.sendResponse({ type: 'ACTORS_DATA', actors: tokens, requestId });
                break;
            }

            default:
                console.log("Unknown DataType: " + type);
                break;
        }
    }

    reconnect() {
        if (this.echo) {
            this.connect();
        }
    }
}

// Instantiate and Hook
if (window.cyberpunkBridge) {
    window.cyberpunkBridge.destroy();
}
window.cyberpunkBridge = new CyberpunkBridge();

// Handle registration and connection regardless of when the script is loaded
if (game.ready) {
    window.cyberpunkBridge.registerSettings();
    if (game.user.isGM) {
        window.cyberpunkBridge.connect();
    }
} else {
    Hooks.once('init', () => {
        window.cyberpunkBridge.registerSettings();
    });

    Hooks.once('ready', () => {
        // Only GM usually manages the global bridge, but players might need their own
        if (game.user.isGM) {
            window.cyberpunkBridge.connect();
        }
    });
}
