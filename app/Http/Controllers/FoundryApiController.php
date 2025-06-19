<?php

namespace App\Http\Controllers;

use App\Http\Controllers\FoundryClient as FoundryClient;
//use SurrealTech\FoundryApiWrapper\FoundryClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FoundryApiController extends Controller
{
    private FoundryClient $apiClient;

    public function __construct(
        FoundryClient $apiClient,
    ) {
        $this->apiClient = $apiClient;
    }

    /**
     * Sets up the API client with credentials from the request.
     * The Vue.js frontend sends 'baseUrl' and 'apiKey' in the POST body to each proxy endpoint.
     */
    private function setupApiClientFromRequest(Request $request): void
    {
        $baseUrl = $request->input('baseUrl', config('services.foundry.base_url', '')); //Could set defaults here...
        $apiKey = $request->input('apiKey', config('services.foundry.api_key', ''));

        $this->apiClient->setBaseUrl($baseUrl);
        $this->apiClient->setApiKey($apiKey);
    }


    /**
     * Show the API testing interface
     *
     * @return View
     */
    public function show(): View
    {
        return view('api');
    }

    /**
     * Helper method to return JSON responses
     *
     * @param mixed $data The data to return as JSON
     * @return JsonResponse
     */
    private function jsonResponse($data): JsonResponse
    {
        return response()->json($data);
    }


    public function getApiDocs(Request $request): JsonResponse
    {
        $this->setupApiClientFromRequest($request);
        return $this->jsonResponse($this->apiClient->getApiDocs());
    }

    public function getApiStatus(Request $request): JsonResponse
    {
        $this->setupApiClientFromRequest($request);
        return $this->jsonResponse($this->apiClient->getApiStatus());
    }

    public function getClients(Request $request): JsonResponse
    {
        $this->setupApiClientFromRequest($request);
        $prettify = $request->boolean('prettify', false);
        return $this->jsonResponse($this->apiClient->getClients($prettify));
    }

    public function getStructure(Request $request): JsonResponse
    {
        $this->setupApiClientFromRequest($request);
        $clientId = $request->input('clientId');
        $prettify = $request->boolean('prettify', false);
        if (!$clientId) return response()->json(['error' => true, 'message' => 'Client ID is required.'], 400);
        return $this->jsonResponse($this->apiClient->getStructure($clientId, $prettify));
    }

    public function getContentsByPath(Request $request): JsonResponse
    {
        $this->setupApiClientFromRequest($request);
        $path = $request->input('path');
        $clientId = $request->input('clientId');
        $prettify = $request->boolean('prettify', false);
        if (!$clientId) return response()->json(['error' => true, 'message' => 'Client ID is required.'], 400);
        if (!$path) return response()->json(['error' => true, 'message' => 'Path is required.'], 400);
        return $this->jsonResponse($this->apiClient->getContentsByPath($path, $clientId, $prettify));
    }

    public function searchEntities(Request $request): JsonResponse
    {
        $this->setupApiClientFromRequest($request);
        $clientId = $request->input('clientId');
        $query = $request->input('query');
        $filter = $request->input('filter');
        $prettify = $request->boolean('prettify', false);
        if (!$clientId) return response()->json(['error' => true, 'message' => 'Client ID is required.'], 400);
        if (!$query) return response()->json(['error' => true, 'message' => 'Query is required.'], 400);
        return $this->jsonResponse($this->apiClient->searchEntities($clientId, $query, $filter, $prettify));
    }

    public function selectEntities(Request $request): JsonResponse
    {
        $this->setupApiClientFromRequest($request);
        $clientId = $request->input('clientId');
        $payload = $request->input('payload'); // Vue sends this as a nested object
        if (!$clientId) return response()->json(['error' => true, 'message' => 'Client ID is required.'], 400);
        if (!is_array($payload)) return response()->json(['error' => true, 'message' => 'Payload must be a valid JSON object.'], 400);
        return $this->jsonResponse($this->apiClient->selectEntities($clientId, $payload));
    }

    public function getSelectedEntities(Request $request): JsonResponse
    {
        $this->setupApiClientFromRequest($request);
        $clientId = $request->input('clientId');
        $prettify = $request->boolean('prettify', false);
        if (!$clientId) return response()->json(['error' => true, 'message' => 'Client ID is required.'], 400);
        return $this->jsonResponse($this->apiClient->getSelectedEntities($clientId, $prettify));
    }

    public function getEntity(Request $request): JsonResponse
    {
        $this->setupApiClientFromRequest($request);
        $clientId = $request->input('clientId');
        $uuid = $request->input('uuid');
        $selected = $request->input('selected');
        $actor = $request->input('actor');
        $prettify = $request->boolean('prettify', false);
        if (!$clientId) return response()->json(['error' => true, 'message' => 'Client ID is required.'], 400);
        return $this->jsonResponse($this->apiClient->getEntity($clientId, $uuid, $selected, $actor, $prettify));
    }

    public function createEntity(Request $request): JsonResponse
    {
        $this->setupApiClientFromRequest($request);
        $clientId = $request->input('clientId');
        $payload = $request->input('payload');
        if (!$clientId) return response()->json(['error' => true, 'message' => 'Client ID is required.'], 400);
        if (!is_array($payload)) return response()->json(['error' => true, 'message' => 'Payload must be a valid JSON object.'], 400);
        return $this->jsonResponse($this->apiClient->createEntity($clientId, $payload));
    }

    public function updateEntity(Request $request): JsonResponse
    {
        $this->setupApiClientFromRequest($request);
        $clientId = $request->input('clientId');
        $uuid = $request->input('uuid');
        $selected = $request->input('selected');
        $actor = $request->input('actor');
        $payload = $request->input('payload');
        if (!$clientId) return response()->json(['error' => true, 'message' => 'Client ID is required.'], 400);
        if (!is_array($payload)) return response()->json(['error' => true, 'message' => 'Payload must be a valid JSON object.'], 400);
        return $this->jsonResponse($this->apiClient->updateEntity($clientId, $payload, $uuid, $selected, $actor));
    }

    public function deleteEntity(Request $request): JsonResponse
    {
        $this->setupApiClientFromRequest($request);
        $clientId = $request->input('clientId');
        $uuid = $request->input('uuid');
        $selected = $request->input('selected');
        if (!$clientId) return response()->json(['error' => true, 'message' => 'Client ID is required.'], 400);
        return $this->jsonResponse($this->apiClient->deleteEntity($clientId, $uuid, $selected));
    }

    public function getMacros(Request $request): JsonResponse
    {
        $this->setupApiClientFromRequest($request);
        $clientId = $request->input('clientId');
        $prettify = $request->boolean('prettify', false);
        if (!$clientId) return response()->json(['error' => true, 'message' => 'Client ID is required.'], 400);
        return $this->jsonResponse($this->apiClient->getMacros($clientId, $prettify));
    }

    public function executeMacro(Request $request): JsonResponse
    {
        $this->setupApiClientFromRequest($request);
        $clientId = $request->input('clientId');
        $uuid = $request->input('uuid');
        $args = $request->input('args'); // Can be null if not provided or empty JSON
        if (!$clientId) return response()->json(['error' => true, 'message' => 'Client ID is required.'], 400);
        if (!$uuid) return response()->json(['error' => true, 'message' => 'Macro UUID is required.'], 400);
        // Args can be null, the client method handles this.
        return $this->jsonResponse($this->apiClient->executeMacro($uuid, $clientId, $args ?? []));
    }

    public function getSheet(Request $request): JsonResponse
    {
        $this->setupApiClientFromRequest($request);
        $clientId = $request->input('clientId');
        $prettify = $request->boolean('prettify', false);
        if (!$clientId) return response()->json(['error' => true, 'message' => 'Client ID is required.'], 400);

        return $this->jsonResponse($this->apiClient->getSheet(
            $clientId,
            $request->input('uuid'),
            $request->input('selected'),
            $request->input('actor'),
            $request->input('format', 'json'),
            $request->input('scale') ? (float)$request->input('scale') : null,
            $request->input('tab') ? (int)$request->input('tab') : null,
            $request->input('darkMode') === null ? null : filter_var($request->input('darkMode'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            $prettify
        ));
    }

    // --- New Methods for Rolls ---
    public function getRolls(Request $request): JsonResponse
    {
        $this->setupApiClientFromRequest($request);
        $clientId = $request->input('clientId');
        $prettify = $request->boolean('prettify', false);
        if (!$clientId) return response()->json(['error' => true, 'message' => 'Client ID is required.'], 400);
        $limit = $request->input('limit');
        return $this->jsonResponse($this->apiClient->getRolls($clientId, $limit ? (int)$limit : 20, $prettify));
    }

    public function getLastRoll(Request $request): JsonResponse
    {
        $this->setupApiClientFromRequest($request);
        $clientId = $request->input('clientId');
        $prettify = $request->boolean('prettify', false);
        if (!$clientId) return response()->json(['error' => true, 'message' => 'Client ID is required.'], 400);
        return $this->jsonResponse($this->apiClient->getLastRoll($clientId, $prettify));
    }

    public function makeRoll(Request $request): JsonResponse
    {
        $this->setupApiClientFromRequest($request);
        $clientId = $request->input('clientId');
        $payload = $request->input('payload');
        if (!$clientId) return response()->json(['error' => true, 'message' => 'Client ID is required.'], 400);
        if (!is_array($payload)) return response()->json(['error' => true, 'message' => 'Payload must be a valid JSON object.'], 400);
        return $this->jsonResponse($this->apiClient->makeRoll($clientId, $payload));
    }

    // --- New Methods for Encounters ---
    public function getEncounters(Request $request): JsonResponse
    {
        $this->setupApiClientFromRequest($request);
        $clientId = $request->input('clientId');
        $prettify = $request->boolean('prettify', false);
        if (!$clientId) return response()->json(['error' => true, 'message' => 'Client ID is required.'], 400);
        return $this->jsonResponse($this->apiClient->getEncounters($clientId, $prettify));
    }

    public function startEncounter(Request $request): JsonResponse
    {
        $this->setupApiClientFromRequest($request);
        $clientId = $request->input('clientId');
        $payload = $request->input('payload');
        if (!$clientId) return response()->json(['error' => true, 'message' => 'Client ID is required.'], 400);
        if (!is_array($payload)) return response()->json(['error' => true, 'message' => 'Payload must be a valid JSON object.'], 400);
        return $this->jsonResponse($this->apiClient->startEncounter($clientId, $payload));
    }

    public function nextTurnInEncounter(Request $request): JsonResponse
    {
        $this->setupApiClientFromRequest($request);
        $clientId = $request->input('clientId');
        $encounterId = $request->input('encounterId');
        if (!$clientId) return response()->json(['error' => true, 'message' => 'Client ID is required.'], 400);
        return $this->jsonResponse($this->apiClient->nextTurnInEncounter($clientId, $encounterId));
    }

    public function nextRoundInEncounter(Request $request): JsonResponse
    {
        $this->setupApiClientFromRequest($request);
        $clientId = $request->input('clientId');
        $encounterId = $request->input('encounterId');
        if (!$clientId) return response()->json(['error' => true, 'message' => 'Client ID is required.'], 400);
        return $this->jsonResponse($this->apiClient->nextRoundInEncounter($clientId, $encounterId));
    }

    public function previousTurnInEncounter(Request $request): JsonResponse
    {
        $this->setupApiClientFromRequest($request);
        $clientId = $request->input('clientId');
        $encounterId = $request->input('encounterId');
        if (!$clientId) return response()->json(['error' => true, 'message' => 'Client ID is required.'], 400);
        return $this->jsonResponse($this->apiClient->previousTurnInEncounter($clientId, $encounterId));
    }

    public function previousRoundInEncounter(Request $request): JsonResponse
    {
        $this->setupApiClientFromRequest($request);
        $clientId = $request->input('clientId');
        $encounterId = $request->input('encounterId');
        if (!$clientId) return response()->json(['error' => true, 'message' => 'Client ID is required.'], 400);
        return $this->jsonResponse($this->apiClient->previousRoundInEncounter($clientId, $encounterId));
    }

    public function endEncounter(Request $request): JsonResponse
    {
        $this->setupApiClientFromRequest($request);
        $clientId = $request->input('clientId');
        $encounterId = $request->input('encounterId');
        if (!$clientId) return response()->json(['error' => true, 'message' => 'Client ID is required.'], 400);
        return $this->jsonResponse($this->apiClient->endEncounter($clientId, $encounterId));
    }

    public function addToEncounter(Request $request): JsonResponse
    {
        $this->setupApiClientFromRequest($request);
        $clientId = $request->input('clientId');
        $encounterId = $request->input('encounterId');
        $payload = $request->input('payload');
        if (!$clientId) return response()->json(['error' => true, 'message' => 'Client ID is required.'], 400);
        if (!is_array($payload)) return response()->json(['error' => true, 'message' => 'Payload must be a valid JSON object.'], 400);
        return $this->jsonResponse($this->apiClient->addToEncounter($clientId, $payload, $encounterId));
    }

    public function removeFromEncounter(Request $request): JsonResponse
    {
        $this->setupApiClientFromRequest($request);
        $clientId = $request->input('clientId');
        $encounterId = $request->input('encounterId');
        $payload = $request->input('payload');
        if (!$clientId) return response()->json(['error' => true, 'message' => 'Client ID is required.'], 400);
        if (!is_array($payload)) return response()->json(['error' => true, 'message' => 'Payload must be a valid JSON object.'], 400);
        return $this->jsonResponse($this->apiClient->removeFromEncounter($clientId, $payload, $encounterId));
    }

    // --- New Methods for Token/Actor Actions ---
    public function markEntityAsDefeated(Request $request): JsonResponse
    {
        $this->setupApiClientFromRequest($request);
        $clientId = $request->input('clientId');
        $uuid = $request->input('uuid');
        $selected = $request->input('selected');
        if (!$clientId) return response()->json(['error' => true, 'message' => 'Client ID is required.'], 400);
        return $this->jsonResponse($this->apiClient->markEntityAsDefeated($clientId, $uuid, $selected));
    }

    public function decreaseAttribute(Request $request): JsonResponse
    {
        $this->setupApiClientFromRequest($request);
        $clientId = $request->input('clientId');
        $attribute = $request->input('attribute');
        $amount = $request->input('amount'); // Will be string, cast in client if needed, or ensure client handles string
        $uuid = $request->input('uuid');
        $selected = $request->input('selected');

        if (!$clientId) return response()->json(['error' => true, 'message' => 'Client ID is required.'], 400);
        if (!$attribute) return response()->json(['error' => true, 'message' => 'Attribute is required.'], 400);
        if ($amount === null) return response()->json(['error' => true, 'message' => 'Amount is required.'], 400);

        // The client expects float|int for amount. Let's try to cast.
        $numericAmount = filter_var($amount, FILTER_VALIDATE_FLOAT);
        if ($numericAmount === false) {
            // Try int if float fails
            $numericAmount = filter_var($amount, FILTER_VALIDATE_INT);
            if ($numericAmount === false) {
                return response()->json(['error' => true, 'message' => 'Amount must be a valid number.'], 400);
            }
        }

        return $this->jsonResponse($this->apiClient->decreaseAttribute($clientId, $attribute, $numericAmount, $uuid, $selected));
    }

    public function increaseAttribute(Request $request): JsonResponse
    {
        $this->setupApiClientFromRequest($request);
        $clientId = $request->input('clientId');
        $payload = $request->input('payload');
        if (!$clientId) return response()->json(['error' => true, 'message' => 'Client ID is required.'], 400);
        if (!is_array($payload)) return response()->json(['error' => true, 'message' => 'Payload must be a valid JSON object.'], 400);
        if (!isset($payload['attribute']) || !isset($payload['amount'])) {
            return response()->json(['error' => true, 'message' => 'Payload must include attribute and amount.'], 400);
        }
        return $this->jsonResponse($this->apiClient->increaseAttribute($clientId, $payload));
    }

    public function giveItem(Request $request): JsonResponse
    {
        $this->setupApiClientFromRequest($request);
        $clientId = $request->input('clientId');
        $payload = $request->input('payload');
        if (!$clientId) return response()->json(['error' => true, 'message' => 'Client ID is required.'], 400);
        if (!is_array($payload)) return response()->json(['error' => true, 'message' => 'Payload must be a valid JSON object.'], 400);
        if (!isset($payload['itemUuid'])) {
            return response()->json(['error' => true, 'message' => 'Payload must include itemUuid.'], 400);
        }
        return $this->jsonResponse($this->apiClient->giveItem($clientId, $payload));
    }
}
