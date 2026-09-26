# Embed Mode: Agent inside Foundry

How the CPR Agent web app runs inside a Foundry VTT iframe, talking to the Agent
Bridge module over `window.postMessage` instead of Laravel + Reverb.

---

## 1. The two deployment modes

The same Vue app runs in two different environments:

| | Hosted mode | Embed mode |
|---|---|---|
| Where it runs | A standalone browser tab (phone/desktop) pointed at the Laravel app | An `<iframe>` rendered inside the Foundry client |
| Serves the page | Laravel (`layouts/cyberpunk.blade.php`) | Laravel (`views/embed.blade.php`), but only the HTML shell |
| Talks to Foundry via | Laravel API → Reverb WebSocket → Bridge module | Direct `postMessage` to the parent window (the Bridge) |
| Auth | App login + session + CSRF | Foundry's own client session (the player is already logged into Foundry) |
| Live updates | Reverb broadcasts on `cpr-agent.{channel_id}` | `postMessage` envelopes from the parent frame |

The hosted path is the original architecture. Embed mode is a newer path that
removes the server round-trip entirely. When a player opens their character in
Foundry, the Bridge drops the Agent UI straight into an iframe and feeds it
locally.

---

## 2. The core idea: the app only speaks two APIs

The embed design rests on one observation. Despite roughly 40 endpoints and a
pile of live-update logic, the Vue app only touches Foundry through two narrow
interfaces:

1. Outbound (requests): `window.axios.post('/api/...')`, meaning "do a thing"
   (roll dice, equip item, fetch modifiers).
2. Inbound (events): `window.Echo.private(ch).listen('.FoundryResponse', cb)`,
   meaning "here is a result or a live change" (ACTOR_DATA, SHEET_DELTA,
   ROLL_RESULT, and so on).

To relocate the app into Foundry we don't rewrite the app. We swap those two
seams for postMessage equivalents and leave every component, composable, and
listener untouched:

- `window.axios.defaults.adapter` becomes an adapter that turns `/api/*` calls
  into Bridge messages and `postMessage`s them to the parent.
- `window.Echo` becomes a MockEcho that receives Bridge messages over postMessage
  and fans them out to the same `.FoundryResponse` listeners the app already
  registered.

Everything downstream (delta application, roll privacy, net pool, modifiers)
behaves identically because it can't tell the difference. It is still awaiting an
axios response and listening on Echo.

```
        HOSTED                                   EMBEDDED
  Vue ──axios──▶ Laravel ──▶ Reverb        Vue ──axios(adapter)──▶ postMessage
  Vue ◀─Echo(Reverb)◀── Reverb ◀── Bridge  Vue ◀─Echo(MockEcho)◀── postMessage ◀── Bridge
```

---

## 3. Boot sequence

1. Laravel serves `views/embed.blade.php`. Before the app bundle loads it sets:
   ```js
   window.__CPR_EMBED = true;
   window.AgentConfig = { channel_id: null };   // no Reverb channel in embed
   ```
2. `resources/js/bootstrap.js` runs `isEmbed()`. If true it calls `initEmbed()`
   instead of constructing the real `laravel-echo` client.
3. `initEmbed()` (`resources/js/embed/index.js`):
   - reads the handshake `nonce` from the query string,
   - derives the parent (Foundry) origin from `document.referrer`,
   - seeds `cpr_base_url` from that origin (Foundry serves the character art),
   - builds the transport and installs it:
     ```js
     window.axios.defaults.adapter = adapter;   // egress
     window.Echo = echo;                        // ingress (MockEcho)
     ```
4. The Vue app mounts exactly as in hosted mode.

---

## 4. The wire protocol

One envelope shape travels in both directions:

```js
{ __cpr: true, nonce, kind: 'hello' | 'welcome' | 'message', payload }
```

