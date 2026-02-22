import { ref, onMounted, onUnmounted, shallowRef, nextTick, triggerRef } from 'vue';
import { isDeepEqual } from '../utils/helpers';

// Shared state to prevent multiple fetches/intervals
const charData = shallowRef(null);
const loading = ref(true);
const error = ref(null);
const baseUrl = ref(localStorage.getItem('cpr_base_url') || '');
let refreshInterval = null;
let actorUuid = localStorage.getItem('cpr_actor_uuid') || null;
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
     * Apply a focused delta update to the local cached sheet (no network).
     * Incoming paths are assumed to be Foundry-like (e.g., 'system.derivedStats.hp.value' or 'items')
     * We map them under our Vue mirror which nests under 'data'.
     */
    const applyDelta = (delta = {}, { tab } = {}) => {
        if (!charData.value || !delta) return;

        const deepSet = (obj, path, value) => {
            // Special case for items.+ID (addition)
            if (path.startsWith('data.items.+')) {
                const itemId = path.replace('data.items.+', '');
                if (!Array.isArray(obj.data.items)) obj.data.items = [];
                const existingIndex = obj.data.items.findIndex(i => i._id === itemId);
                if (existingIndex > -1) {
                    obj.data.items[existingIndex] = value;
                } else {
                    obj.data.items.push(value);
                }
                return;
            }

            // Special case for items.ID (deletion or update)
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

        triggerRef(charData);
        setTimeout(refreshIcons, 50);
    };

    /**
     * Unified refresh orchestrator. If 'paths' provided, does a targeted fetch and merge.
     * If 'force' is true or no delta can be resolved, falls back to full fetch.
     */
    const refreshSheet = async ({ paths = null, tab = null, force = false } = {}) => {
        if (force || !paths) {
            await fetchCharacterData(false, force);
            return;
        }
        // Minimal targeted refresh: ask server for current mirror and only merge requested paths
        try {
            const response = await window.axios.post('/actorsheet', { actorUuid });
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
            applyDelta(deltas, { tab });
        } catch (e) {
            console.error('refreshSheet targeted fetch failed, falling back to full fetch', e);
            await fetchCharacterData(false, force);
        }
    };

    /**
     * Fetch character data from the server
     */
    const fetchCharacterData = async (isInitial = false, forceFresh = false) => {
        try {
            let payload = { "actorUuid": actorUuid };

            if (isInitial || forceFresh) {
                // If we don't have a UUID, we can't proceed
                if (!actorUuid) {
                    const getActors = await window.axios.post("/getactors");
                    if (getActors.data.uuid) {
                        actorUuid = getActors.data.uuid;
                        if (actorUuid.startsWith('Actor.')) {
                            actorUuid = actorUuid.replace('Actor.', '');
                        }
                        localStorage.setItem('cpr_actor_uuid', actorUuid);
                    }
                }

                if (!actorUuid) {
                    error.value = "No character UUID found. Please check your settings.";
                    loading.value = false;
                    return;
                }

                // Call /getactor to fetch live data from Foundry, then save it to DB
                if (forceFresh) {
                    console.log("Forcing fresh fetch from Foundry...");
                    const getActorResponse = await window.axios.post("/getactor", { "actorId": actorUuid });
                    if (getActorResponse.data.actor) {
                        const saveResponse = await window.axios.post("/actorsheet", {
                            "actorUuid": actorUuid,
                            "actorData": getActorResponse.data.actor
                        });
                        charData.value = saveResponse.data;
                    } else {
                        throw new Error("Could not fetch actor from Foundry");
                    }
                } else {
                    // Call /actorsheet to see if we already have saved data (for initial load)
                    try {
                        const checkResponse = await window.axios.post("/actorsheet", { "actorUuid": actorUuid });
                        charData.value = checkResponse.data;
                        loading.value = false;
                    } catch (err) {
                        // If /actorsheet fails (e.g. 400 because no character_data in DB),
                        // then we fetch from /getactor and save it
                        console.log("No saved character data, fetching from Foundry...");
                        const getActorResponse = await window.axios.post("/getactor", { "actorId": actorUuid });

                        if (getActorResponse.data.actor) {
                            // Save the fetched actor data to the DB via /actorsheet
                            const saveResponse = await window.axios.post("/actorsheet", {
                                "actorUuid": actorUuid,
                                "actorData": getActorResponse.data.actor
                            });
                            charData.value = saveResponse.data;
                        } else {
                            throw new Error("Could not fetch actor from Foundry");
                        }
                    }
                }
            } else {
                // For regular polling, just get from DB
                const response = await window.axios.post("/actorsheet", payload);

                // Only update if data has actually changed
                if (charData.value && isDeepEqual(charData.value.data, response.data.data)) {
                    if (charData.value.requestId !== response.data.requestId) {
                        charData.value.requestId = response.data.requestId;
                    }
                    return;
                }

                charData.value = response.data;
            }

            if (isInitial) loading.value = false;
            setTimeout(refreshIcons, 50);
        } catch (err) {
            console.error("Fetch Error:", err);
            if (isInitial) {
                if (err.response && err.response.data.error) {
                    error.value = err.response.data.error;
                } else {
                    error.value = err.message || "An error occurred while fetching character data.";
                }
                loading.value = false;
            }
        }
    };

    onMounted(async () => {
        useCount++;
        if (useCount === 1) {
            await fetchBaseUrl();
            await fetchCharacterData(true);
            // Poll for updates every 60 seconds (reduced frequency)
            refreshInterval = setInterval(() => fetchCharacterData(), 60000);
        }
    });

    onUnmounted(() => {
        useCount--;
        if (useCount === 0 && refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
        }
    });

    return {
        charData,
        loading,
        error,
        getImageUrl,
        fetchCharacterData,
        refreshIcons,
        applyDelta,
        refreshSheet
    };
}
