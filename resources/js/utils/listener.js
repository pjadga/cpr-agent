import {onMounted, onUnmounted} from 'vue';
import {isMyActor, isMyRequest} from './requests';

const sharedListeners = new Map();

/**
 * Hook to listen for Echo events on a private channel
 * @param {Function} handler - The callback function for the event
 * @param {string|null} channel - The private channel name (null to use default with channel_id)
 * @param {string} event - The event name to listen for (default: '.FoundryResponse')
 */
export function useEchoListener(handler, channel = null, event = '.FoundryResponse') {
    const channel_id = window.AgentConfig?.channel_id;
    // In the Foundry embed there is no Reverb channel_id: window.Echo is the
    // MockEcho fed by postMessage and it ignores the channel name. We still must
    // register on *some* channel or onMounted short-circuits and this listener
    // never hears ROLL_MODIFIERS_DATA / NET_POOL_DATA / DICE_ROLL_EVENT. Use the
    // same synthetic name as useWebSocketSubscription so the guards below pass.
    const effectiveChannel = channel
        || (channel_id ? `cpr-agent.${channel_id}` : (window.__CPR_EMBED ? 'cpr-agent.embed' : null));

    const wrappedHandler = (e) => {
        const payload = e.payload || e;
        const requestId = payload.requestId;
        const rollMode = payload.rollMode || 'publicroll';
        const isGM = localStorage.getItem('cpr_is_gm') === 'true';
        const myAgentLink = (window.AgentConfig?.agent_link || localStorage.getItem('cpr_agent_link') || '').replace(/\/$/, '');
        const rollerLink = (payload.agentLink || payload.agent_link || '').replace(/\/$/, '');
        const isMe = rollerLink === myAgentLink;


        let isPublicEvent = false;
        let isForMe = false;

        // if (['ACTOR_DATA', 'SHEET_DELTA'].includes(payload.type)) {
        //     // Route to the specific actor's owner only; GMs see everything
        //     const payloadActorId = payload.actorId;
        //     isPublicEvent = isMyRequest(requestId);
        //     isForMe = isMyActor(payloadActorId);
        //
        // } else

        if (['DICE_ROLL_EVENT', 'ROLL_RESULT'].includes(payload.type)) {
            if (rollMode === 'publicroll') {
                isPublicEvent = true;
            } else if (rollMode === 'gmroll') {
                isPublicEvent = isGM || isMe;
            } else if (rollMode === 'blindroll') {
                isPublicEvent = isGM;
            } else if (rollMode === 'selfroll') {
                isPublicEvent = isMe;
            }
        } else {
            // Cover the other return calls, could be either request-specific or for an actor
            const payloadActorId = payload.actorId;
            isPublicEvent = isMyRequest(requestId);
            isForMe = isMyActor(payloadActorId);
        }

        if (isPublicEvent || isForMe) {
            handler(e);
        } else {
            console.log(`Listener Ignoring FoundryResponse with type [${payload.type}] and requestId [${requestId}] - privacy settings or initiated by another agent.`);
        }
    };

    onMounted(() => {
        if (window.Echo && effectiveChannel) {
            const channelKey = `${effectiveChannel}:${event}`;

            if (!sharedListeners.has(channelKey)) {
                const masterHandler = (e) => {
                    const payload = e.payload || e;
                    console.log(`%c[Agent] Incoming Response: ${payload.type}`, "color: #00ff00; font-weight: bold;", payload);
                    // Dispatch to all registered handlers for this channel/event
                    const listeners = sharedListeners.get(channelKey);
                    if (listeners) {
                        listeners.handlers.forEach(h => h(e));
                    }
                };

                sharedListeners.set(channelKey, {
                    masterHandler,
                    handlers: new Set([wrappedHandler])
                });

                window.Echo.private(effectiveChannel)
                    .listen(event, masterHandler);
            } else {
                sharedListeners.get(channelKey).handlers.add(wrappedHandler);
            }
        }
    });

    onUnmounted(() => {
        if (window.Echo && effectiveChannel) {
            const channelKey = `${effectiveChannel}:${event}`;
            const listenerData = sharedListeners.get(channelKey);

            if (listenerData) {
                listenerData.handlers.delete(wrappedHandler);

                if (listenerData.handlers.size === 0) {
                    window.Echo.private(effectiveChannel)
                        .stopListening(event, listenerData.masterHandler);
                    sharedListeners.delete(channelKey);
                }
            }
        }
    });
}
