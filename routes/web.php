<?php

use App\Http\Controllers\Data\ActorSheet;
use App\Http\Controllers\Data\GetActors;
use App\Http\Controllers\Rolls\RoleRollRequest;
use App\Http\Controllers\Rolls\SkillRollRequest;
use App\Http\Controllers\Rolls\StatRollRequest;
use App\Http\Controllers\Values\UpdateStat;
use App\Http\Controllers\Views\ConfigController;
use Illuminate\Support\Facades\Route;

/*
 * This is creating the problem of ownership. Each user will need their own Client ID - which is linked to their foundry instance.
 * The Client ID, sans "foundry-" is the ID. On this load, ideally, the user can log in, the same as foundry. In reality - these ID's will need to be
 * assigned manually in something akin to the configuration. It's not as good as a login, but only needs to be done once.
 * That said, this takes a while - so the User flow is
 *
 * Load app, Log on if needed to (to laravel)
 * After setting up their ClientId, they will be able to see what Actors are owned by then using the script below.
 * They can then save the Actor ID to their profile, allowing it to be loaded when ever.
 *
 * Todo
 * Weapon rolls
 */

Route::get('/', function () {
    return view('welcome');
});

Route::get('/config', [ConfigController::class, 'index'])->name('config.index');
Route::post('/config', [ConfigController::class, 'store'])->name('config.store');

Route::get('/api', function () { // Route to display the Blade view
    return view('api');
});

Route::post("/statrollrequest", [StatRollRequest::class, "rollStat"]);
Route::post("/skillrollrequest", [SkillRollRequest::class, "rollSkill"]);
Route::post("/rolerollrequest", [RoleRollRequest::class, "rollRole"]);
Route::post("/updatestat", [UpdateStat::class, "updateStat"]);
Route::post("/actorsheet", [ActorSheet::class, "getCleanData"]);
Route::post("/getactors", [GetActors::class, "getActors"]);
