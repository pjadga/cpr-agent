import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import {useCharacterData} from './composables/useCharacterData';
import {isEmbed, initEmbed} from './embed';
import {resolveReverbEndpoint} from './reverb-endpoint';

window.axios = axios;

window.Pusher = Pusher;
window.axios.defaults.withCredentials = true;

const {isWssConnected} = useCharacterData();

if (isEmbed()) {
    // Embedded in Foundry: no Reverb/WebSocket — data flows over postMessage
    // via the Bridge module. initEmbed() installs the mock Echo + axios adapter.
    window.__CPR_EMBED = true;
    window.Echo = initEmbed();
    console.log('[Agent] Embedded mode: postMessage transport active (no Reverb).');
} else {

// Server-injected runtime config (partials/reverb-config.blade.php) wins over
// values baked into the bundle at build time, so prebuilt assets work on any
// host. Final fallback derives everything from the page's own origin, which
// is correct whenever Reverb is reverse-proxied on the same domain.
// See resources/js/reverb-endpoint.js for the full precedence rule.
const endpoint = resolveReverbEndpoint({
    runtime: window.reverbConfig,
    build: import.meta.env,
    location: window.location,
});

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: endpoint.key,
    wsHost: endpoint.host,
    wsPort: endpoint.port,
    wssPort: endpoint.port,
    forceTLS: endpoint.forceTLS,
    // Standard Config
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
    authEndpoint: '/api/broadcasting/auth',
    auth: {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'ngrok-skip-browser-warning': 'true'
        }
    },
    authorizer: (channel, options) => {
        return {
            authorize: (socketId, callback) => {
                axios.post('/api/broadcasting/auth', {
                    socket_id: socketId,
                    channel_name: channel.name
                })
                    .then(response => {
                        callback(false, response.data);
                    })
                    .catch(error => {
                        callback(true, error);
                    });
            }
        };
    },
});

if (window.Echo.connector.pusher) {
    window.Echo.connector.pusher.connection.bind('connected', () => {
        console.log('Pusher connection established');

        isWssConnected.value = true;
    });

    window.Echo.connector.pusher.connection.bind('disconnected', () => {
        isWssConnected.value = false;
    });

    window.Echo.connector.pusher.connection.bind('unavailable', () => {
        isWssConnected.value = false;
    });

    window.Echo.connector.pusher.connection.bind('failed', () => {
        isWssConnected.value = false;
    });
}
}
