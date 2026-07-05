<?php

use App\Http\Controllers\Api\FoundryController;
use App\Http\Controllers\Api\PairingController;
use App\Http\Controllers\Api\SetupCodeController;
use App\Http\Controllers\Cyberdeck\InstallProgram;
use App\Http\Controllers\Cyberdeck\RemoveProgram;
use App\Http\Controllers\Cyberdeck\ToggleRez;
use App\Http\Controllers\Data\ActorSheet;
use App\Http\Controllers\Data\GetActor;
use App\Http\Controllers\Data\GetActors;
use App\Http\Controllers\Data\GetNetPool;
use App\Http\Controllers\Data\GetRollModifiers;
use App\Http\Controllers\Data\ImageProxy;
use App\Http\Controllers\Data\ItemEquip;
use App\Http\Controllers\Item\ConsumeItem;
use App\Http\Controllers\Rolls\AttackRoll;
use App\Http\Controllers\Rolls\DamageRoll;
use App\Http\Controllers\Rolls\DeathSave;
use App\Http\Controllers\Rolls\DiceRoller;
use App\Http\Controllers\Rolls\FaceDown;
use App\Http\Controllers\Rolls\InitiativeRoll;
use App\Http\Controllers\Rolls\ProgramRoll;
use App\Http\Controllers\Rolls\RoleRollRequest;
use App\Http\Controllers\Rolls\SkillRollRequest;
use App\Http\Controllers\Rolls\StatRollRequest;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\Token\GetVisibleTokens;
use App\Http\Controllers\Token\ListVisibleTokens;
use App\Http\Controllers\Token\MoveToken;
use App\Http\Controllers\Token\ReloadWeapon;
use App\Http\Controllers\Token\TargetTokens;
use App\Http\Controllers\Values\UpdateEuroBucks;
use App\Http\Controllers\Values\UpdateNotes;
use App\Http\Controllers\Values\UpdateStat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// // Protect any broadcast EPs via Sanctum
// Route::get('/broadcasting/auth', function (Request $request) {
//    return $request->user();
// })->middleware('auth:sanctum');

// Callback API routes (with deduplication & rate limiting)
Route::middleware(['throttle:60,1', 'foundry.deduplicate'])->group(function () {
    Route::post('/foundry/callback', [FoundryController::class, 'handle'])
        ->middleware('auth:sanctum');

    Route::post('/foundry/update', [FoundryController::class, 'update'])
        ->middleware('auth:sanctum');
});

Route::middleware(['auth:sanctum', 'app.configured', 'profile.setup'])->group(function () {
    Route::get('/getbaseurl', [SettingsController::class, 'getFoundryUrl']);
    Route::post('/statrollrequest', [StatRollRequest::class, 'rollStat']);
    Route::post('/skillrollrequest', [SkillRollRequest::class, 'rollSkill']);
    Route::post('/rolerollrequest', [RoleRollRequest::class, 'rollRole']);
    Route::post('/updatestat', [UpdateStat::class, 'updateStat']);
    Route::post('/updatenotes', [UpdateNotes::class, 'updateNotes']);
    Route::post('/actorsheet', [ActorSheet::class, 'getCleanData']);
    Route::post('/getactor', [GetActor::class, 'getActor']);
    Route::post('/getactors', [GetActors::class, 'getActors']);
    Route::post('/get-net-pool', [GetNetPool::class, 'getNetPool']);
    Route::post('/movetoken', [MoveToken::class, 'moveToken']);
    Route::post('/getvisibletokens', [GetVisibleTokens::class, 'getVisibleTokens']);
    Route::post('/listvisibletokens', [ListVisibleTokens::class, 'listVisibleTokens']);
    Route::post('/targettokens', [TargetTokens::class, 'targetTokens']);
    Route::post('/attackroll', [AttackRoll::class, 'attackRoll']);
    Route::post('/damageroll', [DamageRoll::class, 'damageRoll']);
    Route::post('/make-roll', [DiceRoller::class, 'makeRoll']);
    Route::get('/get-rolls', [DiceRoller::class, 'getRolls']);
    Route::post('/reloadweapon', [ReloadWeapon::class, 'reloadWeapon']);
    Route::post('/getrollmodifiers', [GetRollModifiers::class, 'getRollModifiers']);
    Route::post('/deathsave', [DeathSave::class, 'getDeathSave']);
    Route::post('/facedown', [FaceDown::class, 'getFaceDown']);
    Route::post('/initiativeroll', [InitiativeRoll::class, 'rollInitiative']);
    Route::post('/item-equip', [ItemEquip::class, 'updateState']);
    Route::post('/consume-item', [ConsumeItem::class, 'consume']);
    Route::post('/installprogram', [InstallProgram::class, 'installProgram']);
    Route::post('/uninstallprogram', [RemoveProgram::class, 'removeProgram']);
    Route::post('/toggleprogramrez', [ToggleRez::class, 'toggleRez']);
    Route::post('/programroll', [ProgramRoll::class, 'programRoll']);
    Route::post('/update-eb', [UpdateEuroBucks::class, 'updateEuroBucks']);
    Route::get('/proxy-image', [ImageProxy::class, 'proxy'])->name('proxy-image');
});

// QR pairing handshake. Not behind app.configured: claiming is what configures
// the player. register() is called by the GM's Bridge (Bearer token); claim()
// and status() by the player's Agent (session cookie).
Route::middleware(['auth:sanctum', 'throttle:30,1'])->prefix('pairing')->group(function () {
    Route::post('/register', [PairingController::class, 'register'])->name('pairing.register');
    Route::post('/claim', [PairingController::class, 'claim'])->name('pairing.claim');
    Route::get('/status', [PairingController::class, 'status'])->name('pairing.status');
});

// Short setup code redemption, called by the Bridge's Quick Setup before it has
// any credentials. Public by necessity; hard-throttled, single-use, short TTL.
Route::middleware('throttle:10,1')
    ->get('/setup-code/{code}', [SetupCodeController::class, 'redeem'])
    ->name('setup-code.redeem');

Broadcast::routes(['middleware' => [
    'auth:sanctum',  // 2. Allow EITHER Sanctum Token OR Web Session
]]);
