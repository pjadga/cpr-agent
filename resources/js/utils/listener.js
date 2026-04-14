import { onMounted, onUnmounted } from 'vue';
import { isMyRequest } from './requests';

/**
 * Hook to listen for Echo events on a private channel
 * @param {Function} handler - The callback function for the event
 * @param {string|null} channel - The private channel name (null to use default with channel_id)
 * @param {string} event - The event name to listen for (default: '.FoundryResponse')
 */
export function useEchoListener(handler, channel = null, event = '.FoundryResponse') {
    const channel_id = window.AgentConfig?.channel_id;
    const effectiveChannel = channel || (channel_id ? `cpr-agent.${channel_id}` : null);

    const wrappedHandler = (e) => {
        const payload = e.payload || e;
        const requestId = payload.requestId;
        const rollMode = payload.rollMode || 'publicroll';
        const isGM = localStorage.getItem('cpr_is_gm') === 'true';
        const myAgentLink = (window.AgentConfig?.agent_link || localStorage.getItem('cpr_agent_link') || '').replace(/\/$/, '');
        const rollerLink = (payload.agentLink || payload.agent_link || '').replace(/\/$/, '');
        const isMe = rollerLink && myAgentLink && rollerLink === myAgentLink;

        console.log(`%c[Agent] Incoming Response: ${payload.type}`, "color: #00ff00; font-weight: bold;", payload);

        console.log("RollMode: ", rollMode)

        // Synchronize public events with useCharacterData.js
        let isPublicEvent = ['ACTOR_DATA', 'SHEET_DELTA'].includes(payload.type);

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
        }

        if (isPublicEvent || isMyRequest(requestId)) {
            handler(e);
        } else {
            console.log(`Listener Ignoring FoundryResponse with type [${payload.type}] and requestId [${requestId}] - privacy settings or initiated by another agent.`);
        }
    };

    onMounted(() => {
        if (window.Echo && effectiveChannel) {
            window.Echo.private(effectiveChannel)
                .listen(event, wrappedHandler);
        }
    });

    onUnmounted(() => {
        if (window.Echo && effectiveChannel) {
            window.Echo.private(effectiveChannel)
                .stopListening(event, wrappedHandler);
        }
    });
}
