<?php

namespace Tests\Feature;

use App\Models\Session;
use App\Models\User;
use App\Support\AccessCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Lock-screen sign-in: username, PIN or pattern, any one on its own. The gamemaster account always
 * exists, adds players by username, and can change anyone's username, PIN or pattern.
 */
class LockScreenAccessTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(string $method, string $value)
    {
        return $this->post('/login', ['method' => $method, 'value' => $value, 'cf-turnstile-response' => 'test-token']);
    }

    private function gamemaster(): User
    {
        return User::gamemaster();
    }

    public function test_the_agent_starts_with_a_gamemaster_account(): void
    {
        $gm = $this->gamemaster();

        $this->assertNotNull($gm);
        $this->assertSame('gamemaster', $gm->username);
        $this->assertTrue($gm->is_gm);
        $this->assertNull($gm->access_reviewed_at);
    }

    public function test_registration_and_password_reset_are_gone(): void
    {
        $this->get('/register')->assertNotFound();
        $this->get('/forgot-password')->assertNotFound();
    }

    public function test_the_gamemaster_signs_in_with_gamemaster_and_is_asked_to_review_access(): void
    {
        $this->signIn('username', 'GameMaster')->assertRedirect(route('access.edit'));
        $this->assertAuthenticatedAs($this->gamemaster());
    }

    public function test_a_pin_or_a_pattern_signs_in_on_its_own(): void
    {
        $user = User::factory()->create(['access_reviewed_at' => now()]);
        $user->setPin('4321');
        $user->setPattern('1-2-3-6-9');
        $user->save();

        $this->signIn('pin', '4321')->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        auth()->logout();

        $this->signIn('pattern', '1-2-3-6-9');
        $this->assertAuthenticatedAs($user);
        auth()->logout();

        $this->signIn('pin', '1234')->assertSessionHasErrors('value');
        $this->signIn('pattern', '1-2-3-6')->assertSessionHasErrors('value');
        $this->assertGuest();
    }

    public function test_pins_and_patterns_are_stored_only_as_hashes(): void
    {
        $user = User::factory()->create();
        $user->setPin('4321');
        $user->setPattern('7-5-3-6');
        $user->save();

        $row = (array) \DB::table('users')->find($user->id);
        $this->assertSame(AccessCode::hash('pin', '4321'), $row['pin_hash']);
        $this->assertStringNotContainsString('4321', json_encode($row));
        $this->assertStringNotContainsString('7-5-3-6', json_encode($row));
        // None of the sign-in values reach the API.
        $this->assertArrayNotHasKey('username', $user->toArray());
        $this->assertArrayNotHasKey('pin_hash', $user->toArray());
    }

    public function test_repeated_failures_lock_the_address_out(): void
    {
        foreach (range(1, 5) as $_) {
            $this->signIn('pin', '0000');
        }
        $user = User::factory()->create(['access_reviewed_at' => now()]);

        $this->signIn('username', $user->username)->assertSessionHasErrors('value');
        $this->assertGuest();
    }

    public function test_first_sign_in_lets_a_player_replace_the_username_and_add_a_pin_pattern_and_email(): void
    {
        $this->actingAs($this->gamemaster())->post('/group', ['username' => 'Roy']);
        auth()->logout();

        $this->signIn('username', 'roy')->assertRedirect(route('access.edit'));
        $roy = User::where('username', 'Roy')->firstOrFail();

        $this->put('/access', [
            'username' => 'Wanderer',
            'pin' => '246810',
            'pin_confirmation' => '246810',
            'pattern' => '1-4-7-8-9',
            'email' => 'roy@example.com',
        ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));

        $roy->refresh();
        $this->assertSame('Wanderer', $roy->username);
        $this->assertSame('Roy', $roy->given_username);
        $this->assertNotNull($roy->access_reviewed_at);
        $this->assertSame('roy@example.com', $roy->email);

        auth()->logout();
        $this->signIn('pin', '246810')->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($roy);
    }

    public function test_keeping_everything_as_given_is_fine(): void
    {
        $user = User::factory()->create(['access_reviewed_at' => null]);

        $this->actingAs($user)->put('/access', ['username' => $user->username])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($user->fresh()->access_reviewed_at);
    }

    public function test_two_accounts_cannot_share_a_username_pin_or_pattern(): void
    {
        $taken = User::factory()->create();
        $taken->setPin('5555');
        $taken->setPattern('3-5-7-8');
        $taken->save();
        $user = User::factory()->create();

        $this->actingAs($user)->put('/access', ['username' => strtoupper($taken->username)])->assertSessionHasErrors('username');
        $this->actingAs($user)->put('/access', ['username' => $user->username, 'pin' => '5555', 'pin_confirmation' => '5555'])
            ->assertSessionHasErrors('pin');
        $this->actingAs($user)->put('/access', ['username' => $user->username, 'pattern' => '3-5-7-8'])->assertSessionHasErrors('pattern');
        $this->assertNull($user->fresh()->pin_hash);
    }

    public function test_bad_pins_and_patterns_are_refused(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/access', ['username' => $user->username, 'pin' => '12', 'pin_confirmation' => '12'])
            ->assertSessionHasErrors('pin');
        $this->actingAs($user)->put('/access', ['username' => $user->username, 'pin' => '1234', 'pin_confirmation' => '9999'])
            ->assertSessionHasErrors('pin');
        $this->actingAs($user)->put('/access', ['username' => $user->username, 'pattern' => '1-2-3'])->assertSessionHasErrors('pattern');
        $this->actingAs($user)->put('/access', ['username' => $user->username, 'pattern' => '1-2-1-3'])->assertSessionHasErrors('pattern');
    }

    public function test_a_user_can_remove_their_pin_and_pattern(): void
    {
        $user = User::factory()->create();
        $user->setPin('4444');
        $user->setPattern('1-5-9-6');
        $user->save();

        $this->actingAs($user)->put('/access', ['username' => $user->username, 'clear_pin' => '1', 'clear_pattern' => '1']);

        $user->refresh();
        $this->assertNull($user->pin_hash);
        $this->assertNull($user->pattern_hash);
    }

    public function test_the_gamemaster_adds_a_player_who_joins_their_session(): void
    {
        $gm = $this->gamemaster();

        $this->actingAs($gm)->post('/group', ['username' => 'Roy'])->assertRedirect(route('group.index'));
        $this->actingAs($gm)->post('/group', ['username' => 'ROY'])->assertSessionHasErrors('username');

        $roy = User::where('username', 'Roy')->firstOrFail();
        $session = Session::where('gm_user_id', $gm->id)->firstOrFail();
        $this->assertNull($session->expires_at);
        $this->assertSame($session->code, $roy->channel_id);
        $this->assertFalse($roy->is_gm);
    }

    public function test_the_gamemaster_changes_anyones_username_pin_and_pattern(): void
    {
        $gm = $this->gamemaster();
        $user = User::factory()->create(['username' => 'chosen', 'given_username' => 'given']);

        $this->actingAs($gm)->get('/group')->assertOk()->assertSee('chosen')->assertSee('given: given');

        $this->actingAs($gm)->patch("/group/{$user->id}", ['username' => 'reset', 'pin' => '8080', 'pattern' => '2-5-8-9'])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('reset', $user->username);
        $this->assertSame('reset', $user->given_username);
        $this->assertSame($user->id, User::findByAccess('pin', '8080')?->id);
        $this->assertSame($user->id, User::findByAccess('pattern', '2-5-8-9')?->id);
    }

    public function test_only_the_gamemaster_manages_the_group(): void
    {
        $player = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($player)->get('/group')->assertForbidden();
        $this->actingAs($player)->post('/group', ['username' => 'eve'])->assertForbidden();
        $this->actingAs($player)->patch("/group/{$other->id}", ['username' => 'hijacked'])->assertForbidden();
        $this->actingAs($player)->delete("/group/{$other->id}")->assertForbidden();
        $this->assertNotNull($other->fresh());
    }

    public function test_the_gamemaster_account_cannot_be_removed(): void
    {
        $gm = $this->gamemaster();
        $player = User::factory()->create();

        $this->actingAs($gm)->delete("/group/{$gm->id}")->assertSessionHas('error');
        $this->assertNotNull($gm->fresh());

        $this->actingAs($gm)->delete("/group/{$player->id}");
        $this->assertNull($player->fresh());
    }

    public function test_a_recovery_email_sends_a_one_time_sign_in_link(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'roy@example.com']);

        $this->post('/recover', ['email' => 'nobody@example.com'])->assertSessionHas('status');
        $this->post('/recover', ['email' => 'roy@example.com'])->assertSessionHas('status');

        $token = Password::broker()->createToken($user);
        $this->get("/recover/{$token}?email=roy@example.com")->assertRedirect(route('access.edit'));
        $this->assertAuthenticatedAs($user);

        auth()->logout();
        $this->get("/recover/{$token}?email=roy@example.com")->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
