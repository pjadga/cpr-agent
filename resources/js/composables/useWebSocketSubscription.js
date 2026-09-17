import { shallowRef, ref } from 'vue';
import { isMyRequest } from '../utils/requests';

// Shared state for character data (module-level singleton)
export const charData = shallowRef(null);
export const loading = ref(true);
// The socket to *Reverb*. Says nothing about whether Foundry is on the other
// end of it: with Reverb running and Foundry closed this is happily true while
// the sheet shows month-old cached data.
export const isWssConnected = ref(false);
// Whether Foundry itself has actually spoken to us this session. Only a payload
// that originated in Foundry sets this, so it stays false while the sheet is
// being served from the local database cache.
export const isFoundryLive = ref(false);
export const error = ref(null);
// A DIAGNOSTICS key (see useFoundryDiagnostics) naming *why* the sheet could
// not load. Preferred over `error`, which holds free prose: a code carries a
// title, an explanation and a fix, where a string carries only itself.
export const errorCode = ref(null);
export const agentLink = ref((localStorage.getItem('cpr_agent_link') || '').replace(/\/$/, ''));
export const isGM = ref(localStorage.getItem('cpr_is_gm') === 'true');

let actorUuid = (localStorage.getItem('cpr_actor_uuid') || '').replace(/^Actor\./, '') || null;
let useCount = 0;

/**
 * Subscribe to WebSocket events on the private channel.
 * Handles ACTOR_DATA and SHEET_DELTA events, applying them to charData.
 */
export function useWebSocketSubscription() {
    let refreshInterval = null;

    /**
     * Subscribe to FoundryResponse events on the private channel.
     */
    const subscribe = () => {
        if (!window.Echo) return;

        const channel_id = window.AgentConfig?.channel_id;
        // In the Foundry embed there is no Reverb channel: window.Echo is the
        // MockEcho fed by postMessage, and it ignores the channel name.
        const channelName = channel_id
            ? `cpr-agent.${channel_id}`
            : (window.__CPR_EMBED ? 'cpr-agent.embed' : null);

        if (!channelName) {
            console.warn('[Agent] No channel_id configured. Live updates disabled.');
            return;
        }

        window.Echo.private(channelName)
            .listen('.FoundryResponse', (e) => {
                const payload = e.payload || e;

                // Filter out requests from other agents (unless public event)
                const isPublicEvent = ['DICE_ROLL_EVENT', 'ROLL_RESULT', 'ACTOR_DATA', 'SHEET_DELTA'].includes(payload.type);
                if (!isPublicEvent && payload.requestId && !isMyRequest(payload.requestId)) {
                    console.log(`Ignoring FoundryResponse with requestId [${payload.requestId}] - initiated by another agent.`);
                    return;
                }

                // Handle ACTOR_DATA events
                if (payload.type === 'ACTOR_DATA' && payload.actor) {
                    handleActorData(payload);
                }
                // The Bridge answers with actor === false when the Foundry user
                // has no character assigned. Degrade honestly instead of spinning
                // on the loading screen forever.
                else if (payload.type === 'ACTOR_DATA' && payload.actor === false) {
                    loading.value = false;
                    if (!charData.value) {
                        errorCode.value = 'no_character_assigned';
                    }
                }
                // Handle SHEET_DELTA events
                else if (payload.type === 'SHEET_DELTA' && payload.delta) {
                    handleSheetDelta(payload);
                }
            });

        console.log('[Agent] WebSocket subscription established.');
    };

    /**
     * Process an ACTOR_DATA event from Foundry.
     */
    const handleActorData = (payload) => {
        const payloadActorId = String(payload.actor._id).replace(/^Actor\./, '');

        // Skip if not for our actor
        if (actorUuid && payloadActorId !== actorUuid) return;

        // Adopt UUID if we don't have one yet
        if (!actorUuid) {
            console.log(`[Agent] Adopting Actor ID: ${payloadActorId}`);
            actorUuid = payloadActorId;
            localStorage.setItem('cpr_actor_uuid', actorUuid);
        }

        // Update agent link and GM status from payload
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
            // Mirror the hosted /api/actorsheet shape: a top-level `type` drives
            // isPlayer (charData.type === 'character'). Without it, embed rows
            // render the locked/mook variant
            type: payload.actor.type,
            data: payload.actor,
            uuid: payloadActorId,
            requestId: payload.requestId
        };
        loading.value = false;
        error.value = null; // clear any prior "no character" / timeout state now that real data arrived
        errorCode.value = null;
        isFoundryLive.value = true; // this payload came from Foundry, so it is live
    };

    /**
     * Process a SHEET_DELTA event from Foundry.
     */
    const handleSheetDelta = (payload) => {
        const payloadActorId = String(payload.actorId || payload.uuid || '').replace(/^Actor\./, '');

        // Skip if not for our actor
        if (actorUuid && payloadActorId && payloadActorId !== actorUuid) return;

        // Adopt UUID if we don't have one yet
        if (!actorUuid && payloadActorId) {
            console.log(`[Agent] Adopting Actor ID from delta: ${payloadActorId}`);
            actorUuid = payloadActorId;
            localStorage.setItem('cpr_actor_uuid', actorUuid);
        }

        // A delta only exists because something changed in Foundry, so the
        // connection is demonstrably live.
        isFoundryLive.value = true;

        // Delegate to the delta applier (imported by caller)
        if (window.__applyDelta) {
            window.__applyDelta(payload.delta, { tab: payload.tab });
        }
    };

    /**
     * Called when a component mounts. Subscribes on the first mount.
     */
    const onMount = () => {
        useCount++;
        if (useCount === 1) {
            subscribe();
        }
    };

    /**
     * Called when the last component unmounts. Cleans up.
     */
    const onUnmount = () => {
        useCount--;
        if (useCount === 0 && refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
        }
    };

    return {
        subscribe,
        handleActorData,
        handleSheetDelta,
        onMount,
        onUnmount,
        // Expose shared state for consumers
        charData,
        loading,
        isWssConnected,
        isFoundryLive,
        error,
        errorCode,
        agentLink,
        isGM,
    };
}
