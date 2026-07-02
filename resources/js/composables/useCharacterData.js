import { computed, nextTick, onMounted, onUnmounted, ref, shallowRef, triggerRef } from 'vue';
import { useRefreshIcons, useDeltaApplier } from './useDeltaApplier';
import { useImageProxy } from './useImageProxy';
import { useWebSocketSubscription } from './useWebSocketSubscription';
import { isMyActor, isMyRequest } from '../utils/requests';
import { useNetPool } from './useNetPool';

// Expose delta applier globally for WebSocket handler
window.__applyDelta = null;

/**
 * Main orchestrator composable for character data.
 * Delegates to focused composables: useWebSocketSubscription, useDeltaApplier, useImageProxy.
 */
export function useCharacterData() {
    // Extract sub-composables
    const refreshIcons = useRefreshIcons();
    const { fetchBaseUrl, getImageUrl } = useImageProxy();
    const { onMount, onUnmount, charData, loading, isWssConnected, error, agentLink, isGM } = useWebSocketSubscription();

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
                    console.warn("Local DB fetch failed or empty:", dbErr);
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
                            loading.value = false;
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
        localStorage.setItem('cpr_roll_mode', mode);
    };

    const isPlayer = computed(() => {
        return charData.value?.type === 'character';
    });

    return {
        charData,
        loading,
        isWssConnected,
        error,
        agentLink,
        isGM,
        rollMode: ref(localStorage.getItem('cpr_roll_mode') || 'publicroll'),
        isPlayer,
        setRollMode,
        getImageUrl,
        fetchCharacterData,
        refreshIcons,
        applyDelta: deltaApplier.applyDelta,
        refreshSheet,
    };
}
