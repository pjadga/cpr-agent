<?php
declare(strict_types=1);

namespace FoundryClient;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Psr\Http\Message\ResponseInterface;

/**
 * A PHP client for interacting with the Foundry REST API.
 *
 * This client is generated based on the Postman collection for the Foundry REST API.
 * It uses GuzzleHttp to make HTTP requests.
 *
 * How to use Foundry REST API:
 * - Install the Foundry VTT Module (https://github.com/ThreeHats/foundryvtt-rest-api)
 * - Get an API key for the public relay server at https://foundryvtt-rest-api-relay.fly.dev/
 * - Each request must have your API key in the "x-api-key" header, except for /api/status and /api/docs.
 * - Endpoints other than /clients require a clientId parameter that matches a connected world.
 */
class FoundryClient
{
    private Client $client;
    private string $baseUrl;
    private string $apiKey;
    private JsonPrettifier $jsonPrettifier;

    /**
     * Constructor for the FoundryApiClient.
     *
     * @param string $baseUrl The base URL for the Foundry REST API (e.g., "https://foundryvtt-rest-api-relay.fly.dev").
     * @param string $apiKey  Your API key for the Foundry REST API.
     */
    public function __construct(string $baseUrl = "", string $apiKey = "")
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout'  => 10.0, // Default timeout for requests
            'http_errors' => false, // We will handle HTTP errors manually
        ]);
        $this->jsonPrettifier = new JsonPrettifier();
    }

    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    public function setBaseUrl(string $baseUrl): void
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Makes an HTTP request to the API.
     *
     * @param string $method  The HTTP method (GET, POST, PUT, DELETE, etc.).
     * @param string $uri     The URI for the request, relative to the base URL.
     * @param array  $options Additional options for the Guzzle request (e.g., query, json, headers).
     * @param bool   $prettify Whether to prettify the response (make IDs/UUIDs clickable).
     * @return array|null The decoded JSON response as an associative array, or null on failure.
     */
    private function makeRequest(string $method, string $uri, array $options = [], bool $prettify = false): ?array
    {
        // Prepare headers with API key if needed
        $options['headers'] = $this->prepareHeaders($uri, $options['headers'] ?? []);

        // Build the full URI
        $fullUri = $this->baseUrl . $uri;

        try {
            // Make the request
            $response = $this->client->request($method, $fullUri, $options);
            return $this->processResponse($response, $uri, $options, $prettify);
        } catch (RequestException $e) {
            return $this->handleRequestException($e, $method, $fullUri, $options);
        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
                'context' => ['method' => $method, 'uri' => $fullUri]
            ];
        } catch (GuzzleException $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
                'context' => ['method' => $method, 'uri' => $fullUri]
            ];
        }
    }

    /**
     * Prepares the headers for an API request, including the API key if needed.
     *
     * @param string $uri The URI for the request
     * @param array $headers Any existing headers
     * @return array The prepared headers
     */
    private function prepareHeaders(string $uri, array $headers = []): array
    {
        $defaultHeaders = ['Accept' => 'application/json'];

        // Determine if API key is needed based on the endpoint path
        $apiKeyNeeded = !in_array($uri, ['/api/status', '/api/docs']);

        if ($apiKeyNeeded && !empty($this->apiKey)) {
            $defaultHeaders['x-api-key'] = $this->apiKey;
        }

        return array_merge($defaultHeaders, $headers);
    }

    /**
     * Processes the API response.
     *
     * @param ResponseInterface $response The response from the API
     * @param string $uri The URI that was requested
     * @param array $options The request options
     * @param bool $prettify Whether to prettify the response (make IDs/UUIDs clickable)
     * @return array The processed response data
     */
    private function processResponse(ResponseInterface $response, string $uri, array $options, bool $prettify = false): array
    {
        $statusCode = $response->getStatusCode();
        $bodyContents = $response->getBody()->getContents();

        if ($statusCode >= 200 && $statusCode < 300) {
            // For HTML response (like /sheet), return raw body if not JSON
            if (str_starts_with($uri, '/sheet') && ($options['query']['format'] ?? 'json') === 'html') {
                if ($response->getHeaderLine('Content-Type') === 'text/html; charset=utf-8') {
                    return ['html_content' => $bodyContents];
                }
            }

            $data = json_decode($bodyContents, true);

            // If prettify is enabled, process the response to make IDs/UUIDs clickable
            if ($prettify && is_array($data)) {
                $data = $this->jsonPrettifier->prettifyResponse($data);
            }

            return $data;
        } else {
            return $this->processErrorResponse($statusCode, $bodyContents, $response);
        }
    }

    /**
     * Processes an error response from the API.
     *
     * @param int $statusCode The HTTP status code
     * @param string $bodyContents The response body
     * @param ResponseInterface $response The full response object
     * @return array The processed error data
     */
    private function processErrorResponse(int $statusCode, string $bodyContents, ResponseInterface $response): array
    {
        // Attempt to decode error response, otherwise return status and message
        $errorData = json_decode($bodyContents, true);
        if (is_array($errorData)) {
            return array_merge(['error' => true, 'status_code' => $statusCode], $errorData);
        }
        return [
            'error' => true,
            'status_code' => $statusCode,
            'message' => $bodyContents ?: $response->getReasonPhrase()
        ];
    }

    /**
     * Handles exceptions that occur during API requests.
     *
     * @param RequestException $e The exception that occurred
     * @param string $method The HTTP method used
     * @param string $uri The URI that was requested
     * @param array $options The request options
     * @return array Error information
     */
    private function handleRequestException(RequestException $e, string $method, string $uri, array $options): array
    {
        $context = ['method' => $method, 'uri' => $uri, 'options' => $options];

        if ($e->hasResponse()) {
            $response = $e->getResponse();
            $context['status_code'] = $response->getStatusCode();
            $context['response_body'] = $response->getBody()->getContents();
        }

        return ['error' => true, 'message' => $e->getMessage(), 'context' => $context];
    }

    /**
     * Returns the API documentation.
     * @return array|null The API documentation.
     */
    public function getApiDocs(): ?array
    {
        return $this->makeRequest('GET', '/api/docs');
    }

    /**
     * Returns the API status.
     * @return array|null The API status.
     */
    public function getApiStatus(): ?array
    {
        return $this->makeRequest('GET', '/api/status');
    }

    /**
     * Returns connected client Foundry Worlds.
     * @param bool $prettify Whether to prettify the response (make IDs/UUIDs clickable).
     * @return array|null List of connected clients.
     */
    public function getClients(bool $prettify = true): ?array
    {
        return $this->makeRequest('GET', '/clients', [], $prettify);
    }

    /**
     * Returns the folders and compendiums in the world.
     * @param string $clientId Auth token to connect to specific Foundry world.
     * @param bool $prettify Whether to prettify the response (make IDs/UUIDs clickable).
     * @return array|null The folder structure and compendiums.
     */
    public function getStructure(string $clientId, bool $prettify = true): ?array
    {
        $options = [
            'query' => ['clientId' => $clientId],
        ];
        return $this->makeRequest('GET', '/structure', $options, $prettify);
    }

    /**
     * Returns the contents of a folder or compendium.
     * @param string $path Path to the folder or compendium.
     * @param string $clientId Auth token to connect to specific Foundry world.
     * @param bool $prettify Whether to prettify the response (make IDs/UUIDs clickable).
     * @return array|null The contents of the folder or compendium.
     */
    public function getContentsByPath(string $path, string $clientId, bool $prettify = false): ?array
    {
        $uri = str_replace(':path', urlencode($path), '/contents/:path');
        $options = [
            'query' => ['clientId' => $clientId],
        ];
        return $this->makeRequest('GET', $uri, $options, $prettify);
    }

    /**
     * Searches Foundry VTT entities using QuickInsert.
     * Filters can be a single string for filtering by type ("actor", "item", ext.), or chained together (name:bob,documentType:actor).
     * Available filters: documentType, folder, id, name, package, packageName, subType, uuid, icon, journalLink, tagline, formattedMatch, resultType.
     * @param string $clientId Auth token to connect to specific Foundry world.
     * @param string $query Search string.
     * @param string|null $filter Filters to apply.
     * @param bool $prettify Whether to prettify the response (make IDs/UUIDs clickable).
     * @return array|null Search results.
     */
    public function searchEntities(string $clientId, string $query, ?string $filter = null, bool $prettify = false): ?array
    {
        $queryParams = [
            'clientId' => $clientId,
            'query' => $query,
        ];
        if ($filter !== null) {
            $queryParams['filter'] = $filter;
        }
        $options = ['query' => $queryParams];
        return $this->makeRequest('GET', '/search', $options, $prettify);
    }

    /**
     * Selects entities in Foundry.
     * @param string $clientId Auth token to connect to specific Foundry world.
     * @param array $payload The payload containing uuids, name, data, overwrite.
     * Example: `['uuids' => ['Scene.ckW0qDbFoWblqQiP.Token.cklVpiD7jUgjuAfs'], 'name' => 'ape', 'data' => ['actor.system.attributes.hp.value' => 10], 'overwrite' => true]`
     * @return array|null Response from the server.
     */
    public function selectEntities(string $clientId, array $payload): ?array
    {
        $options = [
            'query' => ['clientId' => $clientId],
            'json' => $payload,
        ];
        return $this->makeRequest('POST', '/select', $options);
    }

    /**
     * Returns the currently selected entities.
     * @param string $clientId Auth token to connect to specific Foundry world.
     * @param bool $prettify Whether to prettify the response (make IDs/UUIDs clickable).
     * @return array|null The selected entities.
     */
    public function getSelectedEntities(string $clientId, bool $prettify = false): ?array
    {
        // Note: The Postman collection has /search as the URL for /selected, which seems incorrect.
        // Assuming the correct path is /selected based on the name.
        $options = ['query' => ['clientId' => $clientId]];
        return $this->makeRequest('GET', '/selected', $options, $prettify);
    }

    /**
     * Returns JSON data for an entity.
     * @param string $clientId Auth token to connect to specific Foundry world.
     * @param string|null $uuid Entity UUID.
     * @param bool|null $selected Use selected entity (pass true as string 'true' or 'false').
     * @param bool|null $actor Use selected entity's actor (pass true as string 'true' or 'false').
     * @param bool $prettify Whether to prettify the response (make IDs/UUIDs clickable).
     * @return array|null Entity data.
     */
    public function getEntity(string $clientId, ?string $uuid = null, ?string $selected = null, ?string $actor = null, bool $prettify = false): ?array
    {
        $query = ['clientId' => $clientId];
        if ($uuid !== null) {
            $query['uuid'] = $uuid;
        }
        if ($selected !== null) {
            $query['selected'] = $selected;
        }
        if ($actor !== null) {
            $query['actor'] = $actor;
        }
        $options = ['query' => $query];
        return $this->makeRequest('GET', '/get', $options, $prettify);
    }

    /**
     * Creates a new entity in Foundry with the given JSON.
     * @param string $clientId Auth token to connect to specific Foundry world.
     * @param array $payload Payload containing 'type' and 'data'. Example: `['type' => 'Actor', 'data' => ['name' => 'Ape', ...]]`
     * @return array|null The created entity data.
     */
    public function createEntity(string $clientId, array $payload): ?array
    {
        $options = [
            'query' => ['clientId' => $clientId],
            'json' => $payload,
        ];
        return $this->makeRequest('POST', '/create', $options);
    }

    /**
     * Updates an entity with the given JSON props.
     * @param string $clientId Auth token to connect to specific Foundry world.
     * @param array $payload Data to update.
     * @param string|null $uuid UUID of the entity to update.
     * @param string|null $selected If 'true', updates all selected entities.
     * @param string|null $actor If 'true' and selected is 'true', updates the currently selected actors.
     * @return array|null The updated entity data.
     */
    public function updateEntity(string $clientId, array $payload, ?string $uuid = null, ?string $selected = null, ?string $actor = null): ?array
    {
        $query = ['clientId' => $clientId];
        if ($uuid !== null) {
            $query['uuid'] = $uuid;
        }
        if ($selected !== null) {
            $query['selected'] = $selected;
        }
        if ($actor !== null) {
            $query['actor'] = $actor;
        }
        $options = [
            'query' => $query,
            'json' => $payload,
        ];
        return $this->makeRequest('PUT', '/update', $options);
    }

    /**
     * Deletes an entity from Foundry.
     * @param string $clientId Auth token to connect to specific Foundry world.
     * @param string|null $uuid UUID of the entity to delete.
     * @param string|null $selected If 'true', deletes all selected entities.
     * @return array|null Response message.
     */
    public function deleteEntity(string $clientId, ?string $uuid = null, ?string $selected = null): ?array
    {
        $query = ['clientId' => $clientId];
        if ($uuid !== null) {
            $query['uuid'] = $uuid;
        }
        if ($selected !== null) {
            $query['selected'] = $selected;
        }
        $options = ['query' => $query];
        return $this->makeRequest('DELETE', '/delete', $options);
    }

    /**
     * Returns all available macros.
     * @param string $clientId Auth token to connect to specific Foundry world.
     * @param bool $prettify Whether to prettify the response (make IDs/UUIDs clickable).
     * @return array|null List of macros.
     */
    public function getMacros(string $clientId, bool $prettify = false): ?array
    {
        $options = ['query' => ['clientId' => $clientId]];
        return $this->makeRequest('GET', '/macros', $options, $prettify);
    }

    /**
     * Executes a macro by UUID.
     * @param string $uuid UUID of the macro to execute.
     * @param string $clientId Auth token to connect to specific Foundry world.
     * @param array|null $args Arguments to pass to the macro (as JSON payload).
     * @return array|null Result of the macro execution.
     */
    public function executeMacro(string $uuid, string $clientId, ?array $args = null): ?array
    {
        $uri = str_replace(':uuid', urlencode($uuid), '/macro/:uuid/execute');
        $options = [
            'query' => ['clientId' => $clientId],
        ];
        if ($args !== null) {
            $options['json'] = $args;
        }
        return $this->makeRequest('POST', $uri, $options);
    }

    /**
     * Returns raw HTML (or a string in a JSON response) for an entity.
     * If returning HTML there are options for scale, tab to open (if available), and darkMode.
     * If returning JSON the HTML is untouched.
     * @param string $clientId Auth token to connect to specific Foundry world.
     * @param string|null $uuid UUID of the entity to get the sheet for.
     * @param string|null $selected If 'true', returns the sheet for all selected entity.
     * @param string|null $actor If 'true' and selected is 'true', returns the sheet for the currently selected actor.
     * @param string|null $format Response format, 'html' or 'json'. Default 'json'.
     * @param float|null $scale Scale factor for the sheet (if format is html).
     * @param int|null $tab Index of the tab to activate (if format is html).
     * @param bool|null $darkMode Whether to use dark mode (if format is html).
     * @param bool $prettify Whether to prettify the response (make IDs/UUIDs clickable).
     * @return array|null HTML content or JSON data.
     */
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
    ): ?array {
        $query = ['clientId' => $clientId];
        if ($uuid !== null) $query['uuid'] = $uuid;
        if ($selected !== null) $query['selected'] = $selected;
        if ($actor !== null) $query['actor'] = $actor;
        if ($format !== null) $query['format'] = $format;
        if ($scale !== null) $query['scale'] = (string)$scale;
        if ($tab !== null) $query['tab'] = (string)$tab;
        if ($darkMode !== null) $query['darkMode'] = $darkMode ? 'true' : 'false';

        $options = ['query' => $query];
        return $this->makeRequest('GET', '/sheet', $options, $prettify);
    }

    /**
     * Returns up to the last 20 rolls.
     * @param string $clientId Auth token to connect to specific Foundry world.
     * @param int|null $limit Max number of rolls to return. Max 20. Default 20.
     * @param bool $prettify Whether to prettify the response (make IDs/UUIDs clickable).
     * @return array|null List of rolls.
     */
    public function getRolls(string $clientId, ?int $limit = 20, bool $prettify = false): ?array
    {
        $query = ['clientId' => $clientId];
        if ($limit !== null) {
            $query['limit'] = (string)$limit;
        }
        $options = ['query' => $query];
        return $this->makeRequest('GET', '/rolls', $options, $prettify);
    }

    /**
     * Returns the last roll made.
     * @param string $clientId Auth token to connect to specific Foundry world.
     * @param bool $prettify Whether to prettify the response (make IDs/UUIDs clickable).
     * @return array|null The last roll.
     */
    public function getLastRoll(string $clientId, bool $prettify = false): ?array
    {
        $options = ['query' => ['clientId' => $clientId]];
        return $this->makeRequest('GET', '/lastroll', $options, $prettify);
    }

    /**
     * Makes a new roll in Foundry.
     * @param string $clientId Auth token to connect to specific Foundry world.
     * @param array $payload Payload containing roll details.
     * Keys: formula (string, e.g. '2d6+3'), itemUuid (string), flavor (string),
     * createChatMessage (boolean), speaker (string, token/actor UUID),
     * target (string, token/actor UUID), whisper (array of user IDs).
     * Either formula or itemUuid is required.
     * @return array|null Result of the roll.
     */
    public function makeRoll(string $clientId, array $payload): ?array
    {
        $options = [
            'query' => ['clientId' => $clientId],
            'json' => $payload,
        ];
        return $this->makeRequest('POST', '/roll', $options);
    }

    /**
     * Returns all active encounters in the world.
     * @param string $clientId Auth token to connect to specific Foundry world.
     * @param bool $prettify Whether to prettify the response (make IDs/UUIDs clickable).
     * @return array|null List of active encounters.
     */
    public function getEncounters(string $clientId, bool $prettify = false): ?array
    {
        $options = ['query' => ['clientId' => $clientId]];
        return $this->makeRequest('GET', '/encounters', $options, $prettify);
    }

    /**
     * Starts a new encounter with optional tokens.
     * @param string $clientId Auth token to connect to specific Foundry world.
     * @param array $payload Payload for starting the encounter.
     * Keys: tokens (array of token UUIDs), startWithSelected (boolean),
     * startWithPlayers (boolean), rollNPC (boolean), rollAll (boolean), name (string).
     * @return array|null The started encounter data.
     */
    public function startEncounter(string $clientId, array $payload): ?array
    {
        $options = [
            'query' => ['clientId' => $clientId],
            'json' => $payload,
        ];
        return $this->makeRequest('POST', '/start-encounter', $options);
    }

    /**
     * Advances to the next turn in an encounter.
     * @param string $clientId Auth token to connect to specific Foundry world.
     * @param string|null $encounterId ID of the encounter to advance (uses active encounter if not specified).
     * @return array|null Encounter state after advancing turn.
     */
    public function nextTurnInEncounter(string $clientId, ?string $encounterId = null): ?array
    {
        $query = ['clientId' => $clientId];
        if ($encounterId !== null) {
            $query['encounter'] = $encounterId;
        }
        $options = ['query' => $query];
        return $this->makeRequest('POST', '/next-turn', $options);
    }

    /**
     * Advances to the next round in an encounter.
     * @param string $clientId Auth token to connect to specific Foundry world.
     * @param string|null $encounterId ID of the encounter to advance (uses active encounter if not specified).
     * @return array|null Encounter state after advancing round.
     */
    public function nextRoundInEncounter(string $clientId, ?string $encounterId = null): ?array
    {
        $query = ['clientId' => $clientId];
        if ($encounterId !== null) {
            $query['encounter'] = $encounterId;
        }
        $options = ['query' => $query];
        return $this->makeRequest('POST', '/next-round', $options);
    }

    /**
     * Goes back to the previous turn in an encounter.
     * @param string $clientId Auth token to connect to specific Foundry world.
     * @param string|null $encounterId ID of the encounter to rewind (uses active encounter if not specified).
     * @return array|null Encounter state after rewinding turn.
     */
    public function previousTurnInEncounter(string $clientId, ?string $encounterId = null): ?array
    {
        $query = ['clientId' => $clientId];
        if ($encounterId !== null) {
            $query['encounter'] = $encounterId;
        }
        $options = ['query' => $query];
        return $this->makeRequest('POST', '/last-turn', $options);
    }

    /**
     * Goes back to the previous round in an encounter.
     * @param string $clientId Auth token to connect to specific Foundry world.
     * @param string|null $encounterId ID of the encounter to rewind (uses active encounter if not specified).
     * @return array|null Encounter state after rewinding round.
     */
    public function previousRoundInEncounter(string $clientId, ?string $encounterId = null): ?array
    {
        $query = ['clientId' => $clientId];
        if ($encounterId !== null) {
            $query['encounter'] = $encounterId;
        }
        $options = ['query' => $query];
        return $this->makeRequest('POST', '/last-round', $options);
    }

    /**
     * Ends a specific encounter.
     * @param string $clientId Auth token to connect to specific Foundry world.
     * @param string|null $encounterId ID of the encounter to end.
     * @return array|null Response message.
     */
    public function endEncounter(string $clientId, ?string $encounterId = null): ?array
    {
        $query = ['clientId' => $clientId];
        if ($encounterId !== null) {
            $query['encounter'] = $encounterId;
        }
        $options = ['query' => $query];
        return $this->makeRequest('POST', '/end-encounter', $options);
    }

    /**
     * Add entities to an encounter.
     * @param string $clientId Auth token to connect to specific Foundry world.
     * @param array $payload Payload for adding entities.
     * Keys: uuids (array of entity UUIDs), selected (boolean), rollInitiative (boolean).
     * @param string|null $encounterId ID of the encounter to add to.
     * @return array|null Response indicating success/failure.
     */
    public function addToEncounter(string $clientId, array $payload, ?string $encounterId = null): ?array
    {
        $query = ['clientId' => $clientId];
        if ($encounterId !== null) {
            $query['encounter'] = $encounterId;
        }
        $options = [
            'query' => $query,
            'json' => $payload,
        ];
        return $this->makeRequest('POST', '/add-to-encounter', $options);
    }

    /**
     * Remove entities from an encounter.
     * @param string $clientId Auth token to connect to specific Foundry world.
     * @param array $payload Payload for removing entities.
     * Keys: uuids (array of entity UUIDs), selected (boolean).
     * @param string|null $encounterId ID of the encounter to remove from.
     * @return array|null Response indicating success/failure.
     */
    public function removeFromEncounter(string $clientId, array $payload, ?string $encounterId = null): ?array
    {
        $query = ['clientId' => $clientId];
        if ($encounterId !== null) {
            $query['encounter'] = $encounterId;
        }
        $options = [
            'query' => $query,
            'json' => $payload,
        ];
        return $this->makeRequest('POST', '/remove-from-encounter', $options);
    }

    /**
     * Mark an entity as defeated.
     * @param string $clientId Auth token to connect to specific Foundry world.
     * @param string|null $uuid UUID of the entity to mark as defeated.
     * @param string|null $selected If 'true' mark all selected tokens as defeated.
     * @return array|null Response message.
     */
    public function markEntityAsDefeated(string $clientId, ?string $uuid = null, ?string $selected = null): ?array
    {
        $query = ['clientId' => $clientId];
        if ($uuid !== null) $query['uuid'] = $uuid;
        if ($selected !== null) $query['selected'] = $selected;
        $options = ['query' => $query];
        return $this->makeRequest('POST', '/kill', $options);
    }

    /**
     * Decrease an attribute value on an entity.
     * @param string $clientId Auth token to connect to specific Foundry world.
     * @param string $attribute Attribute path (e.g. 'system.attributes.hp.value').
     * @param float|int $amount Amount to decrease.
     * @param string|null $uuid UUID of the entity.
     * @param string|null $selected If 'true' decrease all selected tokens.
     * @return array|null Response message.
     */
    public function decreaseAttribute(string $clientId, string $attribute, float|int $amount, ?string $uuid = null, ?string $selected = null): ?array
    {
        $query = [
            'clientId' => $clientId,
            'attribute' => $attribute,
            'amount' => $amount,
        ];
        if ($uuid !== null) $query['uuid'] = $uuid;
        if ($selected !== null) $query['selected'] = $selected;
        $options = ['query' => $query];
        return $this->makeRequest('POST', '/decrease', $options);
    }

    /**
     * Increase an attribute value on an entity.
     * @param string $clientId Auth token to connect to specific Foundry world.
     * @param array $payload Payload containing 'attribute', 'amount', and optionally 'uuid', 'selected'.
     * Example: `['attribute' => 'system.attributes.hp.value', 'amount' => 10, 'uuid' => 'Actor.xyz']`
     * @return array|null Response message.
     */
    public function increaseAttribute(string $clientId, array $payload): ?array
    {
        $options = [
            'query' => ['clientId' => $clientId],
            'json' => $payload,
        ];
        return $this->makeRequest('POST', '/increase', $options);
    }

    /**
     * Transfer an item from one actor to another.
     * @param string $clientId Auth token to connect to specific Foundry world.
     * @param array $payload Payload containing item transfer details.
     * Required: 'itemUuid' (string).
     * Optional: 'fromUuid' (string), 'toUuid' (string), 'selected' (boolean), 'quantity' (number).
     * @return array|null Response message.
     */
    public function giveItem(string $clientId, array $payload): ?array
    {
        $options = [
            'query' => ['clientId' => $clientId],
            'json' => $payload,
        ];
        return $this->makeRequest('POST', '/give', $options);
    }
}
