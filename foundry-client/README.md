# Foundry VTT REST API Client

A PHP client for interacting with the Foundry VTT REST API. This client provides a comprehensive set of methods to interact with the Foundry VTT REST API, making it easy to integrate Foundry VTT with your PHP applications.

## Installation

You can install the package via composer:

```bash
composer require surrealtech/foundry-api-php-client
```

## Usage

```php
use FoundryClient\FoundryClient;

// Initialize the client with your API key and base URL
$client = new FoundryClient(
    'https://foundryvtt-rest-api-relay.fly.dev',
    'your-api-key'
);

// Get the API status
$status = $client->getApiStatus();

// Get connected clients
$clients = $client->getClients();

// Get the structure of a world
$structure = $client->getStructure('your-client-id');

// Search for entities
$searchResults = $client->searchEntities('your-client-id', 'search-query', 'filter');

// Get an entity
$entity = $client->getEntity('your-client-id', 'entity-uuid');

// Create an entity
$newEntity = $client->createEntity('your-client-id', [
    'type' => 'Actor',
    'data' => [
        'name' => 'New Actor',
        // ... other data
    ]
]);

// Update an entity
$updatedEntity = $client->updateEntity('your-client-id', [
    'name' => 'Updated Actor',
    // ... other data
], 'entity-uuid');

// Delete an entity
$deleteResult = $client->deleteEntity('your-client-id', 'entity-uuid');

// ... and many more methods for interacting with the Foundry VTT REST API
```

## API Documentation

For detailed documentation of all available methods, including parameters, return values, and example usage, please see the [API Documentation](API.md).

## Requirements

- PHP 8.0 or higher
- GuzzleHttp 7.0 or higher

## How to use Foundry REST API

- Install the Foundry VTT Module (https://github.com/ThreeHats/foundryvtt-rest-api)
- Get an API key for the public relay server at https://foundryvtt-rest-api-relay.fly.dev/
- Each request must have your API key in the "x-api-key" header, except for /api/status and /api/docs.
- Endpoints other than /clients require a clientId parameter that matches a connected world.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
