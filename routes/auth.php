<?php

use App\Http\Controllers\AccessController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RecoveryController;
use App\Http\Controllers\GroupController;
use Illuminate\Support\Facades\Route;

// No sign-up: the gamemaster adds players by username, and each signs in with any one of their
// username, PIN or pattern.
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('recover', [RecoveryController::class, 'create'])->name('recover');
    Route::post('recover', [RecoveryController::class, 'store'])->middleware('throttle:3,1')->name('recover.send');
    Route::get('recover/{token}', [RecoveryController::class, 'show'])->middleware('throttle:6,1')->name('recover.show');
});

Route::middleware('auth')->group(function () {
    Route::get('access', [AccessController::class, 'edit'])->name('access.edit');
    Route::put('access', [AccessController::class, 'update'])->name('access.update');

    Route::get('group', [GroupController::class, 'index'])->name('group.index');
    Route::post('group', [GroupController::class, 'store'])->name('group.store');
    Route::patch('group/{user}', [GroupController::class, 'update'])->name('group.update');
    Route::delete('group/{user}', [GroupController::class, 'destroy'])->name('group.destroy');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
