<?php

use App\Http\Controllers\Cyberdeck\InstallProgram;
use App\Http\Controllers\Cyberdeck\RemoveProgram;
use App\Http\Controllers\Cyberdeck\ToggleRez;
use App\Http\Controllers\Data\ActorSheet;
use App\Http\Controllers\Data\GetActors;
use App\Http\Controllers\Data\GetRollModifiers;
use App\Http\Controllers\Rolls\AttackRoll;
use App\Http\Controllers\Rolls\DamageRoll;
use App\Http\Controllers\Rolls\DeathSave;
use App\Http\Controllers\Rolls\FaceDown;
use App\Http\Controllers\Rolls\InitiativeRoll;
use App\Http\Controllers\Rolls\ProgramRoll;
use App\Http\Controllers\Rolls\RoleRollRequest;
use App\Http\Controllers\Rolls\SkillRollRequest;
use App\Http\Controllers\Rolls\StatRollRequest;
use App\Http\Controllers\Rolls\DiceRoller;
use App\Http\Controllers\Token\GetVisibleTokens;
use App\Http\Controllers\Token\MoveToken;
use App\Http\Controllers\Token\ListVisibleTokens;
use App\Http\Controllers\Token\ReloadWeapon;
use App\Http\Controllers\Token\TargetTokens;
use App\Http\Controllers\Values\UpdateStat;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileSetupController;
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
 * Facedown?
 * Death Saves

 * Ping on Change - TH Module needs to ping relay if anything happens so it can update sheets
 *
 *  */

Route::get('/', function () {
    return view('welcome');
});

Route::get('/how-to-use', function () {
    return view('how-to-use');
})->name('how-to-use');

Route::view('/design-brief', 'design-brief');

Route::get('/dashboard', function () {
    return view('api');
})->middleware(['auth', 'verified', 'app.configured', 'profile.setup'])->name('dashboard');

Route::middleware(['auth', 'app.configured'])->group(function () {
    Route::get('/settings', [ProfileSetupController::class, 'show'])->name('settings');
    Route::get('/profile-setup', [ProfileSetupController::class, 'show'])->name('profile-setup.show');
    Route::post('/profile-setup', [ProfileSetupController::class, 'store'])->name('profile-setup.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/config', [ConfigController::class, 'index'])->name('config.index');
    Route::post('/config', [ConfigController::class, 'store'])->name('config.store');
});

Route::middleware(['auth', 'app.configured', 'profile.setup'])->group(function () {
    Route::get('/getbaseurl', [ConfigController::class, 'getFoundryUrl']);
    Route::post("/statrollrequest", [StatRollRequest::class, "rollStat"]);
    Route::post("/skillrollrequest", [SkillRollRequest::class, "rollSkill"]);
    Route::post("/rolerollrequest", [RoleRollRequest::class, "rollRole"]);
    Route::post("/updatestat", [UpdateStat::class, "updateStat"]);
    Route::post("/actorsheet", [ActorSheet::class, "getCleanData"]);
    Route::post("/getactors", [GetActors::class, "getActors"]);
    Route::post("/movetoken", [MoveToken::class, "moveToken"]);
    Route::post("/getvisibletokens", [GetVisibleTokens::class, "getVisibleTokens"]);
    Route::post("/listvisibletokens", [ListVisibleTokens::class, "listVisibleTokens"]);
    Route::post("/targettokens", [TargetTokens::class, "targetTokens"]);
    Route::post("/attackroll", [AttackRoll::class, "attackRoll"]);
    Route::post("/damageroll", [DamageRoll::class, "damageRoll"]);
    Route::post("/make-roll", [DiceRoller::class, "makeRoll"]);
    Route::get("/get-rolls", [DiceRoller::class, "getRolls"]);
    Route::post("/reloadweapon", [ReloadWeapon::class, "reloadWeapon"]);
    Route::post("/getrollmodifiers", [GetRollModifiers::class, "getRollModifiers"]);
    Route::post("/deathsave", [DeathSave::class, "getDeathSave"]);
    Route::post("/facedown", [FaceDown::class, "getFaceDown"]);
    Route::post("/initiativeroll", [InitiativeRoll::class, "rollInitiative"]);
    Route::post("/installprogram", [InstallProgram::class, "installProgram"]);
    Route::post("/uninstallprogram", [RemoveProgram::class, "removeProgram"]);
    Route::post("/toggleprogramrez", [ToggleRez::class, "toggleRez"]);
    Route::post("/programroll", [ProgramRoll::class, "programRoll"]);
});

require __DIR__.'/auth.php';
