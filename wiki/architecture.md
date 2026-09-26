---
title: Architecture
order: 6
---

### The Foundry Module: Rough Release Notes

These started life as my bullet-point release notes, so they're rough. They're still the quickest way to see what the thing actually does.

- **What it's for:** Shows your character data from Foundry over a secure websocket connection, and lets you poke your Foundry game back through callbacks.
- **Real-time sheet sync:** Applies small "delta" updates to the Agent's cached character sheet, so nothing needs a full refresh. Deltas can add, update or delete nested values and item entries.
- **Webhook callback:** Foundry posts results and sheet changes to the Agent's `/api/foundry/callback`, which then get broadcast to the frontend over a private channel (`cpr-agent.{{channel_id}}` → `FoundryResponse`).
- **Actor bootstrap:** On first load, the Agent tries to work out which Actor you control, and keeps a clean copy of that actor's data server-side to save on round-trips.
- **Rolls:** Endpoints to kick off stat/skill/role/attack/damage/initiative rolls, pass them to Foundry, and bring the results (modifiers, crit flags, totals) back to the Agent.
- **Token controls:** Endpoints for visibility, targeting, movement and weapon reload.
- **Image routing:** Proxies images from Foundry (Ngrok URLs included) so they still show up when you're not on the Foundry host.
- **Config:** Stores a `foundry_url` and API token per user, so it's always talking to the right Foundry instance and world.
- **Fault tolerance:** Waits for responses against request IDs and the cache, but only for so long. If Foundry's slow, the Agent sends back a proper timeout message instead of hanging.
- **Security:** Every Foundry callback and broadcast auth route is behind Sanctum, and the frontend listens on a private channel that belongs to the GM and their group.

#### Overview and Architecture

There are three moving parts:

- **Agent Backend (Laravel):** The HTTP API the frontend talks to. Every request to Foundry, and every callback from it, goes through here.
- **Foundry VTT + the CPR Agent Bridge:** Runs the Agent's calls inside Foundry, and sends back callbacks with the data or results that were asked for.
- **Agent Frontend (Vue):** Draws the character sheet, sends actions (rolls, updates), and applies real-time deltas as broadcast events come in.

And four ways they talk to each other:
    1) Agent → Foundry: The Agent calls CPRAB endpoints using the configured API key, foundry_url and channel_id.
    2) Foundry → Agent (webhook): Foundry posts results/updates to `/api/foundry/callback` with a `type`, optional `requestId`, and optional `delta`.
    3) Agent → Frontend (broadcast): The Agent emits a `FoundryResponse` event on private channel `cpr-agent.{{channel_id}}`; the Vue app consumes it and applies deltas immediately.
    4) Agent Frontend → Agent Backend (poll/refresh): For first-load or when a targeted refresh is requested, the frontend calls `/actorsheet` (and, if needed, `/getactor`) to hydrate/rehydrate the local mirror.

If you want to go digging, start with these:
- Webhook handler: `app/Http/Controllers/Api/FoundryController.php`
- Broadcast event: `app/Events/FoundryResponse.php`
- Frontend Listener (Axios): `resources/js/composables/listener.js`
- Routes: `routes/api.php`, `routes/web.php`


#### Authentication and Configuration

- **Auth:** Every API route that matters uses Laravel Sanctum. The frontend needs either a logged-in web session or a valid bearer token.
- **Configuration:**
    - `foundry_url`: Base URL to the Foundry instance (stored per user in `configs`).
    - API Token: Generated in the Agent (`/settings/api-token`) and shared with the CPRAB Module per user.
    - Channel ID: Every user gets one, but a group only needs one between them. Share it round the table.
    - Agent ID: Unique to each user. It's how the CPRAB knows which user to talk back to.


#### Webhook: Foundry → Agent

- Method/Path: `POST /api/foundry/callback`
- Auth: Sanctum (Bearer token or authenticated session)
- Purpose: The one callback Foundry uses for everything: roll results, actor snapshots, token lists and partial sheet updates (deltas).
- Body fields (not the full list, the code has the rest):
    - `requestId` (string, optional): Correlates async request/response.
    - `type` (string, required): Message kind. Examples: `SHEET_DELTA`, `CONFIRMATION`, `ACTORS_DATA`, `VISIBLE_TOKENS`, roll result types.
    - `action` (string, optional): Contextual action name (e.g., which roll).
    - `actorId` (string, optional): Target actor identifier.
    - `success` (boolean, optional): Operation success flag.
    - `result` (any, optional): Arbitrary result payload, e.g., roll modifiers or operation outputs.
    - `actors` (array, optional): Actor listing for `GET_ACTORS`.
    - `tokens` (array, optional): Visible tokens for token queries.
    - `data` (object, optional): Structured data payload (e.g., roll shapes).
    - `total` (number, optional): Roll total.
    - `isCriticalSuccess` / `isCriticalFailure` (boolean, optional): Critical roll flags.
    - `label` (string, optional): Result label.
    - `rawRoll` (object, optional): Full dice breakdown.
    - `delta` (object, optional): Map of dot-paths to values representing partial sheet updates. See Delta section below.
    - `tab` (string, optional): UI hint for which sheet tab changed.

