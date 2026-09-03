/**
 * Where the browser should dial for the WebSocket.
 *
 * Three sources, in falling order of authority:
 *
 *   1. window.reverbConfig  — injected per-request by the server
 *      (partials/reverb-config.blade.php). Wins so that one prebuilt asset
 *      bundle works on any host without a rebuild.
 *   2. import.meta.env      — VITE_REVERB_* baked in at build time.
 *   3. window.location      — the page's own origin.
 *
 * (3) is the normal case now that Reverb is reverse-proxied under /app on the
 * app's own listener: an https page derives wss on 443, an http page derives ws
 * on 80, and neither needs to be told. That is also why (1) and (2) must be
 * genuinely absent rather than defaulted — a stale VITE_REVERB_SCHEME=https on
 * a plain-http install points the browser at an endpoint that does not exist,
 * and the only symptom is a sheet that never updates.
 *
 * Kept as a pure function so the derivation can be tested across the
 * http/https matrix without constructing an Echo client.
 *
 * @param {object}  options
 * @param {object}  options.runtime   window.reverbConfig
 * @param {object}  options.build     import.meta.env
 * @param {Location} options.location  window.location
 * @returns {{key: string|undefined, host: string, port: number|string, scheme: string, forceTLS: boolean}}
 */
export function resolveReverbEndpoint({runtime = {}, build = {}, location} = {}) {
    const scheme = runtime.scheme
        ?? build.VITE_REVERB_SCHEME
        ?? location.protocol.replace(':', '');

    const port = runtime.port
        ?? build.VITE_REVERB_PORT
        ?? (location.port || (scheme === 'https' ? 443 : 80));

    return {
        key: runtime.key ?? build.VITE_REVERB_APP_KEY,
        host: runtime.host ?? build.VITE_REVERB_HOST ?? location.hostname,
        port,
        scheme,
        forceTLS: scheme === 'https',
    };
}
