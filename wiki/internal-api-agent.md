---
title: "Internal API: Agent"
order: 7
---

# Internal API: Agent

This is what the Agent and the Bridge *actually* say to each other today. It's pulled straight from the code: `routes/api.php` and the controllers on the Agent side, and `src/handlers/index.js` and `src/bridge.js` in the Bridge.

Looking for the wishlist of endpoints that don't exist yet? That's [External API: Future](/wiki/external-api-future).

## Contents

1. [The shape of it](#the-shape-of-it)
2. [Bridge → Agent over HTTP](#bridge--agent-over-http)
3. [Agent → Bridge over the WebSocket](#agent--bridge-over-the-websocket)
4. [The round trip, one request at a time](#the-round-trip-one-request-at-a-time)
5. [Things the Bridge sends unprompted](#things-the-bridge-sends-unprompted)
6. [Callback payload fields](#callback-payload-fields)
7. [Loose ends](#loose-ends)

---

## The shape of it

There's no REST API into Foundry. Everything is a message with a `type`, and it goes round in a loop:

```
Player's browser ──POST /api/attackroll──▶ Agent
Agent ──GameUpdate { type: ATTACK_ROLL }──▶ Reverb ──▶ Bridge (GM's Foundry)
Bridge runs the roll in Foundry
Bridge ──POST /api/foundry/callback { type: ROLL_RESULT }──▶ Agent
Agent ──FoundryResponse { type: ROLL_RESULT }──▶ Reverb ──▶ Player's browser
```

A few rules hold for all of it:

- **Everyone meets on one channel.** Both directions use the private channel `cpr-agent.{channel_id}`, which is the GM's session.
- **The GM's Bridge does the work.** Only the GM's Foundry has to be open for the Agent to work. Players' Bridges mostly just link their accounts and share their rolls.
- **`requestId` ties it together.** The Agent makes a UUID for each request, and the Bridge sends it back on the reply. That's how a player's browser knows which reply is theirs.
- **`agent_link` says who asked.** The Agent adds it to everything it broadcasts, so the Bridge knows which Foundry user a request belongs to.

---

## Bridge → Agent over HTTP

Every call from the Bridge sends the user's API key as `Authorization: Bearer <token>` (a Sanctum token), except the setup code lookup, which happens before the Bridge has a key.

| Method & path | Used for | Guarded by |
|---|---|---|
| `POST /api/foundry/callback` | **The big one.** Every reply and every update from Foundry. Body is JSON with a `type`. | Sanctum, 60 a minute, de-duplication |
| `POST /api/foundry/update` | Old name for the same thing. It just calls the callback handler. | Same as above |
| `POST /api/broadcasting/auth` | Echo's handshake to join the private `cpr-agent.{channel_id}` channel. | Sanctum |
| `POST /api/pairing/register` | The GM's Bridge registering a QR pairing code. See [QR Pairing](/wiki/getting-started#quick-connect-qr-pairing). | Sanctum, 30 a minute |
| `GET /api/setup-code/{code}` | Quick Setup swapping a short Setup Code for the Bridge's settings. | No auth, 10 a minute, single use, short-lived |

### What the callback does with it

1. **Validates** the payload (the full list is in [Callback payload fields](#callback-payload-fields)).
2. **Works out the user** from the Bearer token.
3. **Handles a few types specially:**
   - `LINK_PLAYER_CHARACTER` and `GM_LINK_PLAYER_CHARACTER` link a Foundry user to an Agent account and get a direct JSON answer (with the `agent_link`). They're **not** broadcast.
   - If there's an `isGM` flag, it updates the user's GM status. It'll promote you, but it won't demote you while you own an active session.
   - `ACTOR_DATA` and `SHEET_DELTA` get written to the Redis cache straight away, and a queued job saves them to the database. More in [The Delta System](/wiki/guide#the-delta-system).
4. **Broadcasts** everything else to the channel as a `FoundryResponse` event, and also caches it for 10 seconds under its `requestId`.

**De-duplication.** `SHEET_DELTA`, `ACTOR_DATA`, both link types, `ROLL_RESULT` and `DICE_ROLL_EVENT` get ignored if the same `requestId` has already come in. The Agent still answers `200` with `duplicate_ignored`, so Foundry doesn't sit there retrying.

---

## Agent → Bridge over the WebSocket

The Agent talks to Foundry by broadcasting a **`GameUpdate`** event on `cpr-agent.{channel_id}`. The Bridge listens for `.GameUpdate`, reads its `type`, and looks up a handler in `HANDLERS` (`src/handlers/index.js`). Anything it doesn't recognise gets logged as `Unknown DataType` and dropped.

On the way out, the Agent tacks on the logged-in user's `agent_link` and `channel_id` if they're not already there.

Going the other way, callbacks come back to players' browsers as a **`FoundryResponse`** event on the same channel.

---

## The round trip, one request at a time

Every row starts in the player's browser as a call to the Agent's API. Those routes use the normal Laravel session, and need the app to be configured and the profile set up.

### Rolls

Every roll comes back as a `ROLL_RESULT`, with the total, the raw roll, the label and whether it was a crit.

| Agent route | `type` sent to the Bridge | Reply from the Bridge |
|---|---|---|
| `POST /api/statrollrequest` | `STAT_ROLL` | `ROLL_RESULT` |
| `POST /api/skillrollrequest` | `SKILL_ROLL` | `ROLL_RESULT` |
| `POST /api/rolerollrequest` | `ROLE_ROLL` | `ROLL_RESULT` |
| `POST /api/attackroll` | `ATTACK_ROLL` | `ROLL_RESULT` |
| `POST /api/damageroll` | `DAMAGE_ROLL` | `ROLL_RESULT` |
| `POST /api/make-roll` | `DICE_ROLL` | `ROLL_RESULT` |
| `POST /api/deathsave` | `DEATH_SAVE` | `ROLL_RESULT`, plus a `SHEET_DELTA` for the death save counter |
| `POST /api/facedown` | `FACEDOWN_ROLL` | `ROLL_RESULT` |
| `POST /api/initiativeroll` | `INITIATIVE_ROLL` | `ROLL_RESULT` |
| `POST /api/programroll` | `PROGRAM_ROLL` | `ROLL_RESULT` |

Every roll also carries the `rollMode` (`publicroll`, `gmroll`, `blindroll` or `selfroll`), and the Agent uses it to decide who gets to see the result.

### Character data

| Agent route | `type` sent to the Bridge | Reply from the Bridge |
|---|---|---|
| `POST /api/getactor` | `GET_ACTOR` | `ACTOR_DATA` (the whole slimmed-down character) |
| `POST /api/getactors` | `GET_ACTORS` | `ACTORS_DATA` |
| `GET /settings` (on load) | `GET_ACTORS`, marked as a link request | `ACTORS_DATA` |
| `POST /api/getrollmodifiers` | `GET_ROLL_MODIFIERS` | `ROLL_MODIFIERS_DATA` |
| `POST /api/get-net-pool` | `GET_NET_POOL` | `NET_POOL_DATA` (Data Pools) |

### Changing the sheet

| Agent route | `type` sent to the Bridge | Reply from the Bridge |
|---|---|---|
| `POST /api/updatestat` | `UPDATE_STAT` | `SHEET_DELTA` |
| `POST /api/updatenotes` | `UPDATE_STAT` (notes are just another path) | `SHEET_DELTA` |
| `POST /api/update-eb` | `UPDATE_EB` (`add`, `subtract` or `set`) | `SHEET_DELTA` on `system.wealth` |
| `POST /api/item-equip` | `ITEM_EQUIP` | `SHEET_DELTA` |
| `POST /api/consume-item` | `CONSUME_ITEM` | `CONFIRMATION` |
| `POST /api/reloadweapon` | `RELOAD_WEAPON` | `SHEET_DELTA` |

### Cyberdeck and cyberware

| Agent route | `type` sent to the Bridge | Reply from the Bridge |
|---|---|---|
| `POST /api/installprogram` | `INSTALL_PROGRAM` | `SHEET_DELTA` |
| `POST /api/uninstallprogram` | `REMOVE_PROGRAM` | `SHEET_DELTA` |
| `POST /api/toggleprogramrez` | `TOGGLE_REZ` | `SHEET_DELTA` |
| `POST /api/prepare-cyberware-install` | `PREPARE_CYBERWARE_INSTALL` | `CYBERWARE_INSTALL_OPTIONS` (where it can go, or why it can't) |
| `POST /api/install-cyberware` | `INSTALL_CYBERWARE` | `ROLL_RESULT` for the Humanity loss, then `CYBERWARE_INSTALLED` |
| `POST /api/uninstall-cyberware` | `UNINSTALL_CYBERWARE` | `CYBERWARE_UNINSTALLED` |

### Tokens and the map

| Agent route | `type` sent to the Bridge | Reply from the Bridge |
|---|---|---|
| `POST /api/movetoken` | `MOVE_TOKEN` | `CONFIRMATION` (with a message if the token wasn't found) |
| `POST /api/targettokens` | `TARGET_TOKEN` | `CONFIRMATION` |
| `POST /api/listvisibletokens` | `LIST_VISIBLE_TOKENS` | `VISIBLE_TOKENS_DATA` |
| `POST /api/getvisibletokens` | `GET_DV_INFO` | `DV_INFO_DATA` (range and DV to the target) |

### Pairing

| Agent route | `type` sent to the Bridge | Reply from the Bridge |
|---|---|---|
| `POST /api/pairing/claim` | `PAIR_AGENT` | `PAIR_CONFIRMED` |

`GET /api/pairing/status` is the player's Agent checking on that `PAIR_CONFIRMED`.

### Routes that never reach Foundry

These answer straight from the Agent, no Bridge involved:

| Agent route | What it does |
|---|---|
| `POST /api/actorsheet` | The cached character (Redis, then the database) for an instant first load. |
| `GET /api/get-rolls` | Recent roll history. |
| `GET /api/getbaseurl` | The user's Foundry URL. |
| `GET /api/proxy-image` | Fetches an image from Foundry on the player's behalf, for when the browser can't reach Foundry directly or the schemes don't match. |

---

## Things the Bridge sends unprompted

Not everything is an answer. The Bridge also hooks into Foundry and POSTs these to `/api/foundry/callback` on its own:

| `type` | When |
|---|---|
| `SHEET_DELTA` | Something changes on a character or mook in Foundry: the actor itself, or an item being updated, created or deleted. Only the bit that changed gets sent. |
| `ROLL_RESULT` | A roll lands in Foundry chat, even one made in Foundry and not the Agent. |
| `REFRESH_SIGNAL` | A token moves or gets deleted. `target` is `VISIBLE_TOKENS_DATA`, which tells the Agent to fetch the token list again. |
| `LINK_PLAYER_CHARACTER` | A player's Bridge starts up, or their assigned character changes. The answer carries their `agent_link`, which the Bridge saves on the Foundry user. |
| `GM_LINK_PLAYER_CHARACTER` | The GM assigns a character in **Manage Characters**. |

Rolls also get passed to other players' Foundry clients over Foundry's own socket, so they see the little side popup. That part never touches the Agent.

---

## Callback payload fields

Everything the callback accepts. All optional apart from `type`, and each message only uses the ones it needs.

| Group | Fields |
|---|---|
| **Routing** | `type`, `action`, `requestId`, `channelId` / `channel_id`, `agentLink`, `foundryUserId`, `isGM`, `playerName`, `target` |
| **Actors** | `actorId`, `actor`, `actors`, `character`, `delta`, `tab`, `data` |
| **Rolls** | `result`, `total`, `formula`, `label`, `flavor`, `rawRoll`, `isCriticalSuccess`, `isCriticalFailure`, `rollMode` |
| **Tokens** | `tokenId`, `tokens`, `targets` |
| **Items and cyberware** | `itemId`, `itemName`, `isFoundational`, `canInstall`, `reason`, `humanityLoss` |
| **Everything else** | `success`, `message`, `pools`, `nonce` |

---

## Loose ends

Stuff I spotted while writing this up, which is worth sorting at some point:

- **`UPDATE_ACTOR`** has a handler in the Bridge, but nothing in the Agent sends it anymore.
- **`DICE_ROLL_EVENT`** is still listened for on the Agent side and de-duplicated, but the Bridge only ever sends `ROLL_RESULT` now.
- **`POST /api/foundry/update`** is only there for older Bridges. Everything current uses `/api/foundry/callback`.
- **Notes** go out as `UPDATE_STAT`. It works, because notes are just a path, but the name's a bit misleading.
