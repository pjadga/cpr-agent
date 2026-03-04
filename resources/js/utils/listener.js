import { onMounted, onUnmounted } from 'vue';
import { isMyRequest } from './requests';

/**
 * Hook to listen for Echo events on a private channel
 * @param {Function} handler - The callback function for the event
 * @param {string|null} channel - The private channel name (null to use default with channel_id)
 * @param {string} event - The event name to listen for (default: '.FoundryResponse')
 */
export function useEchoListener(handler, channel = null, event = '.FoundryResponse') {
    const channelId = window.AgentConfig?.channelId;
    const effectiveChannel = channel || (channelId ? `cpr-agent.${channelId}` : 'cpr-agent');

    const wrappedHandler = (e) => {
        const payload = e.payload || e;
        const requestId = payload.requestId;

        console.log(`%c[Agent] Incoming Response: ${payload.type}`, "color: #00ff00; font-weight: bold;", payload);

        // Dice rolls and results should ALWAYS be processed, even if initiated by another agent
        const isPublicEvent = ['DICE_ROLL_EVENT', 'ROLL_RESULT'].includes(payload.type);

        if (isPublicEvent || isMyRequest(requestId)) {
            handler(e);
        } else {
            console.log(`Ignoring FoundryResponse with requestId [${requestId}] - initiated by another agent.`);
        }
    };

    onMounted(() => {
        if (window.Echo) {
            window.Echo.private(effectiveChannel)
                .listen(event, wrappedHandler);
        }
    });

    onUnmounted(() => {
        if (window.Echo) {
            window.Echo.private(effectiveChannel)
                .stopListening(event, wrappedHandler);
        }
    });
}
