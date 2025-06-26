# Foundry VTT REST API Client - API Documentation

This document provides detailed documentation for all methods available in the FoundryClient class.

## Table of Contents

- [Authentication and Setup](#authentication-and-setup)
  - [Constructor](#constructor)
  - [setApiKey](#setapikey)
  - [setBaseUrl](#setbaseurl)
- [API Information](#api-information)
  - [getApiDocs](#getapidocs)
  - [getApiStatus](#getapistatus)
- [Client Management](#client-management)
  - [getClients](#getclients)
- [World Structure and Content](#world-structure-and-content)
  - [getStructure](#getstructure)
  - [getContentsByPath](#getcontentsbypath)
- [Search and Selection](#search-and-selection)
  - [searchEntities](#searchentities)
  - [selectEntities](#selectentities)
  - [getSelectedEntities](#getselectedentities)
- [Entity Management](#entity-management)
  - [getEntity](#getentity)
  - [createEntity](#createentity)
  - [updateEntity](#updateentity)
  - [deleteEntity](#deleteentity)
- [Macros](#macros)
  - [getMacros](#getmacros)
  - [executeMacro](#executemacro)
- [Character Sheets](#character-sheets)
  - [getSheet](#getsheet)
- [Dice Rolling](#dice-rolling)
  - [getRolls](#getrolls)
  - [getLastRoll](#getlastroll)
  - [makeRoll](#makeroll)
- [Encounter Management](#encounter-management)
  - [getEncounters](#getencounters)
  - [startEncounter](#startencounter)
  - [nextTurnInEncounter](#nextturnincounter)
  - [nextRoundInEncounter](#nextroundincounter)
  - [previousTurnInEncounter](#previousturnincounter)
  - [previousRoundInEncounter](#previousroundincounter)
  - [endEncounter](#endencounter)
  - [addToEncounter](#addtoencounter)
  - [removeFromEncounter](#removefromencounter)
- [Entity Manipulation](#entity-manipulation)
  - [markEntityAsDefeated](#markentityasdefeated)
  - [decreaseAttribute](#decreaseattribute)
  - [increaseAttribute](#increaseattribute)
  - [giveItem](#giveitem)

## Authentication and Setup

### Constructor

Creates a new instance of the FoundryClient.

```php
public function __construct(string $baseUrl = "", string $apiKey = "")
```

**Parameters:**
- `$baseUrl` (string): The base URL for the Foundry REST API (e.g., "https://foundryvtt-rest-api-relay.fly.dev").
- `$apiKey` (string): Your API key for the Foundry REST API.

**Example:**
```php
$client = new FoundryClient(
    'https://foundryvtt-rest-api-relay.fly.dev',
    'your-api-key'
);
```

### setApiKey

Sets the API key for the client.

```php
public function setApiKey(string $apiKey): void
```

**Parameters:**
- `$apiKey` (string): Your API key for the Foundry REST API.

**Example:**
```php
$client->setApiKey('your-new-api-key');
```

### setBaseUrl

Sets the base URL for the client.

```php
public function setBaseUrl(string $baseUrl): void
```

**Parameters:**
- `$baseUrl` (string): The base URL for the Foundry REST API.

**Example:**
```php
$client->setBaseUrl('https://your-new-foundry-api-url.com');
```

## API Information

### getApiDocs

Returns the API documentation.

```php
public function getApiDocs(): ?array
```

**Returns:**
- `array|null`: The API documentation or null on failure.

**Example:**
```php
$apiDocs = $client->getApiDocs();
```

**Response Example:**
```php
[
    "openapi" => "3.0.0",
    "info" => [
        "title" => "Foundry VTT REST API",
        "version" => "1.0.0",
        // ...
    ],
    "paths" => [
        // API paths and operations
    ]
]
```

### getApiStatus

Returns the API status.

```php
public function getApiStatus(): ?array
```

**Returns:**
- `array|null`: The API status or null on failure.

**Example:**
```php
$apiStatus = $client->getApiStatus();
```

**Response Example:**
```php
[
    "status" => "ok",
    "version" => "1.0.0",
    "uptime" => "2d 3h 45m",
    "clients" => 5
]
```

## Client Management

### getClients

Returns connected client Foundry Worlds.

```php
public function getClients(bool $prettify = true): ?array
```

**Parameters:**
- `$prettify` (bool, optional): Whether to prettify the response (make IDs/UUIDs clickable). Default: `true`.

**Returns:**
- `array|null`: List of connected clients or null on failure.

**Example:**
```php
$clients = $client->getClients();
```

**Response Example:**
```php
[
    [
        "id" => "abc123",
        "name" => "My Foundry World",
        "system" => "dnd5e",
        "version" => "10.291",
        "users" => [
            ["id" => "user1", "name" => "Game Master"],
            ["id" => "user2", "name" => "Player 1"]
        ]
    ],
    // More worlds...
]
```

## World Structure and Content

### getStructure

Returns the folders and compendiums in the world.

```php
public function getStructure(string $clientId, bool $prettify = true): ?array
```

**Parameters:**
- `$clientId` (string): Auth token to connect to specific Foundry world.
- `$prettify` (bool, optional): Whether to prettify the response. Default: `true`.

**Returns:**
- `array|null`: The folder structure and compendiums or null on failure.

**Example:**
```php
$structure = $client->getStructure('your-client-id');
```

**Response Example:**
```php
[
    "folders" => [
        [
            "id" => "folder1",
            "name" => "Characters",
            "type" => "Actor",
            "contents" => [
                ["id" => "actor1", "name" => "Character 1", "type" => "character"],
                // More actors...
            ]
        ],
        // More folders...
    ],
    "compendiums" => [
        [
            "id" => "compendium1",
            "name" => "Items",
            "type" => "Item",
            // ...
        ],
        // More compendiums...
    ]
]
```

### getContentsByPath

Returns the contents of a folder or compendium.

```php
public function getContentsByPath(string $path, string $clientId, bool $prettify = false): ?array
```

**Parameters:**
- `$path` (string): Path to the folder or compendium.
- `$clientId` (string): Auth token to connect to specific Foundry world.
- `$prettify` (bool, optional): Whether to prettify the response. Default: `false`.

**Returns:**
- `array|null`: The contents of the folder or compendium or null on failure.

**Example:**
```php
$contents = $client->getContentsByPath('Actors.Characters', 'your-client-id');
```

**Response Example:**
```php
[
    [
        "id" => "actor1",
        "name" => "Character 1",
        "type" => "character",
        "img" => "path/to/image.png",
        // More properties...
    ],
    // More entities...
]
```

## Search and Selection

### searchEntities

Searches Foundry VTT entities using QuickInsert.

```php
public function searchEntities(string $clientId, string $query, ?string $filter = null, bool $prettify = false): ?array
```

**Parameters:**
- `$clientId` (string): Auth token to connect to specific Foundry world.
- `$query` (string): Search string.
- `$filter` (string|null, optional): Filters to apply. Default: `null`.
  - Can be a single string for filtering by type ("actor", "item", etc.)
  - Or chained together (name:bob,documentType:actor)
  - Available filters: documentType, folder, id, name, package, packageName, subType, uuid, icon, journalLink, tagline, formattedMatch, resultType
- `$prettify` (bool, optional): Whether to prettify the response. Default: `false`.

**Returns:**
- `array|null`: Search results or null on failure.

**Example:**
```php
// Simple search
$results = $client->searchEntities('your-client-id', 'goblin');

// Search with filter
$results = $client->searchEntities('your-client-id', 'sword', 'documentType:item');
```

**Response Example:**
```php
[
    [
        "id" => "item1",
        "name" => "Longsword",
        "type" => "weapon",
        "documentType" => "Item",
        "uuid" => "Item.abc123",
        // More properties...
    ],
    // More search results...
]
```

### selectEntities

Selects entities in Foundry.

```php
public function selectEntities(string $clientId, array $payload): ?array
```

**Parameters:**
- `$clientId` (string): Auth token to connect to specific Foundry world.
- `$payload` (array): The payload containing:
  - `uuids` (array): Array of entity UUIDs to select.
  - `name` (string, optional): Name for the selection.
  - `data` (array, optional): Data to apply to the selection.
  - `overwrite` (bool, optional): Whether to overwrite existing data.

**Example:**
```php
$result = $client->selectEntities('your-client-id', [
    'uuids' => ['Scene.ckW0qDbFoWblqQiP.Token.cklVpiD7jUgjuAfs'],
    'name' => 'ape',
    'data' => ['actor.system.attributes.hp.value' => 10],
    'overwrite' => true
]);
```

**Response Example:**
```php
[
    "success" => true,
    "message" => "Selected 1 entities",
    "entities" => [
        ["uuid" => "Scene.ckW0qDbFoWblqQiP.Token.cklVpiD7jUgjuAfs", "name" => "Goblin"]
    ]
]
```

### getSelectedEntities

Returns the currently selected entities.

```php
public function getSelectedEntities(string $clientId, bool $prettify = false): ?array
```

**Parameters:**
- `$clientId` (string): Auth token to connect to specific Foundry world.
- `$prettify` (bool, optional): Whether to prettify the response. Default: `false`.

**Returns:**
- `array|null`: The selected entities or null on failure.

**Example:**
```php
$selectedEntities = $client->getSelectedEntities('your-client-id');
```

**Response Example:**
```php
[
    [
        "uuid" => "Scene.ckW0qDbFoWblqQiP.Token.cklVpiD7jUgjuAfs",
        "name" => "Goblin",
        "type" => "npc",
        // More properties...
    ],
    // More selected entities...
]
```

## Entity Management

### getEntity

Returns JSON data for an entity.

```php
public function getEntity(string $clientId, ?string $uuid = null, ?string $selected = null, ?string $actor = null, bool $prettify = false): ?array
```

**Parameters:**
- `$clientId` (string): Auth token to connect to specific Foundry world.
- `$uuid` (string|null, optional): Entity UUID. Default: `null`.
- `$selected` (string|null, optional): Use selected entity (pass 'true' or 'false'). Default: `null`.
- `$actor` (string|null, optional): Use selected entity's actor (pass 'true' or 'false'). Default: `null`.
- `$prettify` (bool, optional): Whether to prettify the response. Default: `false`.

**Returns:**
- `array|null`: Entity data or null on failure.

**Example:**
```php
// Get entity by UUID
$entity = $client->getEntity('your-client-id', 'Actor.abc123');

// Get selected entity
$entity = $client->getEntity('your-client-id', null, 'true');

// Get selected entity's actor
$entity = $client->getEntity('your-client-id', null, 'true', 'true');
```

**Response Example:**
```php
[
    "id" => "abc123",
    "name" => "Goblin",
    "type" => "npc",
    "img" => "path/to/image.png",
    "system" => [
        "attributes" => [
            "hp" => [
                "value" => 7,
                "max" => 7
            ],
            // More attributes...
        ],
        // More system data...
    ],
    // More entity data...
]
```

### createEntity

Creates a new entity in Foundry with the given JSON.

```php
public function createEntity(string $clientId, array $payload): ?array
```

**Parameters:**
- `$clientId` (string): Auth token to connect to specific Foundry world.
- `$payload` (array): Payload containing:
  - `type` (string): The entity type (e.g., 'Actor', 'Item').
  - `data` (array): The entity data.

**Returns:**
- `array|null`: The created entity data or null on failure.

**Example:**
```php
$newEntity = $client->createEntity('your-client-id', [
    'type' => 'Actor',
    'data' => [
        'name' => 'New Goblin',
        'type' => 'npc',
        'img' => 'icons/svg/mystery-man.svg',
        'system' => [
            'attributes' => [
                'hp' => [
                    'value' => 7,
                    'max' => 7
                ]
            ]
        ]
    ]
]);
```

**Response Example:**
```php
[
    "id" => "def456",
    "name" => "New Goblin",
    "type" => "npc",
    "img" => "icons/svg/mystery-man.svg",
    "system" => [
        "attributes" => [
            "hp" => [
                "value" => 7,
                "max" => 7
            ]
        ]
    ],
    "uuid" => "Actor.def456"
]
```

### updateEntity

Updates an entity with the given JSON props.

```php
public function updateEntity(string $clientId, array $payload, ?string $uuid = null, ?string $selected = null, ?string $actor = null): ?array
```

**Parameters:**
- `$clientId` (string): Auth token to connect to specific Foundry world.
- `$payload` (array): Data to update.
- `$uuid` (string|null, optional): UUID of the entity to update. Default: `null`.
- `$selected` (string|null, optional): If 'true', updates all selected entities. Default: `null`.
- `$actor` (string|null, optional): If 'true' and selected is 'true', updates the currently selected actors. Default: `null`.

**Returns:**
- `array|null`: The updated entity data or null on failure.

**Example:**
```php
// Update entity by UUID
$updatedEntity = $client->updateEntity('your-client-id', [
    'name' => 'Updated Goblin',
    'system.attributes.hp.value' => 5
], 'Actor.def456');

// Update selected entity
$updatedEntity = $client->updateEntity('your-client-id', [
    'name' => 'Updated Selected Entity'
], null, 'true');
```

**Response Example:**
```php
[
    "id" => "def456",
    "name" => "Updated Goblin",
    "type" => "npc",
    "system" => [
        "attributes" => [
            "hp" => [
                "value" => 5,
                "max" => 7
            ]
        ]
    ],
    // More entity data...
]
```

### deleteEntity

Deletes an entity from Foundry.

```php
public function deleteEntity(string $clientId, ?string $uuid = null, ?string $selected = null): ?array
```

**Parameters:**
- `$clientId` (string): Auth token to connect to specific Foundry world.
- `$uuid` (string|null, optional): UUID of the entity to delete. Default: `null`.
- `$selected` (string|null, optional): If 'true', deletes all selected entities. Default: `null`.

**Returns:**
- `array|null`: Response message or null on failure.

**Example:**
```php
// Delete entity by UUID
$result = $client->deleteEntity('your-client-id', 'Actor.def456');

// Delete selected entities
$result = $client->deleteEntity('your-client-id', null, 'true');
```

**Response Example:**
```php
[
    "success" => true,
    "message" => "Entity deleted successfully"
]
```

## Macros

### getMacros

Returns all available macros.

```php
public function getMacros(string $clientId, bool $prettify = false): ?array
```

**Parameters:**
- `$clientId` (string): Auth token to connect to specific Foundry world.
- `$prettify` (bool, optional): Whether to prettify the response. Default: `false`.

**Returns:**
- `array|null`: List of macros or null on failure.

**Example:**
```php
$macros = $client->getMacros('your-client-id');
```

**Response Example:**
```php
[
    [
        "id" => "macro1",
        "name" => "Heal Party",
        "type" => "script",
        "command" => "// Macro script content",
        "author" => "user1",
        "uuid" => "Macro.abc123"
    ],
    // More macros...
]
```

### executeMacro

Executes a macro by UUID.

```php
public function executeMacro(string $uuid, string $clientId, ?array $args = null): ?array
```

**Parameters:**
- `$uuid` (string): UUID of the macro to execute.
- `$clientId` (string): Auth token to connect to specific Foundry world.
- `$args` (array|null, optional): Arguments to pass to the macro. Default: `null`.

**Returns:**
- `array|null`: Result of the macro execution or null on failure.

**Example:**
```php
// Execute macro without arguments
$result = $client->executeMacro('Macro.abc123', 'your-client-id');

// Execute macro with arguments
$result = $client->executeMacro('Macro.abc123', 'your-client-id', [
    'targetId' => 'Actor.def456',
    'amount' => 10
]);
```

**Response Example:**
```php
[
    "success" => true,
    "result" => "Macro executed successfully",
    "returnValue" => [
        // Any data returned by the macro
    ]
]
```

## Character Sheets

### getSheet

Returns raw HTML (or a string in a JSON response) for an entity.

```php
public function getSheet(
    string $clientId,
    ?string $uuid = null,
    ?string $selected = null,
    ?string $actor = null,
    ?string $format = 'json',
    ?float $scale = null,
    ?int $tab = null,
    ?bool $darkMode = null,
    bool $prettify = false
): ?array
```

**Parameters:**
- `$clientId` (string): Auth token to connect to specific Foundry world.
- `$uuid` (string|null, optional): UUID of the entity to get the sheet for. Default: `null`.
- `$selected` (string|null, optional): If 'true', returns the sheet for all selected entity. Default: `null`.
- `$actor` (string|null, optional): If 'true' and selected is 'true', returns the sheet for the currently selected actor. Default: `null`.
- `$format` (string|null, optional): Response format, 'html' or 'json'. Default: `'json'`.
- `$scale` (float|null, optional): Scale factor for the sheet (if format is html). Default: `null`.
- `$tab` (int|null, optional): Index of the tab to activate (if format is html). Default: `null`.
- `$darkMode` (bool|null, optional): Whether to use dark mode (if format is html). Default: `null`.
- `$prettify` (bool, optional): Whether to prettify the response. Default: `false`.

**Returns:**
- `array|null`: HTML content or JSON data or null on failure.

**Example:**
```php
// Get sheet as JSON
$sheet = $client->getSheet('your-client-id', 'Actor.def456');

// Get sheet as HTML
$sheet = $client->getSheet('your-client-id', 'Actor.def456', null, null, 'html', 1.0, 0, true);

// Get sheet for selected entity
$sheet = $client->getSheet('your-client-id', null, 'true');
```

**Response Example (JSON format):**
```php
[
    "html" => "<div class=\"sheet-content\">...</div>",
    "css" => "/* Sheet CSS */",
    "width" => 800,
    "height" => 600
]
```

**Response Example (HTML format):**
```php
[
    "html_content" => "<!DOCTYPE html><html>...</html>"
]
```

## Dice Rolling

### getRolls

Returns up to the last 20 rolls.

```php
public function getRolls(string $clientId, ?int $limit = 20, bool $prettify = false): ?array
```

**Parameters:**
- `$clientId` (string): Auth token to connect to specific Foundry world.
- `$limit` (int|null, optional): Max number of rolls to return. Max 20. Default: `20`.
- `$prettify` (bool, optional): Whether to prettify the response. Default: `false`.

**Returns:**
- `array|null`: List of rolls or null on failure.

**Example:**
```php
// Get last 20 rolls
$rolls = $client->getRolls('your-client-id');

// Get last 5 rolls
$rolls = $client->getRolls('your-client-id', 5);
```

**Response Example:**
```php
[
    [
        "id" => "roll1",
        "formula" => "1d20+5",
        "result" => 18,
        "total" => 23,
        "user" => "user1",
        "timestamp" => 1623456789,
        // More roll data...
    ],
    // More rolls...
]
```

### getLastRoll

Returns the last roll made.

```php
public function getLastRoll(string $clientId, bool $prettify = false): ?array
```

**Parameters:**
- `$clientId` (string): Auth token to connect to specific Foundry world.
- `$prettify` (bool, optional): Whether to prettify the response. Default: `false`.

**Returns:**
- `array|null`: The last roll or null on failure.

**Example:**
```php
$lastRoll = $client->getLastRoll('your-client-id');
```

**Response Example:**
```php
[
    "id" => "roll1",
    "formula" => "1d20+5",
    "result" => 18,
    "total" => 23,
    "user" => "user1",
    "timestamp" => 1623456789,
    // More roll data...
]
```

### makeRoll

Makes a new roll in Foundry.

```php
public function makeRoll(string $clientId, array $payload): ?array
```

**Parameters:**
- `$clientId` (string): Auth token to connect to specific Foundry world.
- `$payload` (array): Payload containing roll details:
  - `formula` (string): The dice formula (e.g., '2d6+3').
  - `itemUuid` (string, optional): UUID of an item to roll.
  - `flavor` (string, optional): Flavor text for the roll.
  - `createChatMessage` (bool, optional): Whether to create a chat message.
  - `speaker` (string, optional): Token/actor UUID to use as the speaker.
  - `target` (string, optional): Token/actor UUID to use as the target.
  - `whisper` (array, optional): Array of user IDs to whisper the roll to.

**Returns:**
- `array|null`: Result of the roll or null on failure.

**Example:**
```php
// Simple roll
$roll = $client->makeRoll('your-client-id', [
    'formula' => '2d6+3'
]);

// Roll with additional options
$roll = $client->makeRoll('your-client-id', [
    'formula' => '1d20+5',
    'flavor' => 'Attack Roll',
    'createChatMessage' => true,
    'speaker' => 'Actor.def456'
]);

// Roll an item
$roll = $client->makeRoll('your-client-id', [
    'itemUuid' => 'Item.ghi789',
    'createChatMessage' => true
]);
```

**Response Example:**
```php
[
    "id" => "roll2",
    "formula" => "2d6+3",
    "result" => [2, 5],
    "total" => 10,
    "success" => true,
    // More roll data...
]
```

## Encounter Management

### getEncounters

Returns all active encounters in the world.

```php
public function getEncounters(string $clientId, bool $prettify = false): ?array
```

**Parameters:**
- `$clientId` (string): Auth token to connect to specific Foundry world.
- `$prettify` (bool, optional): Whether to prettify the response. Default: `false`.

**Returns:**
- `array|null`: List of active encounters or null on failure.

**Example:**
```php
$encounters = $client->getEncounters('your-client-id');
```

**Response Example:**
```php
[
    [
        "id" => "encounter1",
        "name" => "Goblin Ambush",
        "round" => 2,
        "turn" => 3,
        "combatants" => [
            [
                "id" => "combatant1",
                "name" => "Goblin 1",
                "initiative" => 15,
                "tokenId" => "token1",
                "actorId" => "actor1",
                "defeated" => false
            ],
            // More combatants...
        ],
        "active" => true
    ],
    // More encounters...
]
```

### startEncounter

Starts a new encounter with optional tokens.

```php
public function startEncounter(string $clientId, array $payload): ?array
```

**Parameters:**
- `$clientId` (string): Auth token to connect to specific Foundry world.
- `$payload` (array): Payload for starting the encounter:
  - `tokens` (array, optional): Array of token UUIDs.
  - `startWithSelected` (bool, optional): Whether to start with selected tokens.
  - `startWithPlayers` (bool, optional): Whether to start with player tokens.
  - `rollNPC` (bool, optional): Whether to roll initiative for NPCs.
  - `rollAll` (bool, optional): Whether to roll initiative for all combatants.
  - `name` (string, optional): Name for the encounter.

**Returns:**
- `array|null`: The started encounter data or null on failure.

**Example:**
```php
// Start encounter with specific tokens
$encounter = $client->startEncounter('your-client-id', [
    'tokens' => ['Scene.abc123.Token.def456', 'Scene.abc123.Token.ghi789'],
    'rollAll' => true,
    'name' => 'Forest Ambush'
]);

// Start encounter with selected tokens
$encounter = $client->startEncounter('your-client-id', [
    'startWithSelected' => true,
    'rollAll' => true
]);
```

**Response Example:**
```php
[
    "id" => "encounter2",
    "name" => "Forest Ambush",
    "round" => 1,
    "turn" => 0,
    "combatants" => [
        // Combatants data...
    ],
    "active" => true
]
```

### nextTurnInEncounter

Advances to the next turn in an encounter.

```php
public function nextTurnInEncounter(string $clientId, ?string $encounterId = null): ?array
```

**Parameters:**
- `$clientId` (string): Auth token to connect to specific Foundry world.
- `$encounterId` (string|null, optional): ID of the encounter to advance (uses active encounter if not specified). Default: `null`.

**Returns:**
- `array|null`: Encounter state after advancing turn or null on failure.

**Example:**
```php
// Advance turn in active encounter
$encounter = $client->nextTurnInEncounter('your-client-id');

// Advance turn in specific encounter
$encounter = $client->nextTurnInEncounter('your-client-id', 'encounter2');
```

**Response Example:**
```php
[
    "id" => "encounter2",
    "name" => "Forest Ambush",
    "round" => 1,
    "turn" => 1,
    "combatants" => [
        // Updated combatants data...
    ],
    "active" => true
]
```

### nextRoundInEncounter

Advances to the next round in an encounter.

```php
public function nextRoundInEncounter(string $clientId, ?string $encounterId = null): ?array
```

**Parameters:**
- `$clientId` (string): Auth token to connect to specific Foundry world.
- `$encounterId` (string|null, optional): ID of the encounter to advance (uses active encounter if not specified). Default: `null`.

**Returns:**
- `array|null`: Encounter state after advancing round or null on failure.

**Example:**
```php
// Advance round in active encounter
$encounter = $client->nextRoundInEncounter('your-client-id');

// Advance round in specific encounter
$encounter = $client->nextRoundInEncounter('your-client-id', 'encounter2');
```

**Response Example:**
```php
[
    "id" => "encounter2",
    "name" => "Forest Ambush",
    "round" => 2,
    "turn" => 0,
    "combatants" => [
        // Updated combatants data...
    ],
    "active" => true
]
```

### previousTurnInEncounter

Goes back to the previous turn in an encounter.

```php
public function previousTurnInEncounter(string $clientId, ?string $encounterId = null): ?array
```

**Parameters:**
- `$clientId` (string): Auth token to connect to specific Foundry world.
- `$encounterId` (string|null, optional): ID of the encounter to rewind (uses active encounter if not specified). Default: `null`.

**Returns:**
- `array|null`: Encounter state after rewinding turn or null on failure.

**Example:**
```php
// Go back to previous turn in active encounter
$encounter = $client->previousTurnInEncounter('your-client-id');

// Go back to previous turn in specific encounter
$encounter = $client->previousTurnInEncounter('your-client-id', 'encounter2');
```

**Response Example:**
```php
[
    "id" => "encounter2",
    "name" => "Forest Ambush",
    "round" => 1,
    "turn" => 0,
    "combatants" => [
        // Updated combatants data...
    ],
    "active" => true
]
```

### previousRoundInEncounter

Goes back to the previous round in an encounter.

```php
public function previousRoundInEncounter(string $clientId, ?string $encounterId = null): ?array
```

**Parameters:**
- `$clientId` (string): Auth token to connect to specific Foundry world.
- `$encounterId` (string|null, optional): ID of the encounter to rewind (uses active encounter if not specified). Default: `null`.

**Returns:**
- `array|null`: Encounter state after rewinding round or null on failure.

**Example:**
```php
// Go back to previous round in active encounter
$encounter = $client->previousRoundInEncounter('your-client-id');

// Go back to previous round in specific encounter
$encounter = $client->previousRoundInEncounter('your-client-id', 'encounter2');
```

**Response Example:**
```php
[
    "id" => "encounter2",
    "name" => "Forest Ambush",
    "round" => 1,
    "turn" => 0,
    "combatants" => [
        // Updated combatants data...
    ],
    "active" => true
]
```

### endEncounter

Ends a specific encounter.

```php
public function endEncounter(string $clientId, ?string $encounterId = null): ?array
```

**Parameters:**
- `$clientId` (string): Auth token to connect to specific Foundry world.
- `$encounterId` (string|null, optional): ID of the encounter to end (uses active encounter if not specified). Default: `null`.

**Returns:**
- `array|null`: Response message or null on failure.

**Example:**
```php
// End active encounter
$result = $client->endEncounter('your-client-id');

// End specific encounter
$result = $client->endEncounter('your-client-id', 'encounter2');
```

**Response Example:**
```php
[
    "success" => true,
    "message" => "Encounter ended successfully"
]
```

### addToEncounter

Add entities to an encounter.

```php
public function addToEncounter(string $clientId, array $payload, ?string $encounterId = null): ?array
```

**Parameters:**
- `$clientId` (string): Auth token to connect to specific Foundry world.
- `$payload` (array): Payload for adding entities:
  - `uuids` (array): Array of entity UUIDs.
  - `selected` (bool, optional): Whether to add selected entities.
  - `rollInitiative` (bool, optional): Whether to roll initiative for added entities.
- `$encounterId` (string|null, optional): ID of the encounter to add to (uses active encounter if not specified). Default: `null`.

**Returns:**
- `array|null`: Response indicating success/failure or null on failure.

**Example:**
```php
// Add specific entities to active encounter
$result = $client->addToEncounter('your-client-id', [
    'uuids' => ['Scene.abc123.Token.jkl012'],
    'rollInitiative' => true
]);

// Add selected entities to specific encounter
$result = $client->addToEncounter('your-client-id', [
    'selected' => true,
    'rollInitiative' => true
], 'encounter2');
```

**Response Example:**
```php
[
    "success" => true,
    "message" => "Added 1 entities to encounter",
    "combatants" => [
        // New combatant data...
    ]
]
```

### removeFromEncounter

Remove entities from an encounter.

```php
public function removeFromEncounter(string $clientId, array $payload, ?string $encounterId = null): ?array
```

**Parameters:**
- `$clientId` (string): Auth token to connect to specific Foundry world.
- `$payload` (array): Payload for removing entities:
  - `uuids` (array): Array of entity UUIDs.
  - `selected` (bool, optional): Whether to remove selected entities.
- `$encounterId` (string|null, optional): ID of the encounter to remove from (uses active encounter if not specified). Default: `null`.

**Returns:**
- `array|null`: Response indicating success/failure or null on failure.

**Example:**
```php
// Remove specific entities from active encounter
$result = $client->removeFromEncounter('your-client-id', [
    'uuids' => ['Scene.abc123.Token.jkl012']
]);

// Remove selected entities from specific encounter
$result = $client->removeFromEncounter('your-client-id', [
    'selected' => true
], 'encounter2');
```

**Response Example:**
```php
[
    "success" => true,
    "message" => "Removed 1 entities from encounter"
]
```

## Entity Manipulation

### markEntityAsDefeated

Mark an entity as defeated.

```php
public function markEntityAsDefeated(string $clientId, ?string $uuid = null, ?string $selected = null): ?array
```

**Parameters:**
- `$clientId` (string): Auth token to connect to specific Foundry world.
- `$uuid` (string|null, optional): UUID of the entity to mark as defeated. Default: `null`.
- `$selected` (string|null, optional): If 'true' mark all selected tokens as defeated. Default: `null`.

**Returns:**
- `array|null`: Response message or null on failure.

**Example:**
```php
// Mark specific entity as defeated
$result = $client->markEntityAsDefeated('your-client-id', 'Scene.abc123.Token.jkl012');

// Mark selected entities as defeated
$result = $client->markEntityAsDefeated('your-client-id', null, 'true');
```

**Response Example:**
```php
[
    "success" => true,
    "message" => "Marked 1 entities as defeated"
]
```

### decreaseAttribute

Decrease an attribute value on an entity.

```php
public function decreaseAttribute(string $clientId, string $attribute, float|int $amount, ?string $uuid = null, ?string $selected = null): ?array
```

**Parameters:**
- `$clientId` (string): Auth token to connect to specific Foundry world.
- `$attribute` (string): Attribute path (e.g., 'system.attributes.hp.value').
- `$amount` (float|int): Amount to decrease.
- `$uuid` (string|null, optional): UUID of the entity. Default: `null`.
- `$selected` (string|null, optional): If 'true' decrease all selected tokens. Default: `null`.

**Returns:**
- `array|null`: Response message or null on failure.

**Example:**
```php
// Decrease HP of specific entity
$result = $client->decreaseAttribute('your-client-id', 'system.attributes.hp.value', 5, 'Actor.def456');

// Decrease HP of selected entities
$result = $client->decreaseAttribute('your-client-id', 'system.attributes.hp.value', 5, null, 'true');
```

**Response Example:**
```php
[
    "success" => true,
    "message" => "Decreased attribute on 1 entities",
    "entities" => [
        [
            "uuid" => "Actor.def456",
            "name" => "Goblin",
            "attribute" => "system.attributes.hp.value",
            "oldValue" => 7,
            "newValue" => 2
        ]
    ]
]
```

### increaseAttribute

Increase an attribute value on an entity.

```php
public function increaseAttribute(string $clientId, array $payload): ?array
```

**Parameters:**
- `$clientId` (string): Auth token to connect to specific Foundry world.
- `$payload` (array): Payload containing:
  - `attribute` (string): Attribute path (e.g., 'system.attributes.hp.value').
  - `amount` (float|int): Amount to increase.
  - `uuid` (string, optional): UUID of the entity.
  - `selected` (bool, optional): Whether to increase attribute on selected entities.

**Returns:**
- `array|null`: Response message or null on failure.

**Example:**
```php
// Increase HP of specific entity
$result = $client->increaseAttribute('your-client-id', [
    'attribute' => 'system.attributes.hp.value',
    'amount' => 5,
    'uuid' => 'Actor.def456'
]);

// Increase HP of selected entities
$result = $client->increaseAttribute('your-client-id', [
    'attribute' => 'system.attributes.hp.value',
    'amount' => 5,
    'selected' => true
]);
```

**Response Example:**
```php
[
    "success" => true,
    "message" => "Increased attribute on 1 entities",
    "entities" => [
        [
            "uuid" => "Actor.def456",
            "name" => "Goblin",
            "attribute" => "system.attributes.hp.value",
            "oldValue" => 2,
            "newValue" => 7
        ]
    ]
]
```

### giveItem

Transfer an item from one actor to another.

```php
public function giveItem(string $clientId, array $payload): ?array
```

**Parameters:**
- `$clientId` (string): Auth token to connect to specific Foundry world.
- `$payload` (array): Payload containing item transfer details:
  - `itemUuid` (string): UUID of the item to transfer.
  - `fromUuid` (string, optional): UUID of the source actor.
  - `toUuid` (string, optional): UUID of the destination actor.
  - `selected` (bool, optional): Whether to use selected tokens.
  - `quantity` (int, optional): Quantity of the item to transfer.

**Returns:**
- `array|null`: Response message or null on failure.

**Example:**
```php
// Transfer item between specific actors
$result = $client->giveItem('your-client-id', [
    'itemUuid' => 'Item.ghi789',
    'fromUuid' => 'Actor.def456',
    'toUuid' => 'Actor.mno345',
    'quantity' => 1
]);

// Transfer item from selected actor
$result = $client->giveItem('your-client-id', [
    'itemUuid' => 'Item.ghi789',
    'selected' => true,
    'toUuid' => 'Actor.mno345'
]);
```

**Response Example:**
```php
[
    "success" => true,
    "message" => "Item transferred successfully",
    "item" => [
        "uuid" => "Item.ghi789",
        "name" => "Potion of Healing",
        "quantity" => 1
    ],
    "from" => [
        "uuid" => "Actor.def456",
        "name" => "Goblin"
    ],
    "to" => [
        "uuid" => "Actor.mno345",
        "name" => "Player Character"
    ]
]
```