- `__cpr: true` marks the message as ours. Everything else is ignored.
- `nonce` is a per-window shared token. Both sides drop mismatches.
- `kind`:
  - `hello`: iframe to Bridge, sent once the embed app has loaded.
  - `welcome`: Bridge to iframe, handshake acknowledgement.
  - `message`: either direction. `payload` is a Bridge message, the same
    `{ type, ... }` shape the Bridge's handlers consume and `sendResponse` emits.

### Handshake

```
iframe (Agent)                              parent (Bridge / Foundry)
  │  postMessage {kind:'hello'}  ───────────▶
  │                                          ready = true
  │  ◀───────────  postMessage {kind:'welcome'}
  │                                          bridge.handleIncomingDataLocal({type:'GET_ACTOR'})
  │  ◀───────────  postMessage {kind:'message', payload:{type:'ACTOR_DATA', actor}}
  │  MockEcho.deliver(payload) → sheet paints
```

The Bridge pushes `GET_ACTOR` immediately on `welcome`, so the sheet paints
without the app having to ask. MockEcho also replays the last ACTOR_DATA to any
listener that subscribes late, so component mount ordering never races the
initial push.

---

## 5. Egress: the axios adapter (`transport.js`)

The adapter intercepts every `/api/*` call the app makes and maps it to a Bridge
message using `ENDPOINT_MAP`:

```js
'/statrollrequest': { type: 'STAT_ROLL', transform: (b) => ({ ...b, label: b.label.toLowerCase() }) }
```

Each entry is one of:

- default: fire-and-forget. `postMessage` the `{type,...}` and immediately return
  a synthetic `{ requestId, status:'pending' }` (mirrors the Laravel controllers'
  201/202 ack). The real result arrives later via MockEcho.
- `{ await, shape }`: the caller reads the response body, so the adapter parks a
  promise, waits for the named response type (with a 4s timeout), and shapes it
  into the axios body. Used by `/getactors`.
- `{ empty: true }`: server-only state that doesn't exist in embed (DB cache,
  dice history, image base). Returns `{}`; callers degrade gracefully.
- `{ transform }`: see below.

### Controller parity (why `transform` exists)

The Laravel controllers are not pass-throughs. They normalise payloads before
dispatching, so the adapter must reproduce that or the Bridge receives the wrong
shape. Examples the transport mirrors 1:1:

| Endpoint | Laravel controller does | `transform` reproduces |
|---|---|---|
| `/statrollrequest` | `strtolower(label)` | `label.toLowerCase()` |
| `/skillrollrequest` | `ucfirst(label)` | `ucfirst(label)` |
| `/updatestat` | maps `HP` to `derivedStats.hp.value` etc. | `mapUpdateStat()` |
| `/updatenotes` | dispatches `UPDATE_STAT` with `information.notes` | rewrites body |
| `/getrollmodifiers` | pulls `selectedWeapon._id` into `weaponId` | `{ weaponId: b.selectedWeapon?._id }` |

The adapter also injects `requestId` (a fresh UUID) and `actorId` (from
`localStorage.cpr_actor_uuid`) into every payload, matching what the controllers
add from the authenticated user.

---

## 6. Ingress: MockEcho (`mock-echo.js`)

MockEcho implements the slice of the `laravel-echo` API the app uses:
`.private(ch).listen(event, cb)`, `.channel()`, `.leave()`. It ignores the
channel name (there is no real channel) and keeps a flat set of callbacks.

When a `{kind:'message'}` envelope arrives, `transport.js`:

1. resolves any parked `await` promise whose response type matches, then
2. calls `echo.deliver(payload)`, which fans the payload out to every registered
   `.FoundryResponse` listener as `{ payload }`.

Listeners filter by `payload.type` internally, so broadcasting to all of them is
safe. This is why ACTOR_DATA, SHEET_DELTA, ROLL_RESULT, NET_POOL_DATA and
ROLL_MODIFIERS_DATA all work through the same pipe.

### The synthetic channel gotcha

