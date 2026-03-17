### Foundry Module — Bullet Point Release Notes (Rough Write‑Up)

- Purpose: Displays character data from Foundry using a secure web-socket connection. Allows the user to interact with Foundry instance through callbacks.
- Real‑time sheet sync: Applies selective “delta” updates to the Agent’s cached character sheet without requiring full refreshes. Deltas can add/update/delete nested values and item entries.
- Webhook callback: Foundry posts results and sheet changes to the Agent’s `/api/foundry/callback`, which are then broadcast to the frontend over a private channel (`cpr-agent.{{channel_id}}` → `FoundryResponse`).
- Actor bootstrap: On first load, the Agent will try to resolve the user’s controlled Actor and persist a clean mirror of actor data server‑side to reduce round‑trips.
- Rolls orchestration: Provides endpoints to initiate stat/skill/role/attack/damage/initiative rolls, forward them to Foundry, and deliver results (including modifiers, crit flags, and totals) back to the Agent.
- Token controls: Exposes endpoints for visibility, targeting, movement, and weapon reload. 
- Image routing: Supports proxying images from Foundry (including Ngrok URLs) to the Agent for reliable display outside the Foundry host.
- Config integration: Stores per‑user `foundry_url` and an API token to address/authorize the correct Foundry instance and world.
- Fault tolerance: Time‑boxed response collection using request IDs and cache; if Foundry is slow to respond, the Agent returns a structured timeout message.
- Security: All Foundry callback and broadcast auth routes are protected by Sanctum; frontend listens on a private broadcast channel which is specific to the GM and their group.

#### Overview and Architecture

- Components:
    - Agent Backend (Laravel): Provides the HTTP API consumed by the frontend and mediates all requests and callbacks with Foundry.
    - Foundry VTT + CPR Agent Bridge: Executes calls from the Agent within Foundry, sends callbacks containing requested data or results to the Agent.
    - Agent Frontend (Vue): Renders character sheet, sends actions (rolls, updates), and applies realtime deltas via broadcast events.
- Communication patterns:
    1) Agent → Foundry: The Agent calls CPRAB endpoints using the configured API key, foundry_url and channel_id.
    2) Foundry → Agent (webhook): Foundry posts results/updates to `/api/foundry/callback` with a `type`, optional `requestId`, and optional `delta`.
    3) Agent → Frontend (broadcast): The Agent emits a `FoundryResponse` event on private channel `cpr-agent.{{channel_id}}`; the Vue app consumes it and applies deltas immediately.
    4) Agent Frontend → Agent Backend (poll/refresh): For first-load or when a targeted refresh is requested, the frontend calls `/actorsheet` (and, if needed, `/getactor`) to hydrate/rehydrate the local mirror.

Key files for reference:
- Webhook handler: `app/Http/Controllers/Api/FoundryController.php`
- Broadcast event: `app/Events/FoundryResponse.php`
- Frontend Listener (Axios): `resources/js/composables/listener.js`
- Routes: `routes/api.php`, `routes/web.php`


#### Authentication and Configuration

- Auth: All relevant API routes use Laravel Sanctum. The frontend must hold an authenticated web session or a valid bearer token.
- Configuration:
    - `foundry_url`: Base URL to the Foundry instance (stored per user in `configs`).
    - API Token: Generated in the Agent (`/settings/api-token`) and shared with the CPRAB Module per user.
    - Channel ID: Generated for each user, however only one is needed for a group. Channel IDs can be shared between the group.
    - Agent ID: Unique identifier to each user - this is a unique identifier that allows the CPRAB to correctly communicate back to the correct user.


#### Webhook: Foundry → Agent

- Method/Path: `POST /api/foundry/callback`
- Auth: Sanctum (Bearer token or authenticated session)
- Purpose: Unified callback for Foundry to push roll results, actor data snapshots, token lists, and partial sheet updates (deltas).
- Body fields (subset; see code for full list):
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

- Behavior on receipt:
    1) Validate shape and persist in cache if a `requestId` is present (short-lived handoff for polling consumers).
    2) If `type === SHEET_DELTA` and a user is authenticated, apply the delta to the server-side cached mirror (`user->character_data`) using dot-path mutation, including item add/update/delete semantics.
    3) Broadcast the validated payload via `FoundryResponse` on private channel `cpr-agent` to the frontend.
    4) Return `{ "status": "acknowledged" }`.

- Success response: `200 OK`, JSON `{ status: "acknowledged" }`
- Errors: `422 Unprocessable Entity` on validation errors; `401 Unauthorized` if unauthenticated.


#### Core Frontend Data Endpoints (Agent Frontend → Agent Backend)

All routes below require an authenticated session and a configured profile. See `routes/web.php` for middleware guards.

- GET `/getbaseurl`
    - Returns the configured `foundry_url` (string). Used by the frontend to compose image URLs.

- POST `/actorsheet`
    - Purpose: Fetch or persist the server-side mirror of the current actor’s sheet. Also used for periodic or targeted refresh.
    - Request: `{ actorUuid: string, actorData?: object }`
        - If `actorData` provided, it will be saved as the authoritative mirror.
        - If only `actorUuid` provided, the current mirror is returned.
    - Response: `{ data: object, requestId?: string }` — `data` reflects the sheet mirror under `data.*` keys.

