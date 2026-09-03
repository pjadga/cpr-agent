import { MockEcho } from './mock-echo.js';

// EMBED TRANSPORT (Agent side)
//
// The mirror of the Bridge's EmbedTransport. When the Agent runs inside
// a Foundry-hosted iframe, this:
//   - performs the postMessage handshake with the parent (Bridge),
//   - installs a MockEcho fed by incoming bridge messages
//   - installs an axios adapter that maps the app's own /api/* calls to
//     bridge message types and postMessages them to the parent (requests-out).


// Payload transforms mirroring the Laravel controllers.
//
// The hosted controllers are NOT pass-throughs: they normalise the label and
// (for UPDATE_STAT) map a display label to the actor's system path before
// dispatching. The embed adapter must reproduce these or the Bridge receives
// the wrong label e.g. STAT_ROLL with 'INT' instead of 'int' (CPR's stat
// lookup fails) or UPDATE_STAT with 'HP' instead of 'derivedStats.hp.value'
// (actor.update writes a dead path). See app/Http/Controllers/{Rolls,Values}/.
const ucfirst = (s) => { s = String(s ?? ''); return s.charAt(0).toUpperCase() + s.slice(1); };
const lcfirst = (s) => { s = String(s ?? ''); return s.charAt(0).toLowerCase() + s.slice(1); };

// Mirror of App\Http\Controllers\Values\UpdateStat::updateStat.
function mapUpdateStat(b) {
    const raw = String(b.label ?? '').toLowerCase();
    let label;
    if (raw === 'hit points' || raw === 'hp') label = 'derivedStats.hp.value';
    else if (raw === 'humanity') label = 'derivedStats.humanity.value';
    else label = `stats.${raw}.value`; // luck, emp, and other stats
    return { ...b, label, operation: b.operation || 'set' };
}

