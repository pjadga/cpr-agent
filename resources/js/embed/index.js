import { createEmbedTransport } from './transport.js';
import { isWssConnected } from '../composables/useWebSocketSubscription.js';

// ==================================================================
// EMBED MODE ENTRYPOINT
//
// Detects whether the Agent is running inside a Foundry-hosted iframe
// and, if so, installs the postMessage transport (mock Echo + axios
// adapter) in place of Reverb/HTTP. See resources/js/embed/transport.js.
// ==================================================================

/** True when the page was served as the in-Foundry embed. */
export function isEmbed() {
    if (window.__CPR_EMBED === true) return true;
    // Fallback: served with a ?nonce and rendered inside a parent frame.
    try {
        return window.parent !== window && new URLSearchParams(location.search).has('nonce');
    } catch (e) {
        return false;
    }
}

/**
 * Wire up embedded transport. Must run after window.axios exists and before
 * the Vue app mounts / fires requests. Returns the MockEcho for window.Echo.
 */
export function initEmbed() {
    const params = new URLSearchParams(location.search);
    const nonce = params.get('nonce') || '';

    // The parent (Foundry) origin is unknown a priori; prefer the referrer's
    // origin as the postMessage target, else fall back to wildcard. Inbound
    // messages are gated by source === parent + nonce, not origin.
    let parentOrigin = '*';
    try { if (document.referrer) parentOrigin = new URL(document.referrer).origin; } catch (e) { /* noop */ }

    // The Foundry origin doubles as the image base URL: character/gear art is
    // served by Foundry at /systems/cyberpunk-red-core/... There is no
    // /api/getbaseurl in embed mode, so seed cpr_base_url here for useImageProxy.
    // NOTE: origin-only; a Foundry ROUTE_PREFIX deployment would need the Bridge
    // to send an authoritative base (TODO: include baseUrl in the welcome payload).
    try {
        if (parentOrigin && parentOrigin !== '*') localStorage.setItem('cpr_base_url', parentOrigin);
    } catch (e) { /* noop */ }

    const { echo, adapter } = createEmbedTransport({ nonce, parentOrigin });

    if (window.axios) {
        window.axios.defaults.adapter = adapter;
    }
    // Local transport is always "connected".
    try { isWssConnected.value = true; } catch (e) { /* noop */ }

    return echo;
}
