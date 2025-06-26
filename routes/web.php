<?php

use App\Http\Controllers\FoundryApiController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/api-test', function () { // Route to display the Blade view
    return view('api');
});

// Group for API proxy calls
Route::prefix('laravel-foundry-api')->group(function () {
    Route::post('/get-api-docs', [FoundryApiController::class, 'getApiDocs']);
    Route::post('/get-api-status', [FoundryApiController::class, 'getApiStatus']);
    Route::post('/get-clients', [FoundryApiController::class, 'getClients']);
    Route::post('/get-structure', [FoundryApiController::class, 'getStructure']);
    Route::post('/get-contents-by-path', [FoundryApiController::class, 'getContentsByPath']);
    Route::post('/search-entities', [FoundryApiController::class, 'searchEntities']);
    Route::post('/select-entities', [FoundryApiController::class, 'selectEntities']);
    Route::post('/get-selected-entities', [FoundryApiController::class, 'getSelectedEntities']);
    Route::post('/get-entity', [FoundryApiController::class, 'getEntity']);
    Route::post('/create-entity', [FoundryApiController::class, 'createEntity']);
    Route::post('/update-entity', [FoundryApiController::class, 'updateEntity']);
    Route::post('/delete-entity', [FoundryApiController::class, 'deleteEntity']);
    Route::post('/get-macros', [FoundryApiController::class, 'getMacros']);
    Route::post('/execute-macro', [FoundryApiController::class, 'executeMacro']);
    Route::post('/get-sheet', [FoundryApiController::class, 'getSheet']);
    Route::post('/get-rolls', [FoundryApiController::class, 'getRolls']);
    Route::post('/get-lastroll', [FoundryApiController::class, 'getLastRoll']);
    Route::post('/make-roll', [FoundryApiController::class, 'makeRoll']);
    Route::post('/get-encounters', [FoundryApiController::class, 'getEncounters']);
    Route::post('/start-encounter', [FoundryApiController::class, 'startEncounter']);
    Route::post('/next-turn-in-encounter', [FoundryApiController::class, 'nextTurnInEncounter']);
    Route::post('/next-round-in-encounter', [FoundryApiController::class, 'nextRoundInEncounter']);
    Route::post('/previous-turn-in-encounter', [FoundryApiController::class, 'previousTurnInEncounter']);
    Route::post('/previous-round-in-encounter', [FoundryApiController::class, 'previousRoundInEncounter']);
    Route::post('/end-encounter', [FoundryApiController::class, 'endEncounter']);
    Route::post('/add-to-encounter', [FoundryApiController::class, 'addToEncounter']);
    Route::post('/remove-from-encounter', [FoundryApiController::class, 'removeFromEncounter']);
    Route::post('/mark-entity-as-defeated', [FoundryApiController::class, 'markEntityAsDefeated']);
    Route::post('/decrease-attribute', [FoundryApiController::class, 'decreaseAttribute']);
    Route::post('/increase-attribute', [FoundryApiController::class, 'increaseAttribute']);
    Route::post('/give-item', [FoundryApiController::class, 'giveItem']);
});