// Maps the app's own /api/* endpoints (with the /api prefix stripped) to
// Bridge message types.
//   - default        : fire-and-forget; the real result returns via MockEcho
//                       (ACTOR_DATA / SHEET_DELTA / ROLL_RESULT / ...).
//   - { await, shape }: the caller reads the response body, so wait for the
//                       given response type and shape it into the body.
//   - { empty: true } : return {} (endpoints backed by server-only state that
//                       doesn't exist in embed mode; callers degrade gracefully).
//   - { transform }   : rewrite the request body to match what the hosted
//                       controller would dispatch (label normalisation etc.).
export const ENDPOINT_MAP = {
    // Getters
    '/getactor':          { type: 'GET_ACTOR' },
    '/getactors':         { type: 'GET_ACTORS', await: 'ACTORS_DATA', shape: (p) => ({ actors: p.actors || [] }) },
    '/actorsheet':        { empty: true }, // no server DB cache in embed; app falls through to a fresh fetch
    '/get-net-pool':      { type: 'GET_NET_POOL' },        // fire-and-forget; NET_POOL_DATA returns via echo
    // Mirror App\Http\Controllers\Data\GetRollModifiers: the controller pulls
    // selectedWeapon._id and dispatches it as `weaponId`, the Bridge handler
    // reads data.weaponId. Passing selectedWeapon through untransformed leaves
    // weaponId undefined, so the modifier panel loses all weapon skill/stat/acc.
    '/getrollmodifiers':  { type: 'GET_ROLL_MODIFIERS', transform: (b) => ({ weaponId: b.selectedWeapon?._id ?? null }) }, // ROLL_MODIFIERS_DATA returns via echo
    // Mirror App\Http\Controllers\Token\GetVisibleTokens: it reduces the body to
    // weaponId (selectedWeapon._id) and targetTokenId (selectedTarget.id) before
    // dispatching GET_DV_INFO. Without this the Bridge sees weaponId undefined
    // ("Weapon ID undefined not found on actor"). DV_INFO_DATA returns via echo.
    '/getvisibletokens':  { type: 'GET_DV_INFO', transform: (b) => ({ weaponId: b.selectedWeapon?._id ?? null, targetTokenId: b.selectedTarget?.id ?? null }) },
    '/listvisibletokens': { type: 'LIST_VISIBLE_TOKENS' }, // empty body; VISIBLE_TOKENS_DATA returns via echo (verified)
    '/getbaseurl':        { empty: true },                 // image-proxy base; images are a known embed gap
    '/get-rolls':         { empty: true },                 // dice history is server-stored; empty in embed

    // Rolls (result returns via echo: ROLL_RESULT / DICE_ROLL_EVENT)
    '/statrollrequest':   { type: 'STAT_ROLL',  transform: (b) => ({ ...b, label: String(b.label ?? '').toLowerCase() }) }, // StatRollRequest: strtolower
    '/skillrollrequest':  { type: 'SKILL_ROLL', transform: (b) => ({ ...b, label: ucfirst(b.label) }) },                     // SkillRollRequest: ucfirst
    '/rolerollrequest':   { type: 'ROLE_ROLL',  transform: (b) => ({ ...b, label: ucfirst(b.label), role: ucfirst(b.role) }) }, // RoleRollRequest: ucfirst label + role
    // Attack/Damage: hosted controllers reduce the body to weaponId
    // (selectedWeapon._id) + targetId (selectedTarget.id). Passing the raw
    // controlState through leaves weaponId undefined, so CPR finds no weapon, no
    // rollcard renders and the Bridge's capture interceptor times out.
    '/attackroll':        { type: 'ATTACK_ROLL', transform: (b) => ({ weaponId: b.selectedWeapon?._id ?? null, targetId: b.selectedTarget?.id ?? null, fireMode: b.fireMode, actorId: b.actorId, tokenId: b.tokenId, rollMode: b.rollMode }) },
    '/damageroll':        { type: 'DAMAGE_ROLL', transform: (b) => ({ weaponId: b.selectedWeapon?._id ?? null, targetId: b.selectedTarget?.id ?? null, fireMode: b.fireMode, actorId: b.actorId, tokenId: b.tokenId, rollMode: b.rollMode }) },
    '/deathsave':         { type: 'DEATH_SAVE' },
    '/facedown':          { type: 'FACEDOWN_ROLL' },
    '/initiativeroll':    { type: 'INITIATIVE_ROLL' },
    '/make-roll':         { type: 'DICE_ROLL' },
    // Hosted ProgramRoll computes rollType from executionType (execute vs
    // interface) and lcfirst's the name; the Bridge reads data.rollType. Without
    // this rollType is undefined and no rollcard renders (interceptor times out).
    '/programroll':       { type: 'PROGRAM_ROLL', transform: (b) => ({
                              cyberdeckId: b.cyberdeckId,
                              programId: b.programId,
                              programName: lcfirst(b.programName),
                              executionType: b.executionType ?? null,
                              rollType: b.executionType ? 'cyberdeckProgram' : 'interfaceAbility',
                              rollMode: b.rollMode,
                          }) },

    // Writes (SHEET_DELTA returns via echo)
    '/item-equip':        { type: 'ITEM_EQUIP' },
    '/consume-item':      { type: 'CONSUME_ITEM' },
    '/reloadweapon':      { type: 'RELOAD_WEAPON' },
    // Hosted controllers rename deckId -> cyberdeckId (Install/RemoveProgram) and
    // isRezzed -> targetState (ToggleRez) before dispatch; the Bridge reads those
    // renamed fields. Reproduce or the deck/state is undefined and nothing happens.
    '/installprogram':    { type: 'INSTALL_PROGRAM', transform: (b) => ({ programId: b.programId, cyberdeckId: b.deckId }) },
    '/uninstallprogram':  { type: 'REMOVE_PROGRAM',  transform: (b) => ({ programId: b.programId, cyberdeckId: b.deckId }) },
    '/toggleprogramrez':  { type: 'TOGGLE_REZ',      transform: (b) => ({ programId: b.programId, targetState: b.isRezzed }) },
    '/updatestat':        { type: 'UPDATE_STAT', transform: mapUpdateStat },
    '/update-eb':         { type: 'UPDATE_EB' },
    // Hosted UpdateNotes dispatches UPDATE_STAT (label 'information.notes')
    '/updatenotes':       { type: 'UPDATE_STAT', transform: (b) => ({ label: 'information.notes', operation: 'set', value: b.notes }) },
    '/movetoken':         { type: 'MOVE_TOKEN' },
    '/targettokens':      { type: 'TARGET_TOKEN' },

    // Cyberware install/uninstall. PREPARE returns CYBERWARE_INSTALL_OPTIONS via
    // echo (targets + humanity options for the modal); INSTALL/UNINSTALL are
    // fire-and-forget and the result reflects through SHEET_DELTA.
    '/prepare-cyberware-install': { type: 'PREPARE_CYBERWARE_INSTALL' },
    '/install-cyberware':         { type: 'INSTALL_CYBERWARE' },
    '/uninstall-cyberware':       { type: 'UNINSTALL_CYBERWARE' },
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

        const rawBody = parseBody(config.data);
        // Reproduce the hosted controller's payload transform (label
        // normalisation / path mapping) so the Bridge gets what it expects.
        const body = entry.transform ? entry.transform(rawBody) : rawBody;
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
