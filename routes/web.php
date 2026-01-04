<?php

use App\Http\Controllers\Rolls\RoleRollRequest;
use App\Http\Controllers\Rolls\SkillRollRequest;
use App\Http\Controllers\Rolls\StatRollRequest;
use App\Http\Controllers\Values\UpdateStat;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/api', function () { // Route to display the Blade view
    return view('api');
});

Route::post("/statrollrequest", [StatRollRequest::class, "rollStat"]);
Route::post("/skillrollrequest", [SkillRollRequest::class, "rollSkill"]);
Route::post("/rolerollrequest", [RoleRollRequest::class, "rollRole"]);
Route::post("/updatestat", [UpdateStat::class, "updateStat"]);
