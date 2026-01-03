<?php

use App\Http\Controllers\Rolls\SkillRollRequest;
use App\Http\Controllers\Rolls\StatRollRequest;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/api', function () { // Route to display the Blade view
    return view('api');
});

Route::post("/statrollrequest", [StatRollRequest::class, "rollStat"]);
Route::post("/skillrollrequest", [SkillRollRequest::class, "rollSkill"]);
