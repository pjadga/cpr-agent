/**
 * Foundry pairing diagnostics.
 *
 * Central catalogue of every way QR linking can fail, with plain-language
 * troubleshooting for each, including an answer for "the Agent can't reach
 * Foundry" across both hosted and local-LAN setups.
 */

export const PAYLOAD_VERSION = 1;

/**
 * code -> { title, detail, hint }. `hint` is the actionable troubleshooting step.
 */
export const DIAGNOSTICS = {
    // --- Camera / scanning ---
    insecure_context: {
        title: 'Camera blocked (insecure connection)',
        detail: 'Browsers only allow camera access over HTTPS.',
        hint: 'Open the Agent using an https:// address (or localhost). If you typed an http:// or IP address, switch to the secure URL your GM uses.',
    },
    no_camera_api: {
        title: 'Camera not supported',
        detail: 'This browser did not expose a camera API.',
        hint: 'Use a modern browser (Chrome, Safari, Firefox). On iOS, use Safari rather than an in-app browser.',
    },
    permission_denied: {
        title: 'Camera permission denied',
        detail: 'You declined camera access, or the browser is blocking it.',
        hint: 'Allow camera access for this site in your browser settings, then try again.',
    },
    no_camera: {
        title: 'No camera found',
        detail: 'No camera device is available on this device.',
        hint: 'Use a device with a camera, or ask your GM to read you the code another way.',
    },
    camera_in_use: {
        title: 'Camera unavailable',
        detail: 'Another app is using the camera, or the device blocked it.',
        hint: 'Close other apps using the camera (video calls, other tabs) and try again.',
    },
    camera_error: {
        title: 'Could not start the camera',
        detail: 'The camera failed to start for an unknown reason.',
        hint: 'Reload the page and try again. If it persists, restart the browser.',
    },

    // --- QR payload ---
    qr_unreadable_format: {
        title: 'Unrecognised QR code',
        detail: 'That code is not a Cyberpunk Agent pairing code.',
        hint: 'Make sure you are scanning the QR shown by the GM in Foundry (Manage Characters → the QR button), not another code.',
    },
    qr_version_mismatch: {
        title: 'QR code version mismatch',
        detail: 'This pairing code was made by a different version of the Foundry module.',
        hint: 'Ask your GM to update the Cyberpunk Red Agent Bridge module, then generate a fresh code.',
    },
    qr_wrong_server: {
        title: 'QR code is for a different Agent',
        detail: 'This code points at another Agent server than the one you are logged in to.',
        hint: 'Log in to the Agent address your GM uses, then scan again.',
    },
    qr_invalid: {
        title: 'Incomplete pairing code',
        detail: 'The code is missing required information.',
        hint: 'Ask your GM to generate a fresh QR code.',
    },

    // --- Claim (server) ---
    pairing_not_found: {
        title: 'Pairing not recognised',
        detail: 'The server does not recognise this code.',
        hint: 'Ask your GM to generate a fresh QR code.',
    },
    pairing_already_claimed: {
        title: 'Code already used',
        detail: 'This one-time code has already been claimed.',
        hint: 'Ask your GM for a new QR code.',
    },
    pairing_expired: {
        title: 'Code expired',
        detail: 'Pairing codes are valid for only a few minutes.',
        hint: 'Ask your GM to generate a fresh QR code and scan it promptly.',
    },
    not_authenticated: {
        title: 'You are signed out',
        detail: 'Your Agent session has expired.',
        hint: 'Sign in to the Agent again, then retry the scan.',
    },
    claim_failed: {
        title: 'Linking failed',
        detail: 'The server rejected the pairing.',
        hint: 'Try again. If it keeps failing, ask your GM to regenerate the code.',
    },
    agent_unreachable: {
        title: 'Cannot reach the Agent server',
        detail: 'The request to the Agent server failed.',
        hint: 'Check your internet connection and reload the page.',
    },

    // --- Foundry reachability (after a successful claim) ---
    no_foundry_url: {
        title: 'No Foundry address configured',
        detail: 'The session did not include a Foundry URL, so character images may not load.',
        hint: 'Ask your GM to set the Foundry URL in their Agent session settings.',
    },
    foundry_bad_url: {
        title: 'Invalid Foundry address',
        detail: 'The configured Foundry URL is not a valid address.',
        hint: 'Ask your GM to correct the Foundry URL in their Agent session settings.',
    },
    mixed_content: {
        title: "Can't reach Foundry: blocked as insecure",
        detail: 'The Agent is loaded over HTTPS but Foundry is served over plain http on a local address. Browsers block this "mixed content".',
        hint: 'Either have the GM expose Foundry over HTTPS (e.g. a tunnel/hosted setup), or access Foundry images via the Agent proxy. Character data still syncs live; only direct image loading is affected.',
    },
    foundry_timeout: {
        title: "Can't reach Foundry: timed out",
        detail: 'Foundry did not respond in time.',
        hint: 'If the GM hosts Foundry locally, make sure you are on the same network (or they expose it publicly). If hosted, it may be down — ask your GM.',
    },
    foundry_unreachable: {
        title: "Can't reach Foundry",
        detail: "The Foundry address could not be reached from your device.",
        hint: 'If Foundry runs on the GM\'s local network, you must be on that same network. If it is hosted, confirm the address is correct and online with your GM.',
    },

    // --- GM round-trip ---
    no_gm_confirmation: {
        title: 'Linked, but the GM\'s Foundry did not confirm',
        detail: 'Your Agent is configured, but the GM\'s Foundry did not acknowledge the link yet.',
        hint: "Make sure the GM's Foundry is open with the Agent Bridge module connected. Character data will start flowing once it is.",
    },
};

