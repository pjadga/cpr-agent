<?php

use App\Http\Controllers\Data\ActorSheet;
use App\Http\Controllers\Data\GetActors;
use App\Http\Controllers\Rolls\RoleRollRequest;
use App\Http\Controllers\Rolls\SkillRollRequest;
use App\Http\Controllers\Rolls\StatRollRequest;
use App\Http\Controllers\Values\UpdateStat;
use App\Http\Controllers\Views\ConfigController;
use Illuminate\Support\Facades\Route;

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
