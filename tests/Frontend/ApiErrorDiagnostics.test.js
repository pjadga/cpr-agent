/**
 * Turning a failed request into something a person can act on.
 *
 * The sheet used to print the raw axios message, so a stopped Reverb, a closed
 * Foundry and a brand new account with nothing configured all produced the same
 * sentence: "Request failed with status code 500". These tests pin the mapping
 * that replaced it, and the rule that every code it can produce actually has an
 * explanation behind it.
 */

import { describe, expect, it } from 'vitest';
import { DIAGNOSTICS, classifyApiError, diagnostic } from '@/composables/useFoundryDiagnostics';

/** Shape of an axios error for a response that arrived. */
function httpError(status, data = {}) {
    return { response: { status, data }, message: `Request failed with status code ${status}` };
}

/** Shape of an axios error for a request that never landed. */
function networkError() {
    return { request: {}, message: 'Network Error' };
}

describe('classifyApiError', () => {
    it('prefers the code the server sent over the status', () => {
        // The server knows why it failed; the status is a coarse summary of it.
        expect(classifyApiError(httpError(503, { error: 'realtime_unavailable' })))
            .toBe('realtime_unavailable');
        expect(classifyApiError(httpError(409, { error: 'foundry_not_configured' })))
            .toBe('foundry_not_configured');
    });

    it('ignores a server code it has no wording for', () => {
        // Rendering an unknown code would print a generic panel while claiming
        // to be specific. Fall back to the status, which we can describe.
        expect(classifyApiError(httpError(503, { error: 'some_future_code' })))
            .toBe('realtime_unavailable');
    });

    it('maps a bare 503 to the realtime server being down', () => {
        // A proxy can return 503 without our JSON body.
        expect(classifyApiError(httpError(503))).toBe('realtime_unavailable');
    });

    it('maps a bare 409 to nothing being configured', () => {
        expect(classifyApiError(httpError(409))).toBe('foundry_not_configured');
    });

    it('maps auth failures to being signed out', () => {
        expect(classifyApiError(httpError(401))).toBe('not_authenticated');
        expect(classifyApiError(httpError(419))).toBe('not_authenticated');
    });

    it('maps throttling to its own message', () => {
        expect(classifyApiError(httpError(429))).toBe('rate_limited');
    });

    it('maps the empty-sheet 400 to "no character loaded yet"', () => {
        // /api/actorsheet answers 400 when the account has no character cached,
        // which is the ordinary state before Foundry has ever sent one. It is
        // not a bad request and it is certainly not a pairing failure.
        expect(classifyApiError(httpError(400, { error: 'no_character_data' })))
            .toBe('no_character_data');
    });

    it('does not describe a generic 4xx as a pairing failure', () => {
        // The old catch-all was claim_failed ("Linking failed / the server
        // rejected the pairing"), which is pairing-specific wording shown on
        // data endpoints that have nothing to do with pairing.
        const code = classifyApiError(httpError(400));
        expect(code).toBe('request_rejected');
        expect(diagnostic(code).detail).not.toContain('pairing');
    });

    it('maps a plain 500 to a server-side error rather than guessing a cause', () => {
        // Honest degrade: we know it broke on the server, not why. Saying
        // "Reverb is down" here would be a guess dressed as a diagnosis.
        expect(classifyApiError(httpError(500))).toBe('agent_error');
    });

    it('maps a request that never landed to the Agent being unreachable', () => {
        // No response at all is a different problem from a failed response:
        // the Agent itself is not answering.
        expect(classifyApiError(networkError())).toBe('agent_unreachable');
        expect(classifyApiError({ message: 'Network Error' })).toBe('agent_unreachable');
        expect(classifyApiError(undefined)).toBe('agent_unreachable');
    });

    it('never returns the raw axios sentence', () => {
        // The regression this whole change exists to prevent.
        for (const err of [httpError(500), httpError(503), httpError(409), httpError(400), networkError()]) {
            const code = classifyApiError(err);
            expect(code).not.toContain('status code');
            expect(DIAGNOSTICS[code]).toBeDefined();
        }
    });
});

describe('diagnostic coverage', () => {
    it('gives every code a title, an explanation and a fix', () => {
        // A code with no hint is half an explanation, which is the state the
        // sheet was already in.
        for (const [code, entry] of Object.entries(DIAGNOSTICS)) {
            expect(entry.title, `${code}.title`).toBeTruthy();
            expect(entry.detail, `${code}.detail`).toBeTruthy();
            expect(entry.hint, `${code}.hint`).toBeTruthy();
        }
    });

    it('covers every code classifyApiError can produce', () => {
        const produced = [
            'realtime_unavailable',
            'foundry_not_configured',
            'foundry_no_response',
            'agent_error',
            'agent_unreachable',
            'not_authenticated',
            'rate_limited',
            'request_rejected',
            'no_character_data',
            'no_character_assigned',
        ];

        for (const code of produced) {
            expect(DIAGNOSTICS[code], `missing diagnostic for ${code}`).toBeDefined();
        }
    });

    it('names the separate process a self-hoster has to start for realtime', () => {
        // The single most useful sentence in the whole catalogue: Reverb does
        // not start just because the site is up.
        expect(diagnostic('realtime_unavailable').hint).toContain('agent:serve');
    });

    it('points at the Bridge, not at Reverb, when Foundry stays silent', () => {
        // Same symptom, different fix. Conflating them sends people to restart
        // the wrong thing.
        const hint = diagnostic('foundry_no_response').hint;
        expect(hint).toContain('Bridge');
        expect(hint).not.toContain('agent:serve');
    });

    it('still degrades gracefully for an unknown code', () => {
        expect(diagnostic('nonsense').title).toBe('Something went wrong');
    });
});
