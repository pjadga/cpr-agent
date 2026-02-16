import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// 1. Check if we are using HTTPS
const isSecure = window.location.protocol === 'https:';

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,

    // 2. THE DDEV FIX:
    // If Secure (https), use port 8081.
    // If Insecure (http), use port 8080.
    wsPort: isSecure ? 8081 : 8080,
    wssPort: isSecure ? 8081 : 8080,

    // 3. Force TLS if we are on https
    forceTLS: isSecure,

    // Standard Config
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
    authEndpoint: '/api/broadcasting/auth',
    auth: {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    }
});
