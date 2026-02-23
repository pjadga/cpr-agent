import axios from 'axios';
window.axios = axios;
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// 1. Check if we are using HTTPS
const isSecure = window.location.protocol === 'https:';

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,

    // Uncomment for DDEV
    // wsPort: isSecure ? 8081 : 8080,
    // wssPort: isSecure ? 8081 : 8080,

    //Production ports
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
