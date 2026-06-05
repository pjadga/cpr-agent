import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { DataPoolPageModel } from "./data-pool-model.js";
import { DataPoolPageSheet } from "./data-pool-sheet.js";

// Make Pusher available globally (required for Echo)
window.Pusher = Pusher;
const MODULE_ID = 'cpr-agent';


// ==================================================================
// CUSTOM GM MENU: CHARACTER ASSIGNMENT
// ==================================================================


/**
 * Truncates a string to show first and last N characters with ellipsis
 */
function truncateAgentLink(link, chars = 6) {
    if (!link || link.length <= chars * 2 + 3) return link;
    return `${link.substring(0, chars)}...${link.substring(link.length - chars)}`;
}

class CharacterAssignmentMenu extends FormApplication {
    static get defaultOptions() {
        return foundry.utils.mergeObject(super.defaultOptions, {
            id: 'cpr-agent-assignment-menu',
            title: 'Agent Character Assignment',
            width: 500,
            classes: ["form"]
        });
    }

    async _renderInner(data) {
        let html = `
        <div>
        <p>Assign playable characters to players who have linked their API keys.</p>
        <table style="width: 100%; text-align: left; margin-bottom: 10px;">
        <tr>
        <th>Player</th>
        <th>API Status</th>
        <th>Agent Link</th>
        <th>Assigned Character</th>
        <th style="width: 50px;"></th>
        </tr>`;

        for (let u of data.users) {
            const truncatedLink = truncateAgentLink(u.agentLink);
            html += `<tr><td style="padding: 5px 0;"><strong>${u.name}</strong></td>`;
            if (u.hasKey) {
                html += `<td style="color: #00ff00;">✔ Linked</td>`;
                html += `<td><small><code title="${u.agentLink || ''}">${truncatedLink || 'Pending...'}</code></small></td>`;
                html += `<td><select class="character-select" data-userid="${u.id}" style="width: 100%;">
                <option value="">- None -</option>`;
                for (let c of data.characters) {
                    const selected = c.id === u.characterId ? 'selected' : '';
                    html += `<option value="${c.id}" ${selected}>${c.name}</option>`;
                }
                html += `</select></td>`;
                html += `<td><button type="button" class="refresh-user" data-userid="${u.id}" title="Refresh user details"><i class="fas fa-sync-alt"></i></button></td>`;
            } else {
                html += `<td style="color: #ff0000;">❌ Missing</td>
                <td><small>-</small></td>
                <td><select disabled style="width: 100%;"><option>- Key Missing -</option></select></td>
                <td><button type="button" class="refresh-user" data-userid="${u.id}" title="Refresh user details"><i class="fas fa-sync-alt"></i></button></td>`;
            }
            html += `</tr>`;
        }

        html += `</table>
        <div style="display: flex; gap: 10px; margin-top: 10px;">
        <button type="button" id="save-agent-assignments"><i class="fas fa-save"></i> Save Assignments</button>
        <button type="button" id="refresh-all-users"><i class="fas fa-sync-alt"></i> Refresh All Users</button>
        </div>
        </div>`;
        return $(html);
    }

    getData() {
        const users = game.users.map(u => ({
            id: u.id,
            name: u.name,
            isGM: u.isGM,
            hasKey: !!u.getFlag(MODULE_ID, 'hasApiKey'),
            agentLink: u.getFlag(MODULE_ID, 'agent-link'),
            characterId: u.character?.id || ""
        }));

        const characters = game.actors.filter(a => a.type === 'character').map(a => ({
            id: a.id,
            name: a.name
        }));

        return { users, characters };
    }

    // This forces Foundry to listen to our specific button click
    activateListeners(html) {
        super.activateListeners(html);

        html.find('#save-agent-assignments').click(async (event) => {
            event.preventDefault(); // Stop any browser funny business

            let updatedCount = 0;
            const updates = [];

            // Rip the values directly from the dropdowns
            html.find('.character-select').each((index, element) => {
                const userId = element.dataset.userid;
                const actorId = element.value;
                const user = game.users.get(userId);

                if (user) {
                    const newCharId = (actorId === "" || actorId === "null") ? null : actorId;
                    const currentCharId = user.character?.id || null;

                    if (currentCharId !== newCharId) {
                        updates.push({ _id: user.id, character: newCharId });
                        updatedCount++;
                    }
                }
            });

            // Save to DB and alert the GM
            if (updates.length > 0) {
                await User.updateDocuments(updates);
                ui.notifications.info(`Cyberpunk Bridge: Updated ${updatedCount} player assignments!`);

                // Sync each change to Laravel so it knows immediately
                for (let update of updates) {
                    const user = game.users.get(update._id);
                    const agentLink = user?.getFlag(MODULE_ID, 'agent-link');

                    await window.cyberpunkBridge.sendResponse({
                        type: 'GM_LINK_PLAYER_CHARACTER',
                        agentLink: agentLink || update._id, // Use agent-link as primary ID for Laravel
                        actorId: update.character,
                        character: update.character,
                        isGM: user.isGM
                    });
                }

                // Trigger a sync for the GM in case they updated themselves
                window.cyberpunkBridge.syncPlayerLink();
            } else {
                ui.notifications.info(`Cyberpunk Bridge: No assignment changes made.`);
            }

            this.close(); // Close the window manually after saving
        });

        // Handle individual user refresh
        html.find('.refresh-user').click(async (event) => {
            event.preventDefault();
            const userId = event.currentTarget.dataset.userid;
            const user = game.users.get(userId);

            if (user) {
                // Clear the agent-link flag so it can be re-synced
                await user.unsetFlag(MODULE_ID, 'agent-link');
                await user.unsetFlag(MODULE_ID, 'hasApiKey');

                ui.notifications.info(`Cyberpunk Bridge: Cleared cached data for ${user.name}. They will need to re-sync.`);
                this.render(true); // Re-render the menu to show updated state
            }
        });

        // Handle refresh all users
        html.find('#refresh-all-users').click(async (event) => {
            event.preventDefault();

            for (let user of game.users) {
                await user.unsetFlag(MODULE_ID, 'agent-link');
                await user.unsetFlag(MODULE_ID, 'hasApiKey');
            }

            ui.notifications.info(`Cyberpunk Bridge: Cleared cached data for all users. They will need to re-sync.`);
            this.render(true); // Re-render the menu to show updated state
        });
    }
}
// ==================================================================