- What happens when one lands:
    1) Validate shape and persist in cache if a `requestId` is present (short-lived handoff for polling consumers).
    2) If `type === SHEET_DELTA` and a user is authenticated, apply the delta to the server-side cached mirror (`user->character_data`) using dot-path mutation, including item add/update/delete semantics.
    3) Broadcast the validated payload via `FoundryResponse` on private channel `cpr-agent` to the frontend.
    4) Return `{ "status": "acknowledged" }`.

- Success response: `200 OK`, JSON `{ status: "acknowledged" }`
- Errors: `422 Unprocessable Entity` on validation errors; `401 Unauthorized` if unauthenticated.


#### Core Frontend Data Endpoints (Agent Frontend → Agent Backend)

Everything below needs a logged-in session and a configured profile. The middleware guards are in `routes/web.php`.

- GET `/getbaseurl`
    - Returns the configured `foundry_url` (string). Used by the frontend to compose image URLs.

- POST `/actorsheet`
    - Purpose: Fetch or persist the server-side mirror of the current actor’s sheet. Also used for periodic or targeted refresh.
    - Request: `{ actorUuid: string, actorData?: object }`
        - If `actorData` provided, it will be saved as the authoritative mirror.
        - If only `actorUuid` provided, the current mirror is returned.
    - Response: `{ data: object, requestId?: string }`. `data` is the sheet mirror under `data.*` keys.

- POST `/getactor`
    - Purpose: Pull the live actor document from Foundry (via REST), typically for first-time hydration or force-refresh.
    - Request: `{ actorId: string }`
    - Response: `{ actor: object }`

- POST `/getactors`
    - Purpose: Discover available actors the user controls/owns in the Foundry world.
    - Response: `{ actors: array, uuid?: string }`


#### Roll and Mechanics Endpoints

These all work the same way: turn something the player did into a Foundry REST API call. Whatever comes out of it (roll totals, crit flags, modifiers, item effects) comes back through the webhook and gets broadcast. Some of them also send an instant 200 to say "got it" while the real result turns up later.

- POST `/statrollrequest`: request a stat roll
- POST `/skillrollrequest`: request a skill roll
- POST `/rolerollrequest`: request a role ability roll
- POST `/attackroll`: weapon attack roll
- POST `/damageroll`: damage roll after a hit
- POST `/initiativeroll`: initiative roll
- POST `/getrollmodifiers`: fetch and/or prompt roll modifiers in Foundry
- POST `/make-roll`: generic expression roll; GET `/get-rolls` to list recent rolls

What goes in the request depends on the action (stat/skill IDs, context like item IDs or targets, or a free-form expression). Results get posted back to `/api/foundry/callback` and broadcast.


#### Token and Inventory/Program Endpoints

- POST `/getvisibletokens`: query currently visible tokens (may be async with cache handoff via `requestId`).
- POST `/listvisibletokens`: list candidate tokens (broader set than “visible only”).
- POST `/targettokens`: set token targets.
- POST `/movetoken`: move a token to a new position.
- POST `/reloadweapon`: reload current weapon.
- POST `/item-equip`: equip/unequip item.
- POST `/installprogram`, `/uninstallprogram`, `/toggleprogramrez`, `/programroll`: Netrunning program management and rolls.

These mostly take IDs (token, item, program) plus some context. Whether it worked, and what changed in the game, comes back through the webhook and deltas.


#### Delta Mechanism (Selective Sheet Updates)

A delta is a partial update: a map of dot-paths to values. It gets applied to both the server and client copies, so nobody has to pull the whole actor document again.

- Shape:
```json
{
  "type": "SHEET_DELTA",
  "actorId": "abc123",
  "delta": {
    "system.derivedStats.hp.value": 13,
    "items.+7H-PISTOL": { "_id": "7H-PISTOL", "name": "Heavy Pistol", "system": { "ammo": 8 } },
    "items.7H-PISTOL.system.ammo": 7,
    "items.7H-PISTOL": null
  },
  "tab": "stats"
}
```

- On the server (the important bits):
    - Paths are applied to `user->character_data` using a dot-walk.
    - Special item rules in `arraySetDot`:
        - `items.+ID`: append or replace entire item with `_id = ID`; creates `items` array if missing.
        - `items.ID`: add/replace entire item object, or delete when value is `null`.
        - `items.ID.some.deep.path`: deep-merge/update within an existing item; ignored if the item does not exist.

