import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import {useCharacterData} from './composables/useCharacterData';

window.axios = axios;

window.Pusher = Pusher;
window.axios.defaults.withCredentials = true;

const {isWssConnected} = useCharacterData();

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    // Let the .env file dictate the protocol (http vs https)
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
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