class CyberpunkBridge {
    constructor() {
        this.echo = null;
        this.isConnected = false;
        this.activeRequests = new Map();
        this.requestAgentLinks = new Map();
        this.hookIds = {};
        this._isCapturingRoll = false;
    }

    async rollAndCapture(actorId, triggerFunction, rollMode = null) {
        console.log(`%c[Bridge] Starting Render Interceptor for Actor ${actorId}`, "color: #00ffff; font-weight: bold;");
        this._isCapturingRoll = true;

        const prevRollMode = game.settings.get("core", "rollMode");
        if (rollMode) await game.settings.set("core", "rollMode", rollMode);

        // const restoreRollMode = async () => {
        //     if (rollMode) await game.settings.set("core", "rollMode", prevRollMode);
        //     setTimeout(() => { this._isCapturingRoll = false; }, 1000);
        // };

        return new Promise(async (resolve, reject) => {
            let rollFound = false;
            const originalRender = renderTemplate;

            const spyRender = function(path, data) {
                if (path && typeof path === 'string' && path.includes('rollcard')) {
                    console.log(`%c[Bridge] Intercepted Render: ${path}`, "color: #00ff00;", data);
                    rollFound = true;
                    renderTemplate = originalRender;

                    //restoreRollMode();

                    resolve({
                        success: true,
                        total: data.resultTotal,
                        isCriticalSuccess: data.wasCritSuccess ? data.wasCritSuccess() : (data.isCriticalSuccess || false),
                        isCriticalFailure: data.wasCritFail ? data.wasCritFail() : (data.isCriticalFailure || false),
                        label: data.rollTitle,
                        rollMode: rollMode || game.settings.get("core", "rollMode"),
                        rawRoll: data
                    });
                }
                return originalRender.apply(this, arguments);
            };

            renderTemplate = spyRender;

            setTimeout(async () => {
                if (!rollFound) {
                    renderTemplate = originalRender;
                    console.warn("[Bridge] ❌ Interceptor timed out. No rollcard rendered.");
                    //await restoreRollMode();
                    resolve({ success: false, error: "Timed out waiting for render" });
                }
            }, 5000);

            try {
                await triggerFunction();
            } catch (e) {
                renderTemplate = originalRender;
                //await restoreRollMode();
                resolve({ success: false, error: "Trigger failed" });
            }
        });
    }

    destroy() {
        if (this.echo) {
            this.echo.disconnect();
        }
        for (let [hookName, id] of Object.entries(this.hookIds)) {
            Hooks.off(hookName, id);
        }
        if (this._originalToMessage) {
            Roll.prototype.toMessage = this._originalToMessage;
        }
        console.log("Cyberpunk Bridge: Destroyed and hooks removed.");
    }

