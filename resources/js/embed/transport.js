import { MockEcho } from './mock-echo.js';

// ==================================================================
// EMBED TRANSPORT (Agent side)
//
// The mirror of the Bridge's EmbedTransport. When the Agent runs inside
// a Foundry-hosted iframe, this:
//   - performs the postMessage handshake with the parent (Bridge),
//   - installs a MockEcho fed by incoming bridge messages (data-in),
//   - installs an axios adapter that maps the app's own /api/* calls to
//     bridge message types and postMessages them to the parent (requests-out).
//
// The Laravel roll/write controllers are thin, uniform pass-throughs
// (validate -> inject actorId + requestId -> dispatch {type, ...body}),
// so the adapter can reproduce their broadcast shape generically.
// ==================================================================

// Maps the app's own /api/* endpoints (with the /api prefix stripped) to
// Bridge message types.
//   - default        : fire-and-forget; the real result returns via MockEcho
//                       (ACTOR_DATA / SHEET_DELTA / ROLL_RESULT / ...).
//   - { await, shape }: the caller reads the response body, so wait for the
//                       given response type and shape it into the body.
//   - { empty: true } : return {} (endpoints backed by server-only state that
//                       doesn't exist in embed mode; callers degrade gracefully).
export const ENDPOINT_MAP = {
    // --- Reads ---
    '/getactor':          { type: 'GET_ACTOR' },
    '/getactors':         { type: 'GET_ACTORS', await: 'ACTORS_DATA', shape: (p) => ({ actors: p.actors || [] }) },
    '/actorsheet':        { empty: true }, // no server DB cache in embed; app falls through to a fresh fetch
    '/get-net-pool':      { type: 'GET_NET_POOL' },        // TODO(verify): confirm response type / await shape
    '/getrollmodifiers':  { type: 'GET_ROLL_MODIFIERS' },  // TODO(verify)
    '/getvisibletokens':  { type: 'GET_DV_INFO' },         // TODO(verify)
    '/listvisibletokens': { type: 'LIST_VISIBLE_TOKENS' }, // TODO(verify)
    '/getbaseurl':        { empty: true },                 // image-proxy base; images are a known embed gap
    '/get-rolls':         { empty: true },                 // dice history is server-stored; empty in embed

    // --- Rolls (result returns via echo: ROLL_RESULT / DICE_ROLL_EVENT) ---
    '/statrollrequest':   { type: 'STAT_ROLL' },
    '/skillrollrequest':  { type: 'SKILL_ROLL' },
    '/rolerollrequest':   { type: 'ROLE_ROLL' },
    '/attackroll':        { type: 'ATTACK_ROLL' },
    '/damageroll':        { type: 'DAMAGE_ROLL' },
    '/deathsave':         { type: 'DEATH_SAVE' },
    '/facedown':          { type: 'FACEDOWN_ROLL' },
    '/initiativeroll':    { type: 'INITIATIVE_ROLL' },
    '/make-roll':         { type: 'DICE_ROLL' },
    '/programroll':       { type: 'PROGRAM_ROLL' },

    // --- Writes (SHEET_DELTA returns via echo) ---
    '/item-equip':        { type: 'ITEM_EQUIP' },
    '/consume-item':      { type: 'CONSUME_ITEM' },
    '/reloadweapon':      { type: 'RELOAD_WEAPON' },
    '/installprogram':    { type: 'INSTALL_PROGRAM' },
    '/uninstallprogram':  { type: 'REMOVE_PROGRAM' },
    '/toggleprogramrez':  { type: 'TOGGLE_REZ' },
    '/updatestat':        { type: 'UPDATE_STAT' },
    '/update-eb':         { type: 'UPDATE_EB' },
    '/updatenotes':       { type: 'UPDATE_NOTES' },  // TODO(verify): no bridge handler yet
    '/movetoken':         { type: 'MOVE_TOKEN' },
    '/targettokens':      { type: 'TARGET_TOKEN' },
};

const AWAIT_TIMEOUT_MS = 4000;

function newRequestId() {
    try { return crypto.randomUUID(); }
    catch (e) { return 'embed-' + Date.now() + '-' + Math.random().toString(36).slice(2); }
}

function parseBody(data) {
    if (!data) return {};
    if (typeof data === 'string') { try { return JSON.parse(data); } catch (e) { return {}; } }
    if (typeof data === 'object') return data;
    return {};
}

function normalizePath(url = '') {
    return url.replace(/^https?:\/\/[^/]+/, '').split('?')[0].replace(/^\/api/, '');
}

/**
 * Build the embed transport. Returns the MockEcho (to assign to window.Echo)
 * and the axios adapter (to assign to window.axios.defaults.adapter).
 * @param {{ nonce: string, parentOrigin: string }} opts
 */
export function createEmbedTransport({ nonce, parentOrigin }) {
    const echo = new MockEcho();
    const awaiters = []; // { responseType, resolve }

    const postToParent = (envelope) => {
        window.parent.postMessage(envelope, parentOrigin || '*');
    };
    const sendMessage = (payload) => postToParent({ __cpr: true, nonce, kind: 'message', payload });

    // --- Ingress from the Bridge ---
    window.addEventListener('message', (event) => {
        if (event.source !== window.parent) return;
        const msg = event.data;
        if (!msg || msg.__cpr !== true) return;
        if (nonce && msg.nonce && msg.nonce !== nonce) return;

        if (msg.kind === 'welcome') return; // handshake ack
        if (msg.kind !== 'message' || !msg.payload) return;

        const payload = msg.payload;

        // Resolve any awaiter waiting on this response type.
        for (let i = awaiters.length - 1; i >= 0; i--) {
            if (payload.type === awaiters[i].responseType) {
                const a = awaiters.splice(i, 1)[0];
                a.resolve(payload);
            }
        }

        // Feed the mock Echo (drives charData / delta / roll listeners).
        echo.deliver(payload);
    });

    // Announce ourselves; the Bridge replies 'welcome' and pushes GET_ACTOR.
    postToParent({ __cpr: true, nonce, kind: 'hello' });

    // --- Egress: axios adapter ---
    const adapter = async (config) => {
        const path = normalizePath(config.url || '');
        const entry = ENDPOINT_MAP[path];
        const ok = (data) => ({ data, status: 200, statusText: 'OK', headers: {}, config, request: {} });

        if (!entry) {
            // Unknown endpoint in embed mode — degrade to an empty success.
            return ok({});
        }
        if (entry.empty) {
            return ok({});
        }

        const body = parseBody(config.data);
        const requestId = newRequestId();
        const actorId = (localStorage.getItem('cpr_actor_uuid') || '').replace(/^Actor\./, '') || body.actorId || null;

        const payload = { ...body, type: entry.type, requestId };
        if (actorId && payload.actorId == null) payload.actorId = actorId;

        if (entry.await) {
            const responsePromise = new Promise((resolve) => {
                awaiters.push({ responseType: entry.await, resolve });
                setTimeout(() => resolve(null), AWAIT_TIMEOUT_MS);
            });
            sendMessage(payload);
            const resp = await responsePromise;
            return ok(entry.shape ? entry.shape(resp || {}) : (resp || {}));
        }

        sendMessage(payload);
        // Async ack, mirroring the Laravel controllers' immediate response.
        return ok({ requestId, status: 'pending', success: true, data: { requestId } });
    };

    return { echo, adapter };
}
