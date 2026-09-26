/**
 * The browser half of the http/https matrix.
 *
 *   A. http  Agent -> https Foundry   LAN-hosted Agent, Foundry behind a tunnel
 *   B. http  Agent -> http  Foundry   both on the LAN, the plain self-host case
 *   C. https Agent -> http  Foundry   hosted/Herd/Tailscale Agent, LAN Foundry
 *
 * Three browser rules decide whether each one works:
 *
 *   - an https page may not load http subresources (mixed content), which is
 *     what the image proxy exists to route around;
 *   - the WebSocket endpoint has to be derived from the page's own origin
 *     unless the server explicitly says otherwise;
 *   - the diagnostics have to name the scheme when it is the cause, because
 *     nothing else in the UI will.
 *
 * The server half is in tests/Feature/ProtocolMatrixTest.php.
 */

import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {resolveReverbEndpoint} from '@/reverb-endpoint';
import {useImageProxy} from '@/composables/useImageProxy';
import {probeFoundry} from '@/composables/useFoundryDiagnostics';

const originalLocation = window.location;

function stubPage({protocol = 'https:', hostname = 'agent.example.com', port = ''} = {}) {
    const origin = port ? `${protocol}//${hostname}:${port}` : `${protocol}//${hostname}`;

    Object.defineProperty(window, 'location', {
        value: {protocol, hostname, port, origin, href: `${origin}/`},
        configurable: true,
    });

    return window.location;
}

afterEach(() => {
    vi.unstubAllGlobals();
    Object.defineProperty(window, 'location', {value: originalLocation, configurable: true});
});

// =====================================================================
// Reverb endpoint derivation
// =====================================================================

describe('resolveReverbEndpoint', () => {
    it('derives wss:443 from an https page when the server says nothing', () => {
        // Topology A and C. Reverb is reverse-proxied under /app on the app's
        // own listener, so the page origin is the whole answer.
        const endpoint = resolveReverbEndpoint({location: stubPage({protocol: 'https:'})});

        expect(endpoint).toMatchObject({
            host: 'agent.example.com',
            port: 443,
            scheme: 'https',
            forceTLS: true,
        });
    });

    it('derives ws:80 from an http page when the server says nothing', () => {
        // Topology B. This is the case that used to break: config/vite.php
        // defaulted the scheme to https and the port to 443, so a plain-http
        // self-host dialled wss://host:443 and nothing was listening.
        const endpoint = resolveReverbEndpoint({location: stubPage({protocol: 'http:', hostname: '192.168.1.50'})});

        expect(endpoint).toMatchObject({
            host: '192.168.1.50',
            port: 80,
            scheme: 'http',
            forceTLS: false,
        });
    });

    it('keeps a non-standard page port', () => {
        // `php artisan serve` on :8000 with Reverb proxied on the same
        // listener, the WebSocket is on 8000 too, not on 80.
        const endpoint = resolveReverbEndpoint({
            location: stubPage({protocol: 'http:', hostname: '192.168.1.50', port: '8000'}),
        });

        expect(endpoint).toMatchObject({port: '8000', scheme: 'http', forceTLS: false});
    });

    it('lets the server-injected runtime config override the page origin', () => {
        // A genuine split origin: Reverb really is somewhere else.
        const endpoint = resolveReverbEndpoint({
            runtime: {key: 'runtime-key', host: 'ws.example.com', port: 8085, scheme: 'https'},
            build: {VITE_REVERB_HOST: 'stale.example.com', VITE_REVERB_PORT: 9999, VITE_REVERB_SCHEME: 'http'},
            location: stubPage({protocol: 'http:'}),
        });

        expect(endpoint).toMatchObject({
            key: 'runtime-key',
            host: 'ws.example.com',
            port: 8085,
            scheme: 'https',
            forceTLS: true,
        });
    });

    it('prefers runtime config over build-time config field by field', () => {
        // Runtime supplies only the key; host/port/scheme still fall through to
        // the build values rather than being blanked by a partial override.
        const endpoint = resolveReverbEndpoint({
            runtime: {key: 'runtime-key'},
            build: {VITE_REVERB_HOST: 'ws.example.com', VITE_REVERB_PORT: 8085, VITE_REVERB_SCHEME: 'https'},
            location: stubPage({protocol: 'http:'}),
        });

        expect(endpoint).toMatchObject({key: 'runtime-key', host: 'ws.example.com', port: 8085, scheme: 'https'});
    });

    it('falls back to the page origin when runtime config is absent entirely', () => {
        // window.reverbConfig is undefined on a page that did not include the
        // partial; the default parameter has to absorb that, not throw.
        const endpoint = resolveReverbEndpoint({
            runtime: undefined,
            build: {},
            location: stubPage({protocol: 'https:', hostname: 'cyberpunk.ddev.site'}),
        });

        expect(endpoint).toMatchObject({host: 'cyberpunk.ddev.site', port: 443, scheme: 'https'});
    });

    it('is not fooled by a build-time scheme left over from a split-origin install', () => {
        // Documenting the trap rather than defending against it: a stale
        // VITE_REVERB_SCHEME=https in .env still beats an http page. This is
        // exactly why the env templates ship with no VITE_REVERB_* at all.
        const endpoint = resolveReverbEndpoint({
            build: {VITE_REVERB_SCHEME: 'https', VITE_REVERB_PORT: 8081},
            location: stubPage({protocol: 'http:', hostname: '192.168.1.50'}),
        });

        expect(endpoint.scheme).toBe('https');
        expect(endpoint.forceTLS).toBe(true);
        expect(endpoint.port).toBe(8081);
    });
});