Both listener registration points guard on a channel name and short-circuit if
it is null. In embed `channel_id` is `null`, so they must fall back to a synthetic
name or they register nothing and hear nothing:

```js
const effectiveChannel = channel
  || (channel_id ? `cpr-agent.${channel_id}` : (window.__CPR_EMBED ? 'cpr-agent.embed' : null));
```

This applies to both `useWebSocketSubscription.subscribe()` (ACTOR_DATA and
SHEET_DELTA) and `utils/listener.js useEchoListener()` (rolls, net pool, roll
modifiers). Any new composable that registers via `useEchoListener` inherits the
fallback, but a hand-rolled `window.Echo.private(...)` must remember it.

### Response routing / privacy filter

`useEchoListener` still runs its normal privacy/ownership filter in embed. It
passes because the Bridge echoes `actorId` back on every response, so
`isMyActor(payload.actorId)` is true for this player's sheet. The axios
interceptor has also registered the outgoing `requestId`, so `isMyRequest` holds
too. Roll-privacy modes (`publicroll`, `gmroll`, `blindroll`, `selfroll`) behave
the same as in hosted mode.

---

## 6a. Cross-agent roll fan-out and privacy

A roll should appear on every player's agent: a big popup for the roller, a small
side icon for everyone else. It must also honour privacy, so a GM, blind, or self
roll does not leak to players who shouldn't see it. Hosted mode handles this
through the shared channel. The roller's Bridge reports the roll once to Laravel,
Reverb fans it out on `cpr-agent.{channel_id}`, and every subscribed agent
receives it.

Embed has no shared channel. Each iframe only talks to its own parent Bridge, so
we lean on the one bus every client already shares: Foundry's own chat
replication. When any player rolls, Foundry creates a `ChatMessage` and replicates
it to all eligible clients. Each Bridge hooks `createChatMessage` and tees the
roll into its own iframe:

```js
Hooks.on('createChatMessage', (message) => {
    if (!message.rolls?.length) return;
    if (message.author.id === game.user.id) {
        if (this._isCapturingRoll) return;   // my Agent-initiated roll: rollAndCapture emits it
        this.broadcastRoll(message);          // my Foundry-native roll: Laravel + local tee
    } else if (this.embedTransport) {
        this.broadcastRoll(message, { localOnly: true }); // a peer's roll: tee to my iframe only
    }
});
```

Why each clause:

- `author === me` and capturing: skip. My Agent-initiated roll already returns its
  result via `rollAndCapture`'s explicit `ROLL_RESULT` (the big popup), and teeing
  again would double it.
- `author === me` and not capturing: a roll I made directly in Foundry. Broadcast
  it normally (Laravel for hosted, plus a tee to my own iframe).
- `author !== me` and embedded: a peer's roll. In hosted the shared channel handled
  this; in embed my Bridge must tee it into my iframe or I never see it. `localOnly`
  means tee locally and don't re-report to Laravel. The author's client owns the
  report, and every other client re-reporting would send duplicates.

