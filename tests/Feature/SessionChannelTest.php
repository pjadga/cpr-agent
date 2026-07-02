<?php

namespace Tests\Feature;

use App\Models\Config;
use App\Models\Session;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SessionChannelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function creating_a_session_puts_the_gm_on_the_session_channel()
    {
        $gm = User::factory()->create(['is_gm' => true]);

        $response = $this->actingAs($gm)->postJson(route('session.create'));

        $response->assertOk();
        $code = $response->json('session.code');

        // GM, players (via joinPlayer) and Foundry all share cpr-agent.{code}.
        $this->assertSame($code, $gm->fresh()->channel_id);
    }

    #[Test]
    public function creating_a_session_auto_syncs_the_gms_foundry_url_for_players()
    {
        $gm = User::factory()->create(['is_gm' => true]);
        Config::create(['user_id' => $gm->id, 'foundry_url' => 'https://gm-foundry.example.com']);

        $this->actingAs($gm)->postJson(route('session.create'))->assertOk();

        $session = Session::where('gm_user_id', $gm->id)->first();
        $this->assertSame('https://gm-foundry.example.com', $session->foundry_config['foundry_url']);
    }

    #[Test]
    public function saving_connection_settings_resyncs_the_active_session()
    {
        $gm = User::factory()->create(['is_gm' => true]);
        Config::create(['user_id' => $gm->id, 'foundry_url' => 'https://old.example.com']);
        $this->actingAs($gm)->postJson(route('session.create'));

        $this->actingAs($gm)->post(route('config.store'), [
            'foundry_url' => 'https://new.example.com',
        ])->assertRedirect(route('settings'));

        $session = Session::where('gm_user_id', $gm->id)->first();
        $this->assertSame('https://new.example.com', $session->foundry_config['foundry_url']);
    }

    #[Test]
    public function recreating_with_an_existing_session_heals_a_drifted_gm_channel()
    {
        $gm = User::factory()->create(['is_gm' => true]);
        $session = Session::create(['gm_user_id' => $gm->id, 'expires_at' => now()->addDay()]);

        // Simulate drift: GM not on their session channel.
        $gm->channel_id = 'some-other-channel';
        $gm->save();

        $this->actingAs($gm)->postJson(route('session.create'))->assertOk();

        $this->assertSame($session->code, $gm->fresh()->channel_id);
    }
}
