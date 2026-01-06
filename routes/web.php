<?php

use App\Http\Controllers\Data\ActorSheet;
use App\Http\Controllers\Data\GetActors;
use App\Http\Controllers\Rolls\RoleRollRequest;
use App\Http\Controllers\Rolls\SkillRollRequest;
use App\Http\Controllers\Rolls\StatRollRequest;
use App\Http\Controllers\Token\MoveToken;
use App\Http\Controllers\Values\UpdateStat;
use App\Http\Controllers\Views\ConfigController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileSetupController;
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
 * Flow
 * Signup
 * Save deets
 * Choose default client - CALL getclient
 * choose defualt actor - CALL getPlayerTokens (tokens owned by players)
 *
 * let tokens = canvas.tokens.placeables
  .filter(t => t.actor && t.actor.hasPlayerOwner)
  .map(t => ({
    name: t.name,
    actorID: t.actor.id,
    tokenID: t.id
  }));return tokens;


 *
 * Load sheet
 * Rolls are done via JS for now.
 * Direction - JS for now
 *
 * Control -
 * Find all tokens that are not hidden on board - list with coords - triggers aim on that token when selected
 * Work out DV value
 * Equip weapon
 * Choose mode
 * Roll to hit
 * Roll damage
 *
 *
 * Todo
 * Weapon rolls - roll weapon + aim mode.
 * Find Enemies
 * Get Player owned tokens
 * Get tokens on canvas - pass player controll - enemy (not hidden)
 * Facedown? Death Saves
 * Reload?
 * Ping on Change - TH Module needs to ping relay if anything happens so it can update sheets
 *
 *  */

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('api');
})->middleware(['auth', 'verified', 'profile.setup'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/profile-setup', [ProfileSetupController::class, 'show'])->name('profile-setup.show');
    Route::post('/profile-setup', [ProfileSetupController::class, 'store'])->name('profile-setup.store');
});

Route::middleware(['auth', 'profile.setup'])->group(function () {
    Route::get('/config', [ConfigController::class, 'index'])->name('config.index');
    Route::post('/config', [ConfigController::class, 'store'])->name('config.store');

    Route::post("/statrollrequest", [StatRollRequest::class, "rollStat"]);
    Route::post("/skillrollrequest", [SkillRollRequest::class, "rollSkill"]);
    Route::post("/rolerollrequest", [RoleRollRequest::class, "rollRole"]);
    Route::post("/updatestat", [UpdateStat::class, "updateStat"]);
    Route::post("/actorsheet", [ActorSheet::class, "getCleanData"]);
    Route::post("/getactors", [GetActors::class, "getActors"]);
    Route::post("/movetoken", [MoveToken::class, "moveToken"]);
});

require __DIR__.'/auth.php';