Privacy is stamped onto the message, then Foundry's replication does the rest. The
mode the player picks in the agent (`publicroll`, `gmroll`, `blindroll`,
`selfroll`) travels with the roll request to the handler, which passes it to
`rollAndCapture`. Setting `core.rollMode` alone is not reliable, because the CPR
system does not consistently apply it. Instead the Bridge stamps the visibility
explicitly: a `preCreateChatMessage` hook, active only while a capture is in
flight, calls `message.updateSource(whisper/blind)` before the message is created.
Foundry then delivers the roll natively whispered or blind (that is "privacy
reflected in game"), and because a non-recipient never receives the message, their
`createChatMessage` hook never fires and their iframe is never teed. The same
native visibility gates cross-agent fan-out. The client-side `useEchoListener`
rollMode filter is a second backstop.

The capture flag (`_isCapturingRoll`) must survive from the roll's `renderTemplate`
until its `createChatMessage`, so it is consumed there (`_endRollCapture`), not
reset at render time. Resetting early would fire before `preCreateChatMessage` and
lose the stamp. A safety timer clears it if, unusually, no message is created.

One correctness detail: `sendResponse(data, { localOnly })` must not stamp a peer's
roll with the local user's `agent-link`, otherwise the local iframe would mistake
the peer's roll for its own and show the big popup. In `localOnly` mode the
`game.user.getFlag('agent-link')` fallback is suppressed, so attribution stays with
the actual roller (or null).

---

## 7. The Bridge side (separate repo)

Lives in `~/Dev/Cyberpunk-Red-Agent-Bridge` (GitLab), a Foundry module.

- `src/embed/embed-transport.js` is the mirror of this app's `transport.js`. It
  binds to the rendered `<iframe>`, verifies `event.origin === agentOrigin()`,
  `event.source === frame.contentWindow`, and the nonce, then routes inbound
  `message` payloads to `bridge.handleIncomingDataLocal(payload)`, the same
  dispatch path a Reverb message would take. `send()` posts responses back into
  the frame.
- `src/handlers/index.js` holds `HANDLERS`, which maps message `type` to handler.
  It is identical for hosted and embed; only the transport differs.
- Handlers call `bridge.sendResponse({ type: 'X_DATA', ... })`. In embed this
  becomes a `postMessage` back to the iframe; in hosted it becomes a Laravel
  callback then a Reverb broadcast.

Because dispatch is shared, a message type only has to be wired once and both
transports carry it, provided this app's `ENDPOINT_MAP` maps the `/api/*` route to
the matching `type` and reproduces any controller-side payload transform.

---

## 8. Security model

- The iframe runs in the player's own Foundry client, so Foundry's session already
  authenticates them. There is no separate app login and no CSRF. The embed route
  runs without `StartSession`, and all writes go over postMessage rather than
  CSRF-guarded POSTs.
- Both sides validate every message: the `__cpr` marker, a matching `nonce`, and
  `event.source === parent/frame`. The Bridge additionally pins `event.origin`.
- The Agent posts to `document.referrer`'s origin when known, else `*`. Inbound is
  still gated by source and nonce.

---

## 9. Known gaps in embed mode

- Images: character and gear art is served by Foundry. `cpr_base_url` is seeded
  from the parent origin, but a Foundry `ROUTE_PREFIX` deployment would need the
  Bridge to send an authoritative base in the `welcome` payload (TODO).
- Server-only state returns empty: `/actorsheet` (DB cache), `/get-rolls` (dice
  history), `/getbaseurl`.
- The Config tab is hidden in embed. There is nothing to configure (no host or
  session to set) and navigating to `/settings` would break the frame.

---

## 10. File map

Agent (this repo)
```
resources/views/embed.blade.php              HTML shell; sets __CPR_EMBED + AgentConfig
resources/js/bootstrap.js                    picks embed vs Reverb at boot
resources/js/embed/index.js                  isEmbed() / initEmbed(); installs adapter + Echo
resources/js/embed/transport.js              axios adapter + ENDPOINT_MAP + controller-parity transforms
resources/js/embed/mock-echo.js              MockEcho: postMessage to .FoundryResponse listeners
resources/js/utils/listener.js               useEchoListener (synthetic-channel fallback)
resources/js/composables/useWebSocketSubscription.js   ACTOR_DATA / SHEET_DELTA (synthetic-channel fallback)
```

Bridge (`~/Dev/Cyberpunk-Red-Agent-Bridge`)
```
src/embed/embed-transport.js                 iframe binding, handshake, origin/nonce checks
src/handlers/index.js                        HANDLERS map (shared hosted + embed)
src/handlers/*.js                            per-type handlers to bridge.sendResponse({type:'X_DATA'})
src/bridge.js                                createChatMessage / preCreateChatMessage hooks, roll capture
src/ui/agent-app.js                          AgentApp window + fullscreen toggle
```
