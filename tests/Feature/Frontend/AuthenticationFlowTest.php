<?php

namespace Tests\Feature\Frontend;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Frontend authentication and framework tests
 */
class AuthenticationFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function login_screen_can_be_rendered()
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    #[Test]
    public function users_can_authenticate_using_the_login_screen()
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'method' => 'username',
            'value' => $user->username,
            'cf-turnstile-response' => 'test-token',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/dashboard');
    }

    #[Test]
    public function users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'method' => 'username',
            'value' => 'not-'.$user->username,
        ]);

        $this->assertGuest();
    }

    #[Test]
    public function users_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    #[Test]
    public function user_has_agent_link_on_creation()
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->agent_link);
        $this->assertIsString($user->agent_link);
    }

    #[Test]
    public function user_has_channel_id_on_creation()
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->channel_id);
        $this->assertIsString($user->channel_id);
        $this->assertMatchesRegularExpression('/^[a-z]+-[a-z]+-\d{3}$/', $user->channel_id);
    }

    #[Test]
    public function authenticated_user_can_access_dashboard()
    {
        $user = User::factory()->create([
            'character_data' => ['name' => 'Test Character'],
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
    }

    #[Test]
    public function guest_cannot_access_dashboard()
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    #[Test]
    public function user_can_update_profile_information()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/profile', [
            'name' => 'Updated Name',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/settings');

        $user->refresh();

        $this->assertEquals('Updated Name', $user->name);
    }

}
