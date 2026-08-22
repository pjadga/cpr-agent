import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import {useCharacterData} from './composables/useCharacterData';

window.axios = axios;

window.Pusher = Pusher;
window.axios.defaults.withCredentials = true;

const {isWssConnected} = useCharacterData();

// Server-injected runtime config (partials/reverb-config.blade.php) wins over
// values baked into the bundle at build time, so prebuilt assets work on any
// host. Final fallback derives everything from the page's own origin, which
// is correct whenever Reverb is reverse-proxied on the same domain.
const reverbConfig = window.reverbConfig ?? {};
const reverbScheme = reverbConfig.scheme
    ?? import.meta.env.VITE_REVERB_SCHEME
    ?? window.location.protocol.replace(':', '');
const reverbPort = reverbConfig.port
    ?? import.meta.env.VITE_REVERB_PORT
    ?? (window.location.port || (reverbScheme === 'https' ? 443 : 80));

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: reverbConfig.key ?? import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: reverbConfig.host ?? import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
    wsPort: reverbPort,
    wssPort: reverbPort,
    forceTLS: reverbScheme === 'https',
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
