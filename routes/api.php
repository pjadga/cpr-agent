<?php

use App\Http\Controllers\Api\FoundryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Protect any broadcast EPs via Sanctum
Route::get('/broadcasting/auth', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Callback API routes
Route::post('/foundry/callback', [FoundryController::class, 'handle'])
    ->middleware('auth:sanctum');

Route::post('/foundry/update', [FoundryController::class, 'update'])
    ->middleware('auth:sanctum');

Broadcast::routes(['middleware' => [
    'web',              // 1. Run 'web' middleware to Start Session & Read Cookies
    'auth:sanctum,web',  // 2. Allow EITHER Sanctum Token OR Web Session
]]);
