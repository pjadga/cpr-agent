<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Lock-screen sign-in replaces email and password: a user signs in with any one of their username,
 * PIN or 3x3 pattern, so each is unique across accounts. PINs and patterns are kept only as keyed
 * hashes (App\Support\AccessCode). Email becomes an optional recovery address.
 *
 * The Agent always has a gamemaster account, which starts with the username "gamemaster" and is the
 * only one that manages the other accounts. An existing GM becomes it, keeping their characters.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->string('username')->nullable()->after('name');
            $table->string('given_username')->nullable()->after('username');
            $table->string('pin_hash', 64)->nullable()->unique()->after('password');
            $table->string('pattern_hash', 64)->nullable()->unique()->after('pin_hash');
            $table->boolean('is_gamemaster')->default(false)->after('is_gm');
            $table->timestamp('access_reviewed_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('email');
            $table->unique('username');
        });

        // Usernames start as each account's name, made unique ignoring case. Nobody has reviewed
        // their access yet, so everyone is asked on their next sign-in.
        $taken = ['gamemaster' => true];
        $gamemasterId = DB::table('users')->where('is_gm', true)->orderBy('id')->value('id')
            ?? DB::table('users')->orderBy('id')->value('id');

        foreach (DB::table('users')->orderBy('id')->get(['id', 'name']) as $user) {
            if ($user->id == $gamemasterId) {
                DB::table('users')->where('id', $user->id)
                    ->update(['username' => 'gamemaster', 'given_username' => 'gamemaster', 'is_gamemaster' => true, 'is_gm' => true]);

                continue;
            }

            $base = trim($user->name) !== '' ? trim($user->name) : "player{$user->id}";
            $username = $base;
            for ($n = 2; isset($taken[mb_strtolower($username)]); $n++) {
                $username = "{$base}{$n}";
            }
            $taken[mb_strtolower($username)] = true;
            DB::table('users')->where('id', $user->id)->update(['username' => $username, 'given_username' => $username]);
        }

        if (! $gamemasterId) {
            DB::table('users')->insert([
                'name' => 'Gamemaster',
                'username' => 'gamemaster',
                'given_username' => 'gamemaster',
                'is_gm' => true,
                'is_gamemaster' => true,
                'agent_link' => (string) Str::uuid(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // The gamemaster's session is everyone's channel for as long as the Agent runs.
        DB::table('game_sessions')->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->update(['expires_at' => null]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropUnique(['pin_hash']);
            $table->dropUnique(['pattern_hash']);
            $table->dropColumn(['username', 'given_username', 'pin_hash', 'pattern_hash', 'is_gamemaster', 'access_reviewed_at']);
        });
    }
};
