import { onMounted, onUnmounted } from 'vue';
import { isMyRequest } from './requests';

/**
 * Hook to listen for Echo events on a private channel
 * @param {Function} handler - The callback function for the event
 * @param {string} channel - The private channel name (default: 'cpr-agent')
 * @param {string} event - The event name to listen for (default: '.FoundryResponse')
 */
export function useEchoListener(handler, channel = 'cpr-agent', event = '.FoundryResponse') {
    const wrappedHandler = (e) => {
        const payload = e.payload || e;
        const requestId = payload.requestId;

        if (isMyRequest(requestId)) {
            handler(e);
        } else {
            console.log(`Ignoring FoundryResponse with requestId [${requestId}] - initiated by another agent.`);
        }
    };

    onMounted(() => {
        if (window.Echo) {
            window.Echo.private(channel)
                .listen(event, wrappedHandler);
        }
    });

    onUnmounted(() => {
        if (window.Echo) {
            window.Echo.private(channel)
                .stopListening(event, wrappedHandler);
        }
    });
}