- POST `/getactor`
    - Purpose: Pull the live actor document from Foundry (via REST), typically for first-time hydration or force-refresh.
    - Request: `{ actorId: string }`
    - Response: `{ actor: object }`

- POST `/getactors`
    - Purpose: Discover available actors the user controls/owns in the Foundry world.
    - Response: `{ actors: array, uuid?: string }`


#### Roll and Mechanics Endpoints

Common behavior: These routes translate a user action into a Foundry REST API call. The resulting data (roll totals, crit flags, modifiers, item effects) is returned via the webhook and broadcast. Some endpoints may also return an immediate 200 with an acknowledgment while the final result arrives asynchronously.

- POST `/statrollrequest` — request a stat roll
- POST `/skillrollrequest` — request a skill roll
- POST `/rolerollrequest` — request a role ability roll
- POST `/attackroll` — weapon attack roll
- POST `/damageroll` — damage roll after a hit
- POST `/initiativeroll` — initiative roll
- POST `/getrollmodifiers` — fetch and/or prompt roll modifiers in Foundry
- POST `/make-roll` — generic expression roll; GET `/get-rolls` to list recent rolls

Request bodies vary by action (stat/skill identifiers, context like item IDs/targets, or free-form expressions). Results will be posted back to `/api/foundry/callback` and broadcast.


#### Token and Inventory/Program Endpoints

- POST `/getvisibletokens` — query currently visible tokens (may be async with cache handoff via `requestId`).
- POST `/listvisibletokens` — list candidate tokens (broader set than “visible only”).
- POST `/targettokens` — set token targets.
- POST `/movetoken` — move a token to a new position.
- POST `/reloadweapon` — reload current weapon.
- POST `/item-equip` — equip/unequip item.
- POST `/installprogram`, `/uninstallprogram`, `/toggleprogramrez`, `/programroll` — Netrunning program management and rolls.

These typically accept identifiers (token IDs, item IDs, program IDs) and context fields. Success/failure and resulting game state are propagated via the webhook and deltas.


#### Delta Mechanism (Selective Sheet Updates)

Deltas model partial updates as a map of dot-paths to values. They are applied on both server and client mirrors to avoid pulling an entire actor document.

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

- Server-side application (highlights):
    - Paths are applied to `user->character_data` using a dot-walk.
    - Special item rules in `arraySetDot`:
        - `items.+ID`: append or replace entire item with `_id = ID`; creates `items` array if missing.
        - `items.ID`: add/replace entire item object, or delete when value is `null`.
        - `items.ID.some.deep.path`: deep-merge/update within an existing item; ignored if the item does not exist.

- Client-side application (highlights in `applyDelta`):
    - Incoming Foundry-style paths are mapped under the local mirror root:
        - If a path starts with `system` or `items`, it is remapped to `data.system` or `data.items` respectively.
        - If a path already starts with `data.`, it is used as-is.
    - Special item rules mirror the server’s logic:
        - `data.items.+ID` appends/replaces the entire item object.
        - `data.items.ID` adds/replaces the full item or deletes on `null`.
        - `data.items.ID.field...` deep-updates in place.
    - After applying, the composable triggers a reactive refresh and re-renders Lucide icons.

- Targeted refresh (`refreshSheet`):
    - For a focused pull, the frontend can ask the backend for the latest mirror via `/actorsheet` and request only the changed sub-paths; the result is converted into a synthetic delta and applied locally.


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

- Callback not received in time:
    - Backend returns a consistent timeout JSON with `message: "Foundry VTT did not respond in time."` (see token/actors controllers), while still honoring any late callback when it arrives.
- Validation errors on webhook: `422 Unprocessable Entity` with details.
- Auth failures: `401 Unauthorized`.


#### Image Proxying

- GET `/proxy-image?url=<encoded>`
- Proxies images from Foundry (including Ngrok) to ensure they render for the Agent frontend even when cross-origin or privately hosted.


#### Notes on Data Shapes

- Frontend mirror stores actor data under `data.*` to mirror Foundry’s `system.*` and `items` trees.
- When constructing targeted refreshes, the frontend converts requested Foundry paths like `system.*` or `items.*` to `data.system.*` or `data.items.*` for local storage, but keeps the original external path keys in emitted deltas for clarity.


#### Security Considerations

- All critical endpoints require authentication via Sanctum.
- Broadcast authorization uses `Broadcast::routes` with both `web` and `auth:sanctum,web` to allow session- or token-based subscriptions to the private channel.
- Request IDs should be treated as opaque correlators; do not trust them for authorization.


#### Versioning and Compatibility

- The Agent expects the ThreeHats API module installed/enabled in Foundry and configured with the Agent’s relay/callback URL and matching API key.
- Path conventions for deltas follow Foundry’s `system.*` and `items` schema; custom systems or schema changes must maintain dot-path consistency for best results.


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
