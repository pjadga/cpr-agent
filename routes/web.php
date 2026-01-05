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