// =====================================================================
// Image loading across the matrix
// =====================================================================

describe('useImageProxy.getImageUrl', () => {
    let store;

    beforeEach(() => {
        store = {};
        vi.stubGlobal('localStorage', {
            getItem: (k) => (k in store ? store[k] : null),
            setItem: (k, v) => {
                store[k] = String(v);
            },
            removeItem: (k) => {
                delete store[k];
            },
            clear: () => {
                store = {};
            },
        });
    });

    function proxyFor(pageProtocol, foundryUrl) {
        stubPage({protocol: pageProtocol});
        store.cpr_base_url = foundryUrl;

        return useImageProxy();
    }

    it('proxies an http Foundry image on an https page (topology C)', () => {
        // The browser would block this outright, and the only visible symptom
        // is missing token art while character data keeps syncing, it reads as
        // a cosmetic glitch rather than the scheme mismatch it is.
        const {getImageUrl} = proxyFor('https:', 'http://192.168.1.60:30000');

        const url = getImageUrl('/tokens/solo.png');

        expect(url).toBe(
            '/api/proxy-image?url=' + encodeURIComponent('http://192.168.1.60:30000/tokens/solo.png'),
        );
    });

    it('loads an https Foundry image directly on an http page (topology A)', () => {
        // The reverse direction is not blocked: an http page may load https
        // subresources. Proxying here would be pointless server load.
        const {getImageUrl} = proxyFor('http:', 'https://foundry.example.com');

        expect(getImageUrl('/tokens/solo.png')).toBe('https://foundry.example.com/tokens/solo.png');
    });

    it('loads an http Foundry image directly on an http page (topology B)', () => {
        const {getImageUrl} = proxyFor('http:', 'http://192.168.1.60:30000');

        expect(getImageUrl('/tokens/solo.png')).toBe('http://192.168.1.60:30000/tokens/solo.png');
    });

    it('loads an https Foundry image directly on an https page', () => {
        const {getImageUrl} = proxyFor('https:', 'https://foundry.example.com');

        expect(getImageUrl('/tokens/solo.png')).toBe('https://foundry.example.com/tokens/solo.png');
    });

    it('proxies ngrok on every scheme, for the interstitial rather than for mixed content', () => {
        // ngrok answers a plain browser request with its warning page; the
        // proxy sends the skip header. That is scheme-independent.
        const {getImageUrl} = proxyFor('https:', 'https://abc123.ngrok-free.dev');

        expect(getImageUrl('/tokens/solo.png')).toContain('/api/proxy-image?url=');
    });

    it('joins a relative path onto the base without doubling the slash', () => {
        const {getImageUrl} = proxyFor('http:', 'http://192.168.1.60:30000');

        expect(getImageUrl('tokens/solo.png')).toBe('http://192.168.1.60:30000/tokens/solo.png');
    });

    it('rewrites a bare systems/ path to an absolute one before joining', () => {
        const {getImageUrl} = proxyFor('http:', 'http://192.168.1.60:30000');

        expect(getImageUrl('systems/cyberpunk-red-core/icons/x.svg'))
            .toBe('http://192.168.1.60:30000/systems/cyberpunk-red-core/icons/x.svg');
    });

    it('falls back to a local default icon when there is no path', () => {
        const {getImageUrl} = proxyFor('https:', 'http://192.168.1.60:30000');

        // Same-origin, so it survives every topology.
        expect(getImageUrl(null)).toBe('/systems/cyberpunk-red-core/icons/compendium/default/Default_Program.svg');
    });

    it('leaves an absolute https URL alone on an https page', () => {
        const {getImageUrl} = proxyFor('https:', 'https://foundry.example.com');

        expect(getImageUrl('https://cdn.example.com/art.png')).toBe('https://cdn.example.com/art.png');
    });

    it('proxies an absolute http URL on an https page', () => {
        // The proxy will refuse this server-side unless it is the user's own
        // Foundry host, see ProtocolMatrixTest, but the client is right to
        // route it there rather than emit a URL the browser will block.
        const {getImageUrl} = proxyFor('https:', 'https://foundry.example.com');

        expect(getImageUrl('http://foundry.example.com/art.png')).toContain('/api/proxy-image?url=');
    });
});

// =====================================================================
// Diagnostics: naming the scheme when it is the cause
// =====================================================================

describe('probeFoundry across the matrix', () => {
    it('names mixed content for https Agent -> http Foundry (topology C)', async () => {
        stubPage({protocol: 'https:'});

        expect(await probeFoundry('http://192.168.1.60:30000')).toBe('mixed_content');
    });

    it('does not cry mixed content for http Agent -> https Foundry (topology A)', async () => {
        stubPage({protocol: 'http:', hostname: '192.168.1.50'});
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({}));

        expect(await probeFoundry('https://foundry.example.com')).toBeNull();
    });

    it('does not cry mixed content for http Agent -> http Foundry (topology B)', async () => {
        stubPage({protocol: 'http:', hostname: '192.168.1.50'});
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({}));

        expect(await probeFoundry('http://192.168.1.60:30000')).toBeNull();
    });

    it('reports unreachable rather than mixed content when the scheme is fine', async () => {
        // The distinction matters: one is a configuration problem the GM can
        // fix by changing a URL, the other is the Foundry box being off.
        stubPage({protocol: 'https:'});
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('refused')));

        expect(await probeFoundry('https://foundry.example.com')).toBe('foundry_unreachable');
    });
});