    registerSettings() {
        // Register the GM Menu
        game.settings.registerMenu(MODULE_ID, 'characterAssignment', {
            name: 'Player Assignments',
            label: 'Manage Characters',
            hint: 'Assign playable characters to players who have linked their Agent API keys.',
            icon: 'fas fa-users',
            type: CharacterAssignmentMenu,
            restricted: true // GM Only
        });

        game.settings.register(MODULE_ID, 'apiUrl', {
            name: 'Agent URL',
            hint: 'The base URL of your Agent (e.g., https://agent.surrealdisco.co.uk).',
            scope: 'world', // URL is usually the same for everyone
            config: true,
            type: String,
            default: 'https://agent.surrealdisco.co.uk',
            onChange: () => {
                if (game.user.isGM) this.reconnect();
                this.syncPlayerLink();
            }
        });

        game.settings.register(MODULE_ID, 'reverbHost', {
            name: 'Reverb Host (override)',
            scope: 'world',
            config: true,
            type: String,
            default: '',
            onChange: () => this.reconnect()
        });

        game.settings.register(MODULE_ID, 'reverbPort', {
            name: 'Reverb Port',
            scope: 'world',
            config: true,
            type: Number,
            default: 0,
            onChange: () => this.reconnect()
        });

        game.settings.register(MODULE_ID, 'reverbUseSSL', {
            name: 'Reverb Use SSL (wss)',
            scope: 'world',
            config: true,
            type: Boolean,
            default: true,
            onChange: () => this.reconnect()
        });

        game.settings.register(MODULE_ID, 'reverbKey', {
            name: 'Reverb App Key',
            scope: 'world',
            config: true,
            type: String,
            default: 'itdsiqtx8cpskowawl1p',
            onChange: () => this.reconnect()
        });

        game.settings.register(MODULE_ID, 'apiKey', {
            name: 'API Key (Sanctum Token)',
            hint: 'Generate this in your Cyberpunk Agent dashboard.',
            scope: 'client', // Vital for player uniqueness
            config: true,
            type: String,
            default: '',
            onChange: async (val) => {
                // Update the public flag so the GM Menu instantly knows they added a key
                await game.user.setFlag(MODULE_ID, 'hasApiKey', !!val);
                if (game.user.isGM) this.reconnect();
                this.syncPlayerLink();
            }
        });

        game.settings.register(MODULE_ID, 'channelId', {
            name: 'Channel ID',
            hint: 'The unique channel name for this game group. Must match your Agent dashboard.',
            scope: 'world',
            config: true,
            type: String,
            default: '',
            onChange: () => {
                if (game.user.isGM) this.reconnect();
            }
        });

        game.settings.register(MODULE_ID, 'debugMode', {
            name: 'Debug Mode',
            scope: 'client',
            config: true,
            type: Boolean,
            default: false
        });
    }

    connect() {
        const apiUrl = game.settings.get(MODULE_ID, 'apiUrl');
        const token = game.settings.get(MODULE_ID, 'apiKey');
        const debug = game.settings.get(MODULE_ID, 'debugMode');
        const reverbPortSetting = Number(game.settings.get(MODULE_ID, 'reverbPort') || 0) || 0;
        const useSSLSetting = game.settings.get(MODULE_ID, 'reverbUseSSL');
        const reverbHostOverride = (game.settings.get(MODULE_ID, 'reverbHost') || '').trim();

        if (!token || !apiUrl) return;

        if (this.echo) {
            this.echo.disconnect();
        }

        let baseUrl;
        try { baseUrl = new URL(apiUrl); }
        catch (e) { return; }

        const wsHost = reverbHostOverride || baseUrl.hostname;
        const inferredSecure = baseUrl.protocol === 'https:';
        const forceTLS = (typeof useSSLSetting === 'boolean' ? useSSLSetting : undefined);
        const useTLS = (forceTLS !== undefined && forceTLS !== null) ? forceTLS : inferredSecure;
        const defaultPort = useTLS ? 443 : 80;
        const wsPort = reverbPortSetting > 0 ? reverbPortSetting : defaultPort;

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

        window.CyberpunkSocket = this.echo;

        this.echo.connector.pusher.connection.bind('connected', () => {
            this.isConnected = true;
            ui.notifications.info("Cyberpunk Bridge: Online");
            if(debug) console.log("Cyberpunk Bridge: Connection Established");
        });

        this.echo.connector.pusher.connection.bind('disconnected', () => {
            this.isConnected = false;
        });

        this.listen();
    }

    listen() {
        const channelId = game.settings.get(MODULE_ID, 'channelId');
        const channelName = channelId ? `cpr-agent.${channelId}` : `cpr-agent`;

        // Only the GM listens for World Updates
        this.echo.private(channelName)
            .listen('.GameUpdate', (e) => {
                this.handleIncomingData(e);
            });

        this.registerGlobalHooks();
    }

    /**
     * Syncs the player's assigned character with the Agent.
     * This allows the Agent to know which character the player is using in Foundry.
     */
    async syncPlayerLink() {
        const token = game.settings.get(MODULE_ID, 'apiKey');
        const debug = game.settings.get(MODULE_ID, 'debugMode');

        if (debug) console.log("[Bridge] syncPlayerLink: Checking API status...");

        // Update the key status flag on the user for GM visibility
        if (game.user.getFlag(MODULE_ID, 'hasApiKey') !== !!token) {
            await game.user.setFlag(MODULE_ID, 'hasApiKey', !!token);
        }

        if (!token) {
            if (debug) console.log("[Bridge] syncPlayerLink: No API Key set. Skipping character sync.");
            return;
        }

        const broadcastToLaravel = async () => {
            const char = game.user.character;
            const currentAgentLink = game.user.getFlag(MODULE_ID, 'agent-link');

            if (debug) {
                console.log(`[Bridge] syncPlayerLink: Broadcasting character link for ${char?.name || 'None'}...`);
                console.log(`[Bridge] syncPlayerLink: Foundry User ID: ${game.user.id}, Name: ${game.user.name}, isGM: ${game.user.isGM}`);
                console.log(`[Bridge] syncPlayerLink: Current stored agent-link: ${currentAgentLink || '(none)'}`);
            }

            try {
                const response = await this.sendResponse({
                    type: 'LINK_PLAYER_CHARACTER',
                    actorId: char ? char.id : null,
                    character: char ? { id: char.id, name: char.name } : null,
                    actorName: char ? char.name : null,
                    agentLink: currentAgentLink || game.user.id,
                    foundryUserId: game.user.id,
                    foundryUserName: game.user.name,
                    isGM: game.user.isGM
                });

                if (debug) {
                    console.log(`[Bridge] syncPlayerLink: Laravel response:`, response);
                }

                if (response && response.agent_link) {
                    if (currentAgentLink !== response.agent_link) {
                        console.log(`%c[Bridge] Link established! Agent Link: ${response.agent_link}`, "color: #00ff00; font-weight: bold;");
                        console.log(`%c[Bridge] Laravel User ID: ${response.user_id || '(not provided)'}`, "color: #00ff00;");
                        await game.user.setFlag(MODULE_ID, 'agent-link', response.agent_link);
                        ui.notifications?.info(`Agent: Account linked successfully.`);
                    }
                } else {
                    console.warn("[Bridge] syncPlayerLink: API responded but no agent_link was returned.", response);
                }
            } catch (e) {
                console.error("[Bridge] syncPlayerLink: Failed to broadcast link to Laravel.", e);
            }
        };

        // Initial sync on load
        await broadcastToLaravel();

        // Listen for character assignment changes
        if (!this.hookIds.updateUser) {
            this.hookIds.updateUser = Hooks.on('updateUser', (user, changes) => {
                if (user.id === game.user.id && changes.character !== undefined) {
                    broadcastToLaravel();
                }
            });
        }
    }