export function diagnostic(code) {
    return DIAGNOSTICS[code] || {
        title: 'Something went wrong',
        detail: 'An unexpected error occurred.',
        hint: 'Reload the page and try again.',
    };
}

/**
 * Map a getUserMedia DOMException to a diagnostic code.
 */
export function classifyCameraError(err) {
    switch (err?.name) {
        case 'NotAllowedError':
        case 'SecurityError':
            return 'permission_denied';
        case 'NotFoundError':
        case 'OverconstrainedError':
            return 'no_camera';
        case 'NotReadableError':
        case 'AbortError':
            return 'camera_in_use';
        default:
            return 'camera_error';
    }
}

/**
 * Decode a pairing code from either raw JSON (scanned QR) or a base64-encoded
 * JSON blob (pasted "link code"). Returns the parsed object, or null if neither.
 */
function decodePairingText(text) {
    const trimmed = (text || '').trim();
    if (!trimmed) return null;

    // Raw JSON first (what the QR encodes).
    try {
        return JSON.parse(trimmed);
    } catch { /* fall through to base64 */ }

    // Base64-wrapped JSON (the copyable paste code).
    try {
        return JSON.parse(atob(trimmed));
    } catch {
        return null;
    }
}

/**
 * Validate the scanned/pasted text and return { payload } or { error } code.
 */
export function parseQrPayload(text, currentOrigin = window.location.origin) {
    const data = decodePairingText(text);
    if (!data) {
        return { error: 'qr_unreadable_format' };
    }

    if (!data || typeof data !== 'object' || !data.nonce) {
        // Distinguish a structurally-valid-but-foreign code from junk.
        return { error: data && data.v ? 'qr_invalid' : 'qr_unreadable_format' };
    }
    if (data.v !== PAYLOAD_VERSION) {
        return { error: 'qr_version_mismatch' };
    }
    if (data.agentUrl) {
        try {
            if (new URL(data.agentUrl).origin !== currentOrigin) {
                return { error: 'qr_wrong_server' };
            }
        } catch {
            /* ignore malformed agentUrl; nonce claim is the source of truth */
        }
    }
    return { payload: data };
}

/**
 * Map a failed claim HTTP response to a diagnostic code.
 */
export function classifyClaimError(status, body) {
    if (body && body.error && DIAGNOSTICS[body.error]) return body.error;
    if (status === 401 || status === 419) return 'not_authenticated';
    if (status === 0) return 'agent_unreachable';
    return 'claim_failed';
}

/**
 * Best-effort reachability probe for the GM's Foundry from this device.
 * Returns a diagnostic code, or null if reachable.
 */
export async function probeFoundry(foundryUrl, { timeoutMs = 5000 } = {}) {
    if (!foundryUrl) return 'no_foundry_url';

    let url;
    try {
        url = new URL(foundryUrl);
    } catch {
        return 'foundry_bad_url';
    }

    // Mixed content: an https page cannot fetch http (browsers block it outright).
    if (window.location.protocol === 'https:' && url.protocol === 'http:') {
        return 'mixed_content';
    }

    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);
    try {
        // no-cors: an opaque resolve means the host answered (reachable).
        await fetch(url.origin, { mode: 'no-cors', signal: controller.signal, cache: 'no-store' });
        return null;
    } catch (e) {
        return e.name === 'AbortError' ? 'foundry_timeout' : 'foundry_unreachable';
    } finally {
        clearTimeout(timer);
    }
}
