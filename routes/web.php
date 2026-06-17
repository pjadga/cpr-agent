<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SettingsController;
use App\Models\Config;
use Illuminate\Http\Request;
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

Route::middleware(['auth', 'app.configured', 'profile.setup'])->group(function () {
    Route::get('/dashboard', function () {
        return view('api');
    })->name('dashboard');
});

Route::middleware(['auth', 'app.configured'])->group(function () {
    Route::get('/settings/refresh', [SettingsController::class, 'refresh'])->name('settings.refresh');
    Route::get('/settings', [SettingsController::class, 'show'])->name('settings');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Session management routes (unified onboarding)
Route::middleware(['auth'])->group(function () {
    Route::post('/settings/onboarding/read', [SettingsController::class, 'markOnboardingAsRead'])->name('settings.onboarding.read');
    Route::post('/settings/sync', [SettingsController::class, 'storeSync'])->name('profile-setup.store');
    Route::get('/config', [SettingsController::class, 'show'])->name('config.index');
    Route::post('/config', [SettingsController::class, 'storeConfig'])->name('config.store');

    // Session API endpoints
    Route::post('/session/create', [SessionController::class, 'create'])->name('session.create');
    Route::post('/session/join', [SessionController::class, 'join'])->name('session.join');
    Route::post('/session/config', [SessionController::class, 'setFoundryConfig'])->name('session.config');
    Route::get('/session/info', [SessionController::class, 'show'])->name('session.info');
    Route::post('/session/leave', [SessionController::class, 'leave'])->name('session.leave');
    Route::delete('/session/delete', [SessionController::class, 'destroy'])->name('session.delete');
});

// API Key registration

Route::middleware('auth')->post('/settings/api-token', function (Request $request) {
    // Delete old tokens if you want them to only have one active connection
    $request->user()->tokens()->delete();

    $token = $request->user()->createToken('FoundryClient')->plainTextToken;

    // Save it to the users config
    $config = $request->user()->config ?: new Config(['user_id' => $request->user()->id]);
    $config->api_key = $token;
    $config->save();

    return back()->with('flash_token', $token);
})->name('token.create');

require __DIR__.'/auth.php';