    registerGlobalHooks() {
        if (this.hookIds.updateActor) return;

        // Debounce map for batching updates if needed, though SHEET_DELTA is usually fine per-event
        const syncUpdate = async (actor, delta) => {
            const token = game.settings.get(MODULE_ID, 'apiKey');
            if (!this.isConnected && !token) return;
            // We only sync if the actor is a character or mook and has owners
            if (!actor || !['character', 'mook'].includes(actor.type)) return;

            console.log(`%c[Bridge] Syncing change for ${actor.name}`, "color: #ffaa00;", delta);
            await this.sendResponse({
                type: 'SHEET_DELTA',
                actorId: actor.id,
                uuid: actor.id,
                delta: delta,
                success: true
            });
        };

        //dropActorSheetData
        //

        // Hook into Actor updates (stats, derived stats, etc.)
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

        // Hook into Item updates (inventory, weapons, etc.)
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

        this.hookIds.closeSettingsConfig = Hooks.on('closeSettingsConfig', (app, html) => {
            this.syncPlayerLink();
        });

        this.hookIds.updateToken = Hooks.on('updateToken', (token, changes, options, userId) => {
            if (changes.x || changes.y || changes.disposition || changes.hidden) {
                this.broadcastRefresh('VISIBLE_TOKENS_DATA');
            }
        });

        this.hookIds.deleteToken = Hooks.on('deleteToken', (token, options, userId) => {
            this.broadcastRefresh('VISIBLE_TOKENS_DATA');
        });

        this.hookIds.createChatMessage = Hooks.on('createChatMessage', (message, options, userId) => {
            if (message.author.id !== game.user.id) return;
            if (this._isCapturingRoll) return;
            if (message.rolls && message.rolls.length > 0) {
                this.broadcastRoll(message);
            }
        });
    }

    broadcastRefresh(type) {
        this.sendResponse({ type: 'REFRESH_SIGNAL', target: type });
    }

