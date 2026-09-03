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

    // No defaults on purpose. These describe a Reverb endpoint that is *not*
    // on the page's own origin, which is now the exception rather than the
    // rule: with Reverb reverse-proxied under /app on the app's own listener,
    // the browser derives everything from window.location and none of these
    // are set. Defaulting them to https/443 meant the Foundry setup code
    // confidently advertised a WebSocket endpoint that did not exist.
    'reverb' => [
        'scheme' => env('VITE_REVERB_SCHEME'),
        'host' => env('VITE_REVERB_HOST'),
        'port' => env('VITE_REVERB_PORT'),
    ],

];