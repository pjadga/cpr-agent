import { onMounted, onUnmounted } from 'vue';

/**
 * Hook to listen for Echo events on a private channel
 * @param {string} channel - The private channel name (default: 'cpr-agent')
 * @param {string} event - The event name to listen for (default: '.FoundryResponse')
 * @param {Function} handler - The callback function for the event
 */
export function useEchoListener(handler, channel = 'cpr-agent', event = '.FoundryResponse') {
    onMounted(() => {
        if (window.Echo) {
            window.Echo.private(channel)
                .listen(event, handler);
        }
    });

    onUnmounted(() => {
        if (window.Echo) {
            window.Echo.private(channel)
                .stopListening(event, handler);
        }
    });
}