    broadcastRoll(message) {
        const user = game.users.get(message.author.id);
        const roll = message.rolls[0];

        // Determine roll mode from message properties
        let rollMode = "publicroll";
        if (message.blind) {
            rollMode = "blindroll";
        } else if (message.whisper && message.whisper.length > 0) {
            const gms = game.users.filter(u => u.isGM).map(u => u.id);
            const isToGMs = message.whisper.every(id => gms.includes(id) || id === message.author.id);
            const isSelf = message.whisper.length === 1 && message.whisper[0] === message.author.id;

            if (isSelf) rollMode = "selfroll";
            else if (isToGMs) rollMode = "gmroll";
            else rollMode = "gmroll"; // Default to private if there's a whisper
        }

        this.sendResponse({
            type: 'ROLL_RESULT',
            actorId: message.speaker?.actor || null,
            agentLink: user ? user.getFlag(MODULE_ID, 'agent-link') : null,
            playerName: message.alias,
            total: roll.total,
            formula: roll.formula,
            rollMode: message.rollMode || rollMode,
            label: message.flavor || 'Dice Roll',
            rawRoll: {
                total: roll.total,
                formula: roll.formula,
                rollTitle: message.flavor || 'Dice Roll',
                _roll: roll.toJSON()
            },
            success: true
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

        // Include agent-link if available on the current Foundry user
        const agentLink = (data.requestId ? this.requestAgentLinks.get(data.requestId) : null) || game.user.getFlag(MODULE_ID, 'agent-link');
        if (agentLink && !data.agentLink) {
            data.agentLink = agentLink;
        }

        // Include channelId if set
        const channelId = game.settings.get(MODULE_ID, 'channelId');
        if (channelId && !data.channelId) {
            data.channelId = channelId;
        }

        // Include player name and rollMode for roll results
        if (data.type === 'ROLL_RESULT') {
            if (!data.playerName) {
                const actor = data.actorId ? game.actors.get(data.actorId) : null;
                data.playerName = actor ? actor.name : game.user.name;
            }
            if (!data.rollMode) {
                data.rollMode = game.settings.get("core", "rollMode");
            }
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
                return null;
            }

            return await response.json();
        } catch (e) {
            console.error("Cyberpunk Bridge: Error sending callback", e);
            return null;
        }
    }

    /**
     * Handle Logic (The Tunnel Output)
     */
    async handleIncomingData(payload) {
        // 1. Unwrap the data (Laravel sometimes wraps it in a 'payload' property)
        const data = payload.payload || payload;
        const type = data.type;

        console.log(`%c[Bridge] Incoming Call: ${type}`, "color: #00ffff; font-weight: bold;", data);

        const requestId = data.requestId;
        const agentLink = data.agent_link || data.agentLink;

        if (requestId && agentLink) {
            this.requestAgentLinks.set(requestId, agentLink);
            // Cleanup after 30 seconds to prevent leaks
            setTimeout(() => this.requestAgentLinks.delete(requestId), 30000);
        }

        const myAgentLink = game.user.getFlag(MODULE_ID, 'agent-link');
        const actorId = data.actorId;
        const myCharacterId = game.user.character?.id;
        const debug = game.settings.get(MODULE_ID, 'debugMode');

        if (debug) console.log(`[Bridge] handleIncomingData: requestId=${requestId}, agentLink=${agentLink}, myAgentLink=${myAgentLink}, actorId=${actorId}, myCharacterId=${myCharacterId}`);

        // Determine if this message is intended for us
        let isForMe = false;
        if (game.user.isGM) {
            isForMe = true;
        } else if (myAgentLink && (requestId === myAgentLink || agentLink === myAgentLink)) {
            isForMe = true;
        } else if (!myAgentLink && requestId && actorId && myCharacterId === actorId) {
            // "Discovery" link: We aren't linked yet, but the message is for our character.
            console.log(`[Bridge] Discovery: Matching incoming request to character ${actorId}`);
            isForMe = true;
        }

        // SECURITY & ROUTING:
        // If not a GM and not for me, ignore it.
        if (!isForMe) {
            if (debug) console.log("[Bridge] handleIncomingData: Message ignored (not for me).");
            return;
        }

        // Auto-link if we're not a GM and we found a new/different link for ourselves
        if (!game.user.isGM && agentLink && myAgentLink !== agentLink) {
            // Final check: Only set the flag if this message was specifically for us (myAgentLink match or actorId match)
            if (requestId === agentLink || (actorId && myCharacterId === actorId)) {
                console.log(`%c[Bridge] Setting agent-link flag: ${agentLink}`, "color: #00ff00; font-weight: bold;");
                await game.user.setFlag(MODULE_ID, 'agent-link', agentLink);
                ui.notifications?.info(`Agent: Account linked!`);
            }
        }

        let tokenId = data.tokenId;

        // If no tokenId provided, but we have an actorId, try to find a token for that actor on the canvas
        if (!tokenId && actorId) {
            const token = canvas.tokens.placeables.find(t => t.actor?.id === actorId);
            if (token) {
                tokenId = token.id;
            }
        }

        switch (type) {
            case 'UPDATE_ACTOR': {
                const actor = game.actors.get(actorId);
                if (!actor) {
                    console.warn(`⚠️ Actor ID ${actorId} not found in this world.`);
                    return;
                }
                await actor.update(data.changes);
                ui.notifications.info(`💾 Sync: ${actor.name} updated.`);
                await this.sendResponse({ type: 'CONFIRMATION', action: type, actorId: actorId, success: true, requestId });
                break;
            }

            case 'CONSUME_ITEM': {
                const actor = game.actors.get(actorId);
                if (!actor) {
                    console.warn(`Actor ID ${actorId} not found in this world.`);
                    return;
                }
                const item = actor.items.get(data.itemId);
                if (!item) {
                    console.warn(`Item ID ${data.itemId} not found on actor ${actor.name}.`);
                    return;
                }
                await item.snort();
                ui.notifications.info(`Sync: ${item.name} consumed.`);
                await this.sendResponse({ type: 'CONFIRMATION', action: type, actorId: actorId, success: true, requestId });
                break;
            }

            case 'ITEM_EQUIP': {
                const actor = game.actors.get(actorId);
                if (actor) {
                    const item = actor.items.get(data.itemId);
                    if (item) {
                        const path = `items.${data.itemId}.system.equipped`;
                        await item.update({'system.equipped': data.state});
                        await this.sendResponse({ type: 'SHEET_DELTA', actorId: actorId, delta: { [path]: data.state }, success: true, requestId });
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
                    actorId: actorId,
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

                await this.sendResponse({ type: 'SHEET_DELTA', actorId: actorId, delta, success: true, requestId });
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
                            actorId: actorId,
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

                    const result = await this.rollAndCapture(actorId, trigger, data.rollMode);

                    if (result.success) {
                        await this.sendResponse({
                            type: 'ROLL_RESULT',
                            rollMode: result.rollMode,
                            requestId: requestId,
                            actorId: actorId,
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

                    const result = await this.rollAndCapture(actorId, trigger, data.rollMode);

                    if (result.success) {
                        // After roll, sync the updated death saves counter
                        const deathSaves = actor.system.derivedStats.deathsave || {};

                        await this.sendResponse({
                            type: 'ROLL_RESULT',
                            rollMode: result.rollMode,
                            requestId: requestId,
                            actorId: actorId,
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
                            actorId: actorId,
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
                const result = await this.rollAndCapture(actorId, trigger, data.rollMode);

                if (result.success) {
                    await this.sendResponse({
                        type: 'ROLL_RESULT',
                        rollMode: result.rollMode,
                        requestId,
                        actorId: actorId,
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

                    const result = await this.rollAndCapture(actorId, trigger, data.rollMode);

                    if (result.success) {
                        await this.sendResponse({
                            type: 'ROLL_RESULT',
                            rollMode: result.rollMode,
                            requestId: requestId,
                            actorId: actorId,
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

                    const result = await this.rollAndCapture(actorId, trigger, data.rollMode);

                    if (result.success) {
                        await this.sendResponse({
                            type: 'ROLL_RESULT',
                            rollMode: result.rollMode,
                            requestId: requestId,
                            actorId: actorId,
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

                        const result = await this.rollAndCapture(actorId, trigger, data.rollMode);

                        if (result.success) {
                            await this.sendResponse({
                                type: 'ROLL_RESULT',
                                rollMode: result.rollMode,
                                requestId: requestId,
                                actorId: actorId,
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

                        const result = await this.rollAndCapture(actorId, trigger, data.rollMode);

                        if (result.success) {
                            await this.sendResponse({
                                type: 'ROLL_RESULT',
                                rollMode: result.rollMode,
                                requestId: requestId,
                                actorId: actorId,
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

                    const result = await this.rollAndCapture(actorId, trigger, data.rollMode);

                    if (result.success) {
                        await this.sendResponse({
                            type: 'ROLL_RESULT',
                            rollMode: result.rollMode,
                            requestId: requestId,
                            actorId: actorId,
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
                if (requestId) this.activeRequests.set(requestId, { actorId: actorId, timestamp: Date.now() });
                const roll = new Roll(data.formula);
                await roll.evaluate();
                await roll.toMessage({
                    speaker: ChatMessage.getSpeaker({actor: game.actors.get(actorId)}),
                    flavor: data.flavor,
                    rollMode: data.rollMode || game.settings.get("core", "rollMode")
                });
                await this.sendResponse({
                    type: 'ROLL_RESULT',
                    rollMode: data.rollMode || game.settings.get("core", "rollMode"),
                    requestId: requestId,
                    actorId: actorId,
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
                if (game.paused)
                    break;

                const token = canvas.tokens.get(tokenId);
                if (token) {
                    let newPos = {};
                    if (data.direction === "up") newPos.y = token.document.y - canvas.grid.size;
                    else if (data.direction === "down") newPos.y = token.document.y + canvas.grid.size;
                    else if (data.direction === "left") newPos.x = token.document.x - canvas.grid.size;
                    else if (data.direction === "right") newPos.x = token.document.x + canvas.grid.size;
                    await token.document.update(newPos);

                    await this.sendResponse({ type: 'CONFIRMATION', action: type, actorId: actorId, tokenId, success: true, requestId });
                } else {
                    console.warn(`MOVE_TOKEN: Token ID ${tokenId} not found.`);
                    await this.sendResponse({ type: 'CONFIRMATION', action: type, actorId: actorId, tokenId, success: false, message: 'Token not found', requestId });
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

                        await this.sendResponse({ type: 'SHEET_DELTA', actorId: actorId, delta, success: true, requestId });
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
                const token = canvas.tokens.get(data.targetTokenId);
                if (token) {
                    token.setTarget(true, { releaseOthers: true, groupSelection: true });
                    await this.sendResponse({ type: 'CONFIRMATION', action: type, actorId: actorId, tokenId: data.targetTokenId, success: true, requestId });
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
                    await this.sendResponse({ type: 'SHEET_DELTA', actorId: actorId, delta: { [path]: newValue }, tab: 'stats', success: true, requestId });
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
                        const shooter = canvas.tokens.get(tokenId) || actor.getActiveTokens()?.[0] || canvas.tokens.placeables.find(t => t.actor?.id === actor.id);
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

                    const result = await this.rollAndCapture(actorId, trigger, data.rollMode);


                    if (result.success) {
                        let outcome = null;
                        if (typeof computedDV === 'number') {
                            outcome = result.total >= computedDV ? 'Hit' : 'Miss';
                        } else if (computedDV === 'Out of Range') {
                            outcome = 'Miss';
                        }
                        const magazineValue = actor.items.get(weaponId)?.system?.magazine?.value ?? null;
                        await this.sendResponse({
                            type: 'ROLL_RESULT',
                            rollMode: result.rollMode,
                            requestId: requestId,
                            actorId: actorId,
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
                            ammoTo: magazineValue,
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
                await this.sendResponse({ type: 'ROLL_MODIFIERS_DATA', actorId: actorId, result, requestId });
                break;
            }

            case 'GET_DV_INFO': {
                const jim = canvas.tokens.get(tokenId);
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
                    if (!weaponData) {
                        console.warn(`GET_DV_INFO: Weapon ID ${weaponId} not found on actor ${jim.actor.name}.`);
                        await this.sendResponse({ type: 'DV_INFO_DATA', actorId: actorId, result: null, requestId });
                        break;
                    }

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
                    await this.sendResponse({ type: 'DV_INFO_DATA', actorId: actorId, result, requestId });
                } else {
                    console.warn(`GET_DV_INFO: Token(s) not found. Jim: ${!!jim}, Target: ${!!target}`);
                    await this.sendResponse({ type: 'DV_INFO_DATA', actorId: actorId, result: null, requestId });
                }
                break;
            }

            case 'LIST_VISIBLE_TOKENS': {

                const tokens = canvas.tokens.placeables.filter(t => t.actor?.id === actorId);
                if (tokenId === null) {
                    tokenId = tokens[0]?.id ?? null;
                }

                const jim = canvas.tokens.get(tokenId);
                let nearbyTokens = [];
                if (jim) {
                    const PIXELS_PER_METER = canvas.grid.size;
                    const d = canvas.dimensions;
                    const jimX = jim.center.x;
                    const jimY = jim.center.y;

                    nearbyTokens = canvas.tokens.placeables
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
                } else {
                    console.warn(`LIST_VISIBLE_TOKENS: Token ID ${tokenId} not found.`);
                }
                await this.sendResponse({ type: 'VISIBLE_TOKENS_DATA', actorId: actorId, tokens: nearbyTokens, requestId });
                break;
            }

            case 'GET_ACTOR': {
                let actorId = data.actorId;
                let targetUser = game.user;
                const userIdentifier = data.agentLink || data.agent_link || data.foundryUserId || data.requestId;

                if (userIdentifier) {
                    targetUser = game.users.find(u => u.getFlag(MODULE_ID, 'agent-link') === userIdentifier)
                        || game.users.get(userIdentifier)
                        || (userIdentifier === game.user.id ? game.user : null);
                }

                if (!targetUser) targetUser = game.user;

                // If no actorId provided, try to get the assigned character for the user
                if (!actorId) {
                    const assignedChar = targetUser.character;
                    if (assignedChar) {
                        actorId = assignedChar.id;
                    }
                }

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
                        await this.sendResponse({ type: 'ACTOR_DATA', actorId: actorId, actor: slim, requestId, isGM: targetUser.isGM, agentLink: targetUser.getFlag(MODULE_ID, 'agent-link') || targetUser.id });
                        break;
                    }
                }
                await this.sendResponse({ type: 'ACTOR_DATA', actorId: actorId, actor: false, requestId, isGM: targetUser.isGM, agentLink: targetUser.getFlag(MODULE_ID, 'agent-link') || targetUser.id });
                break;
            }

            case 'GET_ACTORS': {
                let targetUser = game.user;
                const userIdentifier = data.agentLink || data.agent_link || data.foundryUserId || data.requestId;

                if (userIdentifier) {
                    // Try to find the user by their agent-link flag
                    targetUser = game.users.find(u => u.getFlag(MODULE_ID, 'agent-link') === userIdentifier)
                        || game.users.get(userIdentifier) // Fallback to ID
                        || (userIdentifier === game.user.id ? game.user : null);
                }

                if (!targetUser) targetUser = game.user;

                function getTokenFromActor(actorId) {
                    let tokens = canvas.tokens.placeables.filter(t => t.actor?.id === actorId);
                    return tokens[0]?.id ?? null;
                }

                let actors = [];
                // If the target user is a Game Master, we always return ALL characters and mooks.
                // We check targetUser.isGM instead of game.user.isGM.
                if (targetUser.isGM) {
                    // GM sees ALL characters and mooks in the world
                    actors = game.actors
                        .filter(a => a.type === 'character' || a.type === 'mook')
                        .map(a => {
                            // Find the primary owner (player) if any
                            const ownerId = Object.entries(a.ownership || {}).find(([id, level]) => {
                                const u = game.users.get(id);
                                return u && !u.isGM && level >= 3;
                            })?.[0];

                            return {
                                name: a.name,
                                actorId: a.id,
                                tokenId: getTokenFromActor(a.id),
                                agentLink: (ownerId ? game.users.get(ownerId)?.getFlag(MODULE_ID, 'agent-link') : null) || (ownerId || targetUser.getFlag(MODULE_ID, 'agent-link') || targetUser.id)
                            };
                        });
                } else {
                    // Players see tokens they own on the canvas
                    actors = canvas.tokens.placeables
                        .filter(t => t.actor && (t.actor.type === 'character' || t.actor.type === 'mook'))
                        .filter(t => t.actor.testUserPermission(targetUser, "OWNER"))
                        .map(t => {
                            // Find the primary owner (player)
                            const ownerId = Object.entries(t.actor.ownership || {}).find(([id, level]) => {
                                const u = game.users.get(id);
                                return u && !u.isGM && level >= 3;
                            })?.[0];

                            return {
                                name: t.name,
                                actorId: t.actor.id,
                                tokenId: getTokenFromActor(t.actor.id),
                                agentLink: (ownerId ? game.users.get(ownerId)?.getFlag(MODULE_ID, 'agent-link') : null) || ownerId || null
                            };
                        });

                    // If they have an assigned character, always include it
                    if (targetUser.character) {
                        const alreadyInList = actors.some(a => a.actorId === targetUser.character.id);
                        if (!alreadyInList) {
                            actors.push({
                                name: targetUser.character.name,
                                actorId: targetUser.character.id,
                                tokenId: getTokenFromActor(targetUser.character.id),
                                agentLink: targetUser.getFlag(MODULE_ID, 'agent-link') || targetUser.id
                            });
                        }
                    }

                    // If the user is a player, only return their assigned character if they have one
                    if (!targetUser.isGM && targetUser.character) {
                        actors = actors.filter(a => a.actorId === targetUser.character.id);
                    }
                }

                await this.sendResponse({ type: 'ACTORS_DATA', actorId: actorId, actors: actors, requestId, isGM: targetUser.isGM, agentLink: targetUser.getFlag(MODULE_ID, 'agent-link') || targetUser.id });
                break;
            }

            case 'GET_NET_POOL': {
                const dataPools = [];

                // Loop through all journal entries in the world
                for (const journal of game.journal) {

                    // Filter for our custom Data Pool pages
                    const poolPages = journal.pages.filter(p => p.type === "cpr-agent.datapool");

                    for (const page of poolPages) {
                        const rawEntries = page.system.entries || [];
                        const processedEntries = [];

                        // Process and enrich the entries
                        for (const entry of rawEntries) {
                            let content = entry.htmlContent;

                            // If it's HTML, resolve Foundry's internal @UUID and @Roll links into standard HTML
                            if (entry.type === 'html' && content) {
                                content = await TextEditor.enrichHTML(content, { async: true });
                            }

                            processedEntries.push({
                                id: entry.id,
                                icon: entry.icon,
                                title: entry.title,
                                type: entry.type,
                                htmlContent: content,
                                pdfPath: entry.pdfPath
                            });
                        }

                        dataPools.push({
                            journalId: journal.id,
                            journalName: journal.name,
                            pageId: page.id,
                            pageName: page.name,
                            entries: processedEntries
                        });
                    }
                }

                if (debug) console.log("[Bridge] Found Data Pools:", dataPools);

                // Send the payload back to Laravel
                await this.sendResponse({
                    type: 'NET_POOL_DATA',
                    actorId: actorId,
                    pools: dataPools,
                    requestId: requestId,
                    success: true
                });

                break;
            }

            case 'UPDATE_EB': {
                if (actorId == null) {
                    if (debug) console.log("[Bridge] Actor ID Not set:");
                    break;
                }

                let actor = game.actors.get(actorId)

                if (data.action == null || data.value == null) {
                    if (debug) console.log("[Bridge] EuroBucks data error");
                }

                switch (data.action) {
                    case 'add':
                        actor.deltaLedgerProperty("wealth", data.value, data.reason)
                        break;
                    case 'subtract':
                        actor.deltaLedgerProperty("wealth", -Number(data.value), data.reason)
                        break;
                    case 'set':
                        actor.setLedgerProperty("wealth", data.value, data.reason)
                        break;
                }

                if (actor) {
                    await this.sendResponse({ type: 'SHEET_DELTA', actorId: actorId, delta: { 'system.wealth': actor.system.wealth }, tab: 'stats', success: true, requestId });
                }
                break;
            }


            default:
                console.log("Unknown DataType: " + type);
                break;
        }
    }

    reconnect() {
        if (game.user.isGM && this.echo) {
            this.connect();
        }
    }
}
// ==================================================================
// BOOT SEQUENCE & VITE ASYNC FAILSAFE
// ==================================================================


if (window.cyberpunkBridge) {
    window.cyberpunkBridge.destroy();
}
window.cyberpunkBridge = new CyberpunkBridge();

Hooks.once('init', () => {
    ensureSettingsRegistered();

    if (!CONFIG.JournalEntryPage.dataModels["cpr-agent.datapool"]) {
        CONFIG.JournalEntryPage.dataModels["cpr-agent.datapool"] = DataPoolPageModel;
        console.log("[Bridge] Registered DataPool DataModel.");
    }
    DocumentSheetConfig.registerSheet(JournalEntryPage, "cpr-agent", DataPoolPageSheet, {
        types: ["cpr-agent.datapool"],
        makeDefault: true,
        label: "Agent Data Pool"
    });
    console.log("[Bridge] Registered DataPool Journal Page Sheet.");
});

Hooks.once('ready', () => {
    if (game.user.isGM) {
        window.cyberpunkBridge.connect();
    }
    window.cyberpunkBridge.syncPlayerLink();
    window.cyberpunkBridge.registerGlobalHooks();
    console.log("[Bridge] Cyberpunk Bridge Fully Online.");
});

function ensureSettingsRegistered() {
    if (!game.settings.settings.has(`${MODULE_ID}.apiUrl`)) {
        console.log("[Bridge] Registering Module Settings...");
        window.cyberpunkBridge.registerSettings();
    }
}
