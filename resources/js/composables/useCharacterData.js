import {ref, onMounted, onUnmounted, shallowRef, nextTick, triggerRef} from 'vue';
import {isDeepEqual} from '../utils/helpers';
import {isMyRequest} from '../utils/requests';

// Shared state to prevent multiple fetches/intervals
const charData = shallowRef(null);
const loading = ref(true);
const isWssConnected = ref(false);
const error = ref(null);
const agentLink = ref((localStorage.getItem('cpr_agent_link') || '').replace(/\/$/, ''));
const isGM = ref(localStorage.getItem('cpr_is_gm') === 'true');
const baseUrl = ref(localStorage.getItem('cpr_base_url') || '');
const rollMode = ref(localStorage.getItem('cpr_roll_mode') || 'publicroll');
let refreshInterval = null;
let actorUuid = (localStorage.getItem('cpr_actor_uuid') || '').replace(/^Actor\./, '') || null;
let useCount = 0;

/**
 * Composable to handle fetching and polling character data
 */
export function useCharacterData() {
    /**
     * Efficiently refresh Lucide icons
     */
    const refreshIcons = () => {
        if (window.lucide) {
            nextTick(() => window.lucide.createIcons());
        }
    };

    /**
     * Construct full image URL
     */
    const getImageUrl = (path) => {
        if (!path) return '/systems/cyberpunk-red-core/icons/compendium/default/Default_Program.svg';

        let cleanPath = path;
        if (path.startsWith('systems/cyberpunk-red-core/')) {
            cleanPath = path.replace('systems/cyberpunk-red-core/', '/systems/cyberpunk-red-core/');
        }

        let fullUrl = cleanPath;
        if (cleanPath.startsWith('/')) {
            if (baseUrl.value) {
                fullUrl = baseUrl.value + cleanPath;
            }
        } else if (baseUrl.value && !cleanPath.includes('://')) {
            fullUrl = baseUrl.value + "/" + cleanPath;
        }

        // If it's an external URL (e.g. pointing to Ngrok), proxy it
        if (fullUrl.includes('ngrok-free.dev') || fullUrl.includes('ngrok.dev')) {
            return `/proxy-image?url=${encodeURIComponent(fullUrl)}`;
        }

        return fullUrl;
    };

    /**
     * Fetch base URL for images
     */
    const fetchBaseUrl = async () => {
//        if (baseUrl.value) return; // Use cached value if available
        try {
            const response = await window.axios.get("/getbaseurl");
            const url = response.data.replace(/\/$/, ''); // Remove trailing slash if any
            baseUrl.value = url;
            localStorage.setItem('cpr_base_url', baseUrl.value);
        } catch (err) {
            console.error("Error fetching base URL:", err);
        }
    };

    /**
     * Applies a focused delta update to the local character data.
     */
    const applyDelta = (delta = {}, {tab} = {}) => {
        if (!charData.value || !delta) return;

        // Verify character ID consistency
        if (delta.uuid) {
            const incomingUuid = String(delta.uuid).replace(/^Actor\./, '');
            if (incomingUuid && incomingUuid !== actorUuid) {
                console.log(`Character ID mismatch: Local (${actorUuid}) vs Incoming (${incomingUuid}). Updating...`);
                actorUuid = incomingUuid;
                localStorage.setItem('cpr_actor_uuid', actorUuid);
            }
        }

        // Track which item indices were modified so we can replace their references
        const modifiedItemIndices = new Set();

        const deepSet = (obj, path, value) => {
            // Special case for items.+ID (addition)
            if (path.startsWith('data.items.+')) {
                const itemId = path.replace('data.items.+', '');
                if (!Array.isArray(obj.data.items)) obj.data.items = [];
                const existingIndex = obj.data.items.findIndex(i => i._id === itemId);
                if (existingIndex > -1) {
                    obj.data.items[existingIndex] = value;
                    modifiedItemIndices.add(existingIndex);
                } else {
                    obj.data.items.push(value);
                }
                return;
            }

            // Special case for refreshSheetitems.ID (deletion or update)
            if (path.startsWith('data.items.')) {
                const parts = path.split('.');
                const itemId = parts[2];
                if (!Array.isArray(obj.data.items)) obj.data.items = [];

                const existingIndex = obj.data.items.findIndex(i => i._id === itemId);

                if (parts.length === 3) {
                    // Deletion: data.items.ID
                    if (value === null) {
                        if (existingIndex > -1) obj.data.items.splice(existingIndex, 1);
                    } else {
                        // Addition/Replacement: data.items.ID
                        if (existingIndex > -1) {
                            obj.data.items[existingIndex] = value;
                            modifiedItemIndices.add(existingIndex);
                        } else {
                            obj.data.items.push(value);
                        }
                    }
                } else {
                    // Deep update: data.items.ID.field...
                    if (existingIndex === -1) return; // Cannot deep update non-existent item

                    const subPath = parts.slice(3).join('.');
                    const targetItem = obj.data.items[existingIndex];

                    const setNested = (o, p, v) => {
                        const ks = p.split('.');
                        let t = o;
                        for (let i = 0; i < ks.length - 1; i++) {
                            const k = ks[i];
                            if (typeof t[k] !== 'object' || t[k] === null) t[k] = {};
                            t = t[k];
                        }
                        const lastK = ks[ks.length - 1];
                        if (v === null) delete t[lastK];
                        else t[lastK] = v;
                    };
                    setNested(targetItem, subPath, value);
                    modifiedItemIndices.add(existingIndex);
                }
                return;
            }

            const keys = path.split('.');
            let target = obj;
            for (let i = 0; i < keys.length - 1; i++) {
                const k = keys[i];
                if (typeof target[k] !== 'object' || target[k] === null) {
                    target[k] = {};
                }
                target = target[k];
            }

            const lastKey = keys[keys.length - 1];
            if (value === null) {
                delete target[lastKey];
            } else {
                target[lastKey] = value;
            }
        };

        Object.entries(delta).forEach(([path, value]) => {
            let mapped = path.startsWith('data.') ? path : (path.startsWith('system') || path.startsWith('items') ? `data.${path}` : path);

            // Special handling for 'items' to ensure it remains an array (if the whole array is sent)
            if (path === 'items') {
                const valueArray = Array.isArray(value) ? value : Object.values(value || {});
                charData.value.data.items = valueArray;
                return;
            }

            deepSet(charData.value, mapped, value);
        });

        // Replace charData.value entirely to trigger Vue reactivity with shallowRef
        // We need to create new references all the way up the chain
        if (modifiedItemIndices.size > 0 && charData.value.data.items) {
            // Deep clone modified items to ensure all nested properties are new references
            const newItems = charData.value.data.items.map((item, index) => {
                if (modifiedItemIndices.has(index)) {
                    // Deep clone to ensure system.equipped, system.installedItems etc. are new refs
                    return JSON.parse(JSON.stringify(item));
                }
                return item;
            });

            // Replace the entire charData.value to trigger shallowRef reactivity
            charData.value = {
                ...charData.value,
                data: {
                    ...charData.value.data,
                    items: newItems
                }
            };
        } else {
            // Even without item changes, we might have system-level changes
            // Replace charData.value to ensure reactivity
            charData.value = {
                ...charData.value,
                data: {...charData.value.data}
            };
        }

        loading.value = false;
        triggerRef(charData);
        setTimeout(refreshIcons, 50);
    };

    /**
     * Unified refresh orchestrator. If 'paths' provided, does a targeted fetch and merge.
     * If 'force' is true or no delta can be resolved, falls back to full fetch.
     */
    const refreshSheet = async ({paths = null, tab = null, force = false} = {}) => {
        if (force || !paths) {
            await fetchCharacterData(false, force);
            return;
        }
        // Minimal targeted refresh: ask server for current mirror and only merge requested paths
        try {
            const response = await window.axios.post('/actorsheet', {actorUuid});
            const latest = response.data;
            if (!latest || !latest.data) return;
            const extract = (obj, path) => path.split('.').reduce((o, k) => (o ? o[k] : undefined), obj);
            const deltas = {};
            paths.forEach((p) => {
                const foundryRooted = p.startsWith('system') || p.startsWith('items') ? `data.${p}` : p;
                const newVal = extract(latest, foundryRooted);
                if (typeof newVal !== 'undefined') {
                    deltas[p] = newVal; // keep incoming key form (Foundry-like)
                }
            });
            applyDelta(deltas, {tab});
        } catch (e) {
            console.error('refreshSheet targeted fetch failed, falling back to full fetch', e);
            await fetchCharacterData(false, force);
        }
    };

    /**
     * Fetch character data from the server
     *
     * NEW STRATEGY (Phase 2):
     * 1. Load from local DB (/actorsheet) instantly to provide immediate UI feedback.
     * 2. In parallel (or background), trigger a refresh from Foundry (/getactor).
     * 3. UI updates when Echo receives the Foundry response.
     */
    const fetchCharacterData = async (isInitial = false, forceFresh = false) => {
        try {
            if (!actorUuid) {
                // If we don't have a UUID, trigger a fetch from the backend
                const getActors = await window.axios.post("/getactors");
                if (getActors.data?.actors?.[0]?.actorId) {
                    actorUuid = String(getActors.data.actors[0].actorId).replace(/^Actor\./, '');
                    localStorage.setItem('cpr_actor_uuid', actorUuid);
                } else {
                    console.log("[Agent] No immediate actor ID found, waiting for background refresh...");
                }
            }

            console.log("ActorUUID: " + actorUuid)

            if (!forceFresh) {
                try {
                    const dbResponse = await window.axios.post("/actorsheet");
                    if (dbResponse.data && dbResponse.data.data) {
                        if (dbResponse.data.agentLink) {
                            const normalizedLink = dbResponse.data.agentLink.replace(/\/$/, '');
                            agentLink.value = normalizedLink;
                            localStorage.setItem('cpr_agent_link', normalizedLink);
                        }
                        if (typeof dbResponse.data.isGM !== 'undefined') {
                            isGM.value = !!dbResponse.data.isGM;
                            localStorage.setItem('cpr_is_gm', isGM.value);
                        }
                        // Check if the UUID from the database matches our local actorUuid
                        const dbActorUuid = String(dbResponse.data.uuid).replace(/^Actor\./, '');
                        if (dbActorUuid && dbActorUuid !== actorUuid) {
                            console.log(`UUID mismatch: Local (${actorUuid}) vs DB (${dbActorUuid}). Updating...`);
                            actorUuid = dbActorUuid;
                            localStorage.setItem('cpr_actor_uuid', actorUuid);
                        }

                        charData.value = dbResponse.data;
                        if (isInitial) loading.value = false;
                        setTimeout(refreshIcons, 50);
                    }
                } catch (dbErr) {
                    console.warn("Local DB fetch failed or empty:", dbErr);
                    // If it's the very first time (no DB entry), we MUST force a fresh fetch
                    if (isInitial) forceFresh = true;
                }
            } else {
                // When forcing fresh, we show loading state while waiting for Foundry
                loading.value = true;
            }

            if (forceFresh) {
                //Only trigger this when asked to refresh
                console.log("Triggering background refresh from Foundry...");
                if (actorUuid) {
                    await window.axios.post("/getactor", {"actorId": actorUuid});
                }

                // Safety fallback: if no event lands quickly, unstick the UI
                setTimeout(async () => {
                    if (loading.value) {
                        console.warn('[Agent] Refresh timed out. Falling back to local cache.');
                        try {
                            const dbResponse = await window.axios.post('/actorsheet');
                            if (dbResponse.data && dbResponse.data.data) {
                                charData.value = dbResponse.data;
                                loading.value = false;
                                triggerRef(charData);
                                setTimeout(refreshIcons, 50);
                            }
                        } catch (e) {
                            console.error('[Agent] Fallback /actorsheet failed', e);
                            loading.value = false; // Still unstick even if fallback fails
                        }
                    }
                }, 4000);
            }

        } catch (err) {
            console.error("Fetch Error:", err);
            if (isInitial && !charData.value) {
                error.value = err.response?.data?.error || err.message || "An error occurred.";
                loading.value = false;
            }
        }
    };

    onMounted(async () => {
        useCount++;
        if (useCount === 1) {
            // Subscribe FIRST to avoid missing fast responses from Foundry
            if (window.Echo) {
                const channel_id = window.AgentConfig?.channel_id;
                const channelName = channel_id ? `cpr-agent.${channel_id}` : null;

                if (channelName) {
                    window.Echo.private(channelName)
                        .listen('.FoundryResponse', (e) => {
                            const payload = e.payload || e;

                            // If the request was initiated by another agent, ignore it IF it has a requestId
                            // Except for Group events which are for everyone in this private channel
                            const isPublicEvent = ['DICE_ROLL_EVENT', 'ROLL_RESULT', 'ACTOR_DATA', 'SHEET_DELTA'].includes(payload.type);
                            if (!isPublicEvent && payload.requestId && !isMyRequest(payload.requestId)) {
                                console.log(`Ignoring FoundryResponse with requestId [${payload.requestId}] - initiated by another agent.`);
                                return;
                            }

                            // If it's actor data or a sheet delta, apply it
                            if (payload.type === 'ACTOR_DATA' && payload.actor) {
                                const payloadActorId = String(payload.actor._id).replace(/^Actor\./, '');
                                if (actorUuid && payloadActorId !== actorUuid) return;

                                // Adopting the UUID if we don't have one
                                if (!actorUuid) {
                                    console.log(`[Agent] Adopting Actor ID: ${payloadActorId}`);
                                    actorUuid = payloadActorId;
                                    localStorage.setItem('cpr_actor_uuid', actorUuid);
                                }

                                if (payload.agentLink || payload.agent_link) {
                                    const normalizedLink = (payload.agentLink || payload.agent_link).replace(/\/$/, '');
                                    agentLink.value = normalizedLink;
                                    localStorage.setItem('cpr_agent_link', normalizedLink);
                                }
                                if (typeof payload.isGM !== 'undefined') {
                                    isGM.value = !!payload.isGM;
                                    localStorage.setItem('cpr_is_gm', isGM.value);
                                }

                                charData.value = {
                                    ...charData.value,
                                    data: payload.actor,
                                    uuid: payloadActorId,
                                    requestId: payload.requestId
                                };
                                loading.value = false;
                                triggerRef(charData);
                                setTimeout(refreshIcons, 50);
                            } else if (payload.type === 'SHEET_DELTA' && payload.delta) {
                                const payloadActorId = String(payload.uuid || '').replace(/^Actor\./, '');
                                if (actorUuid && payloadActorId && payloadActorId !== actorUuid) return;

                                // Adopting the UUID if we don't have one
                                if (!actorUuid && payloadActorId) {
                                    console.log(`[Agent] Adopting Actor ID from delta: ${payloadActorId}`);
                                    actorUuid = payloadActorId;
                                    localStorage.setItem('cpr_actor_uuid', actorUuid);
                                }

                                applyDelta(payload.delta, {tab: payload.tab});
                            }
                        });
                } else {
                    console.warn('[Agent] No channel_id configured. Live updates disabled.');
                }
            }

            // Trigger data load AFTER subscription is ready
            await fetchBaseUrl();
            await fetchCharacterData(true);
        }
    });

    onUnmounted(() => {
        useCount--;
        if (useCount === 0 && refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
        }
    });

    const setRollMode = (mode) => {
        rollMode.value = mode;
        localStorage.setItem('cpr_roll_mode', mode);
    };

    return {
        charData,
        loading,
        isWssConnected,
        error,
        agentLink,
        isGM,
        rollMode,
        setRollMode,
        getImageUrl,
        fetchCharacterData,
        refreshIcons,
        applyDelta,
        refreshSheet
    };
}