- On the client (the important bits of `applyDelta`):
    - Incoming Foundry-style paths are mapped under the local mirror root:
        - If a path starts with `system` or `items`, it is remapped to `data.system` or `data.items` respectively.
        - If a path already starts with `data.`, it is used as-is.
    - Items follow the same rules as the server:
        - `data.items.+ID` appends/replaces the entire item object.
        - `data.items.ID` adds/replaces the full item or deletes on `null`.
        - `data.items.ID.field...` deep-updates in place.
    - After applying, the composable triggers a reactive refresh and re-renders Lucide icons.

- Targeted refresh (`refreshSheet`):
    - When the frontend only needs a bit of the sheet, it asks the backend for the latest mirror via `/actorsheet`, just for the changed sub-paths. The result gets turned into a synthetic delta and applied locally.


#### Typical Transaction Flows

1) Stat/Skill/Role Roll
- Frontend posts to `/statrollrequest` (or `/skillrollrequest`, `/rolerollrequest`) with actor ID and context.
- Backend calls Foundry REST to execute the roll.
- Foundry posts to `/api/foundry/callback` with `{ type, requestId, total, rawRoll, isCriticalSuccess/Failure, label, delta? }`.
- Backend broadcasts `FoundryResponse` to `private:cpr-agent`.
- Frontend listener updates any roll UI and applies `delta` to the character sheet without a full reload.

2) Attack → Damage
- Frontend posts `/attackroll` with weapon context/targets.
- On hit, frontend (or Foundry macro) triggers `/damageroll`.
- Foundry returns one or more callbacks with results and any item/state `delta` (e.g., ammo decrement), which are broadcast and applied.

3) Sheet Field Update (e.g., notes, a stat value)
- Frontend posts `/updatestat` or `/updatenotes`.
- Backend proxies the change to Foundry; Foundry confirms via callback with `type: SHEET_DELTA` and the precise changed paths.
- Backend applies/persists the delta and broadcasts the event; frontend applies it instantly.

4) Token Visibility/Targeting/Movement
- Frontend posts `/getvisibletokens`, `/targettokens`, or `/movetoken`.
- If results are async, the backend correlates via `requestId` and resolves via cache when the callback lands.
- Any resulting `delta` (e.g., conditions applied to the actor) is persisted and broadcast.

5) Initial Load / Rehydrate
- Frontend calls `/actorsheet` with `actorUuid`.
    - If no mirror exists, backend fetches from Foundry via `/getactor`, saves it, and returns.
- Subsequent renders use deltas and occasional targeted refresh for efficiency.


#### Errors and Timeouts

- Foundry never called back:
    - The backend sends a standard timeout JSON with `message: "Foundry VTT did not respond in time."` (see the token/actors controllers), and still deals with the callback properly if it turns up late.
- Validation errors on webhook: `422 Unprocessable Entity` with details.
- Auth failures: `401 Unauthorized`.


#### Image Proxying

- GET `/proxy-image?url=<encoded>`
- Proxies images from Foundry (Ngrok included) so they still show up in the Agent when they're cross-origin or privately hosted.


#### Notes on Data Shapes

- Frontend mirror stores actor data under `data.*` to mirror Foundry’s `system.*` and `items` trees.
- For targeted refreshes, the frontend stores Foundry paths like `system.*` or `items.*` locally as `data.system.*` or `data.items.*`, but keeps the original Foundry-style keys in the deltas it sends out, so they still make sense.


#### Security Considerations

- Anything that matters needs Sanctum authentication.
- Broadcast authorization uses `Broadcast::routes` with both `web` and `auth:sanctum,web` to allow session- or token-based subscriptions to the private channel.
- Request IDs are just labels for matching responses to requests. Don't ever use them for authorisation.


#### Versioning and Compatibility

- The Agent expects the ThreeHats API module to be installed and switched on in Foundry, pointed at the Agent's relay/callback URL, with the matching API key.
- Delta paths follow Foundry's `system.*` and `items` schema. If you're on a custom system or the schema changes, keep the dot-paths consistent or things will get weird.


#### Minimal Examples

- Example: Roll callback payload
```json
{
  "requestId": "r-123",
  "type": "ROLL_RESULT",
  "actorId": "abc123",
  "label": "Handgun (AGI)",
  "total": 17,
  "isCriticalSuccess": false,
  "isCriticalFailure": false,
  "rawRoll": { "dice": [10, 4], "mod": 3 },
  "delta": { "items.7H-PISTOL.system.ammo": 7 },
  "tab": "gear"
}
```

- Example: Sheet delta only
```json
{
  "type": "SHEET_DELTA",
  "actorId": "abc123",
  "delta": { "system.derivedStats.hp.value": 13 }
}
```
