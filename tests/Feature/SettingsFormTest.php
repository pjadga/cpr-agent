<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The settings page groups fields into "Connect to Foundry" (config form) and
 * "Your Agent" (sync form). Channel ID moved into the connection form and Theme
 * into the agent form, so each form must persist its moved field.
 */
class SettingsFormTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function channel_id_saves_through_the_connection_form()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('config.store'), [
                'foundry_url' => 'https://foundry.example.com',
                'channel_id' => 'my-channel-1',
            ])
            ->assertRedirect(route('settings'));

        $this->assertSame('my-channel-1', $user->fresh()->channel_id);
        $this->assertSame('https://foundry.example.com', $user->fresh()->config->foundry_url);
    }

    #[Test]
    public function theme_saves_through_the_agent_form()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile-setup.store'), ['theme' => 'corpo'])
            ->assertRedirect(route('settings'));

        $this->assertSame('corpo', $user->fresh()->config->theme);
    }
}
