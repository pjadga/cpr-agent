<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Self-hosted installs leave the Turnstile keys blank, which sets
 * services.turnstile.enabled to false. Auth must work with no captcha and
 * without pulling in Cloudflare's external script. These tests lock that in
 * so the self-host path can't silently regress back to being login-blocked.
 */
class TurnstileDisabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_works_without_a_token_when_turnstile_disabled(): void
    {
        config(['services.turnstile.enabled' => false]);
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            // no cf-turnstile-response on purpose
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registration_works_without_a_token_when_turnstile_disabled(): void
    {
        config(['services.turnstile.enabled' => false]);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'player',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_login_page_omits_cloudflare_script_when_turnstile_disabled(): void
    {
        config(['services.turnstile.enabled' => false]);

        $this->get('/login')
            ->assertStatus(200)
            ->assertDontSee('challenges.cloudflare.com');
    }

    public function test_login_still_requires_a_token_when_turnstile_enabled(): void
    {
        config(['services.turnstile.enabled' => true]);
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            // no cf-turnstile-response: the 'required' rule must reject this
        ])->assertSessionHasErrors('cf-turnstile-response');

        $this->assertGuest();
    }
}
