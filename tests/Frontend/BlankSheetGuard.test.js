/**
 * The blank dashboard.
 *
 * character-sheet.vue renders exactly three branches: loading, an error panel,
 * and the sheet itself. All three are conditional, so a state that satisfies
 * none of them renders literally nothing -- and that is what a fresh account
 * hit. /api/actorsheet answers 400 (no character cached yet), the fallback
 * caught it, set `loading = false` and stopped, leaving charData null with no
 * error set. Every branch false, empty page, reason only in the console.
 *
 * These tests pin the invariant rather than the wiring: whenever loading
 * finishes, at least one branch must be able to render.
 */

import { describe, expect, it } from 'vitest';
import { classifyApiError } from '@/composables/useFoundryDiagnostics';

/**
 * The template's branch conditions, transcribed from character-sheet.vue:
 *
 *   v-if="loading"
 *   v-else-if="(error || errorCode) && !charData"
 *   v-if="!loading && charData && !error"
 */
function branches({ loading, charData, error, errorCode }) {
    return {
        loading: Boolean(loading),
        errorPanel: Boolean(!loading && (error || errorCode) && !charData),
        sheet: Boolean(!loading && charData && !error),
    };
}

function rendersSomething(state) {
    return Object.values(branches(state)).some(Boolean);
}

/** The settle step the fallback now performs when it finishes empty-handed. */
function settle(state, lastFailure) {
    state.loading = false;

    if (!state.charData && !state.error && !state.errorCode) {
        state.errorCode = lastFailure || 'no_character_data';
    }

    return state;
}

const empty = () => ({ loading: true, charData: null, error: null, errorCode: null });

/**
 * The OFFLINE badge, transcribed from character-sheet.vue:
 *
 *   v-if="!isLive"   where   isLive = isWssConnected && isFoundryLive
 */
function showsOfflineBadge({ isWssConnected, isFoundryLive }) {
    return !(isWssConnected && isFoundryLive);
}

describe('the OFFLINE badge', () => {
    it('shows when Reverb is up but Foundry has never answered', () => {
        // The regression: the badge keyed off isWssConnected alone, which is
        // true as soon as Reverb accepts the socket. Start the realtime server
        // with no Foundry running and the sheet quietly claimed to be live
        // while serving month-old cached data.
        expect(showsOfflineBadge({ isWssConnected: true, isFoundryLive: false })).toBe(true);
    });

    it('shows when the socket itself is down', () => {
        // The case it always handled correctly.
        expect(showsOfflineBadge({ isWssConnected: false, isFoundryLive: false })).toBe(true);
    });

    it('hides once Foundry has actually sent something', () => {
        expect(showsOfflineBadge({ isWssConnected: true, isFoundryLive: true })).toBe(false);
    });

    it('shows again if the socket drops after Foundry had been live', () => {
        // Having heard from Foundry once is not a promise about now: with the
        // socket gone nothing further can reach us.
        expect(showsOfflineBadge({ isWssConnected: false, isFoundryLive: true })).toBe(true);
    });
});

describe('the sheet always renders one branch', () => {
    it('would have gone blank under the old give-up path', () => {
        // Guarding the guard: if this ever starts passing, the branch
        // conditions have changed and the rest of this file is testing nothing.
        const old = { ...empty(), loading: false };

        expect(rendersSomething(old)).toBe(false);
    });

    it('shows a reason when the cached sheet 400s and Foundry never answers', () => {
        // Chris's exact sequence: Reverb up, no Foundry, fresh account.
        const state = empty();
        const lastFailure = classifyApiError({
            response: { status: 400, data: { error: 'no_character_data' } },
        });

        settle(state, lastFailure);

        expect(state.errorCode).toBe('no_character_data');
        expect(rendersSomething(state)).toBe(true);
    });

    it('falls back to no_character_data when nothing even threw', () => {
        // The endpoint can answer 200 with an empty body; there is no error to
        // classify, but there is still nothing to draw.
        const state = settle(empty(), null);

        expect(state.errorCode).toBe('no_character_data');
        expect(rendersSomething(state)).toBe(true);
    });

    it('keeps a more specific cause when one is known', () => {
        // A dead Reverb must not be relabelled as "no character yet": the fix
        // is completely different.
        const state = settle(empty(), 'realtime_unavailable');

        expect(state.errorCode).toBe('realtime_unavailable');
    });

    it('does not overwrite an error that was already reported', () => {
        const state = { ...empty(), errorCode: 'no_character_assigned' };

        settle(state, 'no_character_data');

        expect(state.errorCode).toBe('no_character_assigned');
    });

    it('never shows the error panel on top of a loaded sheet', () => {
        // The two are separate v-ifs, so an error arriving after a good load
        // used to paint a full-screen panel over the sheet.
        const rendered = branches({
            loading: false,
            charData: { name: 'V' },
            error: null,
            errorCode: 'realtime_unavailable',
        });

        expect(rendered.sheet).toBe(true);
        expect(rendered.errorPanel).toBe(false);
    });

    it('does not raise an error when the data actually arrived', () => {
        // The success path must stay silent, or every load ends in a red panel.
        const state = { ...empty(), charData: { name: 'V' } };

        settle(state, 'no_character_data');

        expect(state.errorCode).toBeNull();
        expect(rendersSomething(state)).toBe(true);
    });

    it('leaves free-text errors alone', () => {
        // Paths that carry their own prose keep the old presentation.
        const state = { ...empty(), error: 'Something specific happened.' };

        settle(state, 'no_character_data');

        expect(state.errorCode).toBeNull();
        expect(rendersSomething(state)).toBe(true);
    });
});
