import { computed, nextTick, onMounted, onUnmounted, ref, shallowRef, triggerRef, watch } from 'vue';
import { useRefreshIcons, useDeltaApplier } from './useDeltaApplier';
import { useImageProxy } from './useImageProxy';
import { useWebSocketSubscription } from './useWebSocketSubscription';
import { isMyActor, isMyRequest } from '../utils/requests';
import { useNetPool } from './useNetPool';
import { classifyApiError } from './useFoundryDiagnostics';

// Expose delta applier globally for WebSocket handler
window.__applyDelta = null;

// Shared singleton for the roll privacy mode. This MUST be module-level so every
// useCharacterData() consumer reads and writes the same ref: the roll-mode
// dropdown and the components that dispatch rolls. A per-call ref silently
// desynced them, so the dropdown updated its own copy while rolls went out with
// a stale 'publicroll'. The watcher persists any change (v-model or setRollMode).
const rollMode = ref(localStorage.getItem('cpr_roll_mode') || 'publicroll');
watch(rollMode, (mode) => {
    try { localStorage.setItem('cpr_roll_mode', mode); } catch (e) { /* noop */ }
});

/**
 * Main orchestrator composable for character data.
 * Delegates to focused composables: useWebSocketSubscription, useDeltaApplier, useImageProxy.
 */
export function useCharacterData() {
    // Extract sub-composables
    const refreshIcons = useRefreshIcons();
    const { fetchBaseUrl, getImageUrl } = useImageProxy();
    const { onMount, onUnmount, charData, loading, isWssConnected, isFoundryLive, error, errorCode, agentLink, isGM } = useWebSocketSubscription();

    // Delta applier (bound to our reactive state)
    const deltaApplier = useDeltaApplier(charData, loading);
    window.__applyDelta = deltaApplier.applyDelta;

    let actorUuid = (localStorage.getItem('cpr_actor_uuid') || '').replace(/^Actor\./, '') || null;

    /**
     * Unified refresh orchestrator. If 'paths' provided, does a targeted fetch and merge.
     */
    const refreshSheet = async ({ paths = null, tab = null, force = false } = {}) => {
        if (force || !paths) {
            const { fetchNetPool } = useNetPool();
            await fetchNetPool();
            await fetchCharacterData(false, force);
            return;
        }

        try {
            const response = await window.axios.post('/api/actorsheet', { actorUuid });
            const latest = response.data;
            if (!latest || !latest.data) return;

            const extract = (obj, path) => path.split('.').reduce((o, k) => (o ? o[k] : undefined), obj);
            const deltas = {};
            paths.forEach((p) => {
                const foundryRooted = p.startsWith('system') || p.startsWith('items') ? `data.${p}` : p;
                const newVal = extract(latest, foundryRooted);
                if (typeof newVal !== 'undefined') {
                    deltas[p] = newVal;
                }
            });

            deltaApplier.applyDelta(deltas, { tab });
        } catch (e) {
            console.error('refreshSheet targeted fetch failed, falling back to full fetch', e);
            await fetchCharacterData(false, force);
        }
    };

    /**
     * Fetch character data from the server.
     * Strategy: 1) Load from local DB instantly → 2) Background refresh from Foundry.
     */
    const fetchCharacterData = async (isInitial = false, forceFresh = false) => {
        // Why the most recent request failed, as a DIAGNOSTICS key. Several
        // steps here fail benignly on a fresh account, so the cause is carried
        // rather than thrown, and only surfaces if we finish with nothing.
        let lastFailure = null;

        try {
            if (!actorUuid) {
                const getActors = await window.axios.post("/api/getactors");
                if (getActors.data?.actors?.[0]?.actorId) {
                    actorUuid = String(getActors.data.actors[0].actorId).replace(/^Actor\./, '');
                    localStorage.setItem('cpr_actor_uuid', actorUuid);
                } else {
                    console.log("[Agent] No immediate actor ID found, waiting for background refresh...");
                }
            }

            console.log("ActorUUID: " + actorUuid);

            if (!forceFresh) {
                try {
                    const dbResponse = await window.axios.post("/api/actorsheet");
                    if (dbResponse.data && dbResponse.data.data) {
                        if (dbResponse.data.agentLink) {
                            agentLink.value = dbResponse.data.agentLink.replace(/\/$/, '');
                            localStorage.setItem('cpr_agent_link', agentLink.value);
                        }
                        if (typeof dbResponse.data.isGM !== 'undefined') {
                            isGM.value = !!dbResponse.data.isGM;
                            localStorage.setItem('cpr_is_gm', isGM.value);
                        }

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
                    // Expected on a fresh account: there is no cached sheet yet.
                    // Keep *why* so that if the Foundry refresh also comes back
                    // empty we can say which of the two it was, instead of
                    // rendering nothing at all.
                    console.warn("Local DB fetch failed or empty:", dbErr);
                    lastFailure = classifyApiError(dbErr);
                    if (isInitial) forceFresh = true;
                }
            } else {
                loading.value = true;
            }

            if (forceFresh) {
                console.log("Triggering background refresh from Foundry...");
                if (actorUuid) {
                    await window.axios.post("/api/getactor", { "actorId": actorUuid });
                }

                // Safety fallback: unstick UI if no event lands within 4s
                setTimeout(async () => {
                    if (loading.value) {
                        console.warn('[Agent] Refresh timed out. Falling back to local cache.');
                        try {
                            const dbResponse = await window.axios.post('/api/actorsheet');
                            if (dbResponse.data && dbResponse.data.data) {
                                charData.value = dbResponse.data;
                                loading.value = false;
                                triggerRef(charData);
                                setTimeout(refreshIcons, 50);
                            }
                        } catch (e) {
                            console.error('[Agent] Fallback /actorsheet failed', e);
                            lastFailure = classifyApiError(e);
                        }

                        loading.value = false;

                        // Nothing loaded and nothing raised: without this the
                        // sheet renders no branch at all and the user gets a
                        // blank dashboard with the answer only in the console.
                        if (!charData.value && !error.value && !errorCode.value) {
                            errorCode.value = lastFailure || 'no_character_data';
                        }
                    }
                }, 4000);
            }

        } catch (err) {
            console.error("Fetch Error:", err);
            lastFailure = classifyApiError(err);
            if (isInitial && !charData.value) {
                // "Request failed with status code 500" is what the user used to
                // see here, which is true of a dead Reverb, a closed Foundry and
                // an unconfigured account alike. Classify instead.
                errorCode.value = lastFailure;
                loading.value = false;
            }
        }
    };

    // Lifecycle hooks
    onMounted(async () => {
        onMount();
        await fetchBaseUrl();
        await fetchCharacterData(true);
    });

    onUnmounted(() => {
        onUnmount();
    });

    const setRollMode = (mode) => {
        rollMode.value = mode; // the module-level watcher persists to localStorage
    };

    const isPlayer = computed(() => {
        return charData.value?.type === 'character';
    });

    return {
        charData,
        loading,
        isWssConnected,
        isFoundryLive,
        error,
        errorCode,
        agentLink,
        isGM,
        rollMode,
        isPlayer,
        setRollMode,
        getImageUrl,
        fetchCharacterData,
        refreshIcons,
        applyDelta: deltaApplier.applyDelta,
        refreshSheet,
    };
}
