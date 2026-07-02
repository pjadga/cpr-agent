<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Client-facing Reverb Settings
    |--------------------------------------------------------------------------
    |
    | These mirror the VITE_REVERB_* env vars exposed to the browser bundle,
    | making them available server-side (e.g. when building the Foundry setup
    | code). They describe the public-facing WebSocket endpoint, which may
    | differ from the internal REVERB_* values used by the server itself.
    |
    */

    'reverb' => [
        'scheme' => env('VITE_REVERB_SCHEME', 'https'),
        'host' => env('VITE_REVERB_HOST'),
        'port' => env('VITE_REVERB_PORT', 443),
    ],

];