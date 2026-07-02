/**
 * Tests for the QR pairing diagnostics catalogue and classifiers.
 */

import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    diagnostic,
    classifyCameraError,
    parseQrPayload,
    classifyClaimError,
    probeFoundry,
    PAYLOAD_VERSION,
} from '@/composables/useFoundryDiagnostics';

const ORIGIN = 'https://agent.example.com';

function validPayload(overrides = {}) {
    return JSON.stringify({
        v: PAYLOAD_VERSION,
        agentUrl: ORIGIN,
        channelId: 'NEON-7X2',
        nonce: 'abc123',
        ...overrides,
    });
}

describe('diagnostic()', () => {
    it('returns a known entry', () => {
        expect(diagnostic('mixed_content').title).toContain('insecure');
    });

    it('falls back for unknown codes', () => {
        expect(diagnostic('nope').title).toBe('Something went wrong');
    });
});

describe('classifyCameraError', () => {
    it.each([
        ['NotAllowedError', 'permission_denied'],
        ['SecurityError', 'permission_denied'],
        ['NotFoundError', 'no_camera'],
        ['NotReadableError', 'camera_in_use'],
        ['WeirdError', 'camera_error'],
    ])('maps %s -> %s', (name, code) => {
        expect(classifyCameraError({ name })).toBe(code);
    });
});

describe('parseQrPayload', () => {
    it('accepts a valid payload from the same server', () => {
        const { payload, error } = parseQrPayload(validPayload(), ORIGIN);
        expect(error).toBeUndefined();
        expect(payload.nonce).toBe('abc123');
    });

    it('rejects non-JSON', () => {
        expect(parseQrPayload('not json', ORIGIN).error).toBe('qr_unreadable_format');
    });

    it('rejects a foreign QR with no nonce as unreadable', () => {
        expect(parseQrPayload(JSON.stringify({ hello: 1 }), ORIGIN).error).toBe('qr_unreadable_format');
    });

    it('flags a version mismatch', () => {
        expect(parseQrPayload(validPayload({ v: 99 }), ORIGIN).error).toBe('qr_version_mismatch');
    });

    it('flags a QR for a different agent server', () => {
        expect(parseQrPayload(validPayload({ agentUrl: 'https://other.example.com' }), ORIGIN).error)
            .toBe('qr_wrong_server');
    });

    it('treats a versioned-but-nonceless payload as invalid', () => {
        expect(parseQrPayload(JSON.stringify({ v: PAYLOAD_VERSION }), ORIGIN).error).toBe('qr_invalid');
    });

    it('accepts a base64-wrapped pasted link code', () => {
        const code = btoa(validPayload());
        const { payload, error } = parseQrPayload(code, ORIGIN);
        expect(error).toBeUndefined();
        expect(payload.nonce).toBe('abc123');
    });

    it('trims whitespace around a pasted code', () => {
        const { payload, error } = parseQrPayload(`  ${validPayload()}\n`, ORIGIN);
        expect(error).toBeUndefined();
        expect(payload.nonce).toBe('abc123');
    });

    it('rejects an empty paste', () => {
        expect(parseQrPayload('   ', ORIGIN).error).toBe('qr_unreadable_format');
    });
});

describe('classifyClaimError', () => {
    it('passes through known server error codes', () => {
        expect(classifyClaimError(410, { error: 'pairing_expired' })).toBe('pairing_expired');
    });

    it('maps auth failures', () => {
        expect(classifyClaimError(401, {})).toBe('not_authenticated');
        expect(classifyClaimError(419, {})).toBe('not_authenticated');
    });

    it('maps network failure (status 0)', () => {
        expect(classifyClaimError(0, null)).toBe('agent_unreachable');
    });

    it('falls back to claim_failed', () => {
        expect(classifyClaimError(500, {})).toBe('claim_failed');
    });
});

describe('probeFoundry', () => {
    const originalLocation = window.location;

    afterEach(() => {
        vi.unstubAllGlobals();
        Object.defineProperty(window, 'location', { value: originalLocation, configurable: true });
    });

    function stubHttpsPage() {
        Object.defineProperty(window, 'location', {
            value: { protocol: 'https:', origin: ORIGIN },
            configurable: true,
        });
    }

    it('returns no_foundry_url when missing', async () => {
        expect(await probeFoundry('')).toBe('no_foundry_url');
    });

    it('returns foundry_bad_url for garbage', async () => {
        expect(await probeFoundry('::::')).toBe('foundry_bad_url');
    });

    it('detects mixed content (https page, http foundry)', async () => {
        stubHttpsPage();
        expect(await probeFoundry('http://192.168.1.50:30000')).toBe('mixed_content');
    });

    it('returns null when the host is reachable', async () => {
        stubHttpsPage();
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({}));
        expect(await probeFoundry('https://foundry.example.com')).toBeNull();
    });

    it('returns foundry_unreachable when fetch rejects', async () => {
        stubHttpsPage();
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('refused')));
        expect(await probeFoundry('https://foundry.example.com')).toBe('foundry_unreachable');
    });

    it('returns foundry_timeout when aborted', async () => {
        stubHttpsPage();
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(Object.assign(new Error('abort'), { name: 'AbortError' })));
        expect(await probeFoundry('https://foundry.example.com')).toBe('foundry_timeout');
    });
});
