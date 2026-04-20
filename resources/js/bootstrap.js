import axios from 'axios';

window.axios = axios;
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// 1. Check if we are using HTTPS
const isSecure = window.location.protocol === 'https:';

import {useCharacterData} from './composables/useCharacterData';

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
            'Accept': 'application/json',
            'ngrok-skip-browser-warning': 'true'
        }
    }
});

if (window.Echo.connector.pusher) {
    window.Echo.connector.pusher.connection.bind('connected', () => {
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
