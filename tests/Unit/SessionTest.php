<?php

namespace Tests\Unit;

use App\Models\Session;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SessionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_generates_unique_code()
    {
        $session = Session::create([
            'gm_user_id' => null,
        ]);

        $this->assertNotNull($session->code);
        $this->assertMatchesRegularExpression('/^[a-z]+-[A-Z0-9]{3}$/', $session->code);
    }

    #[Test]
    public function it_joins_a_player_to_session()
    {
        $gm = User::factory()->create(['is_gm' => true]);
        $session = Session::create([
            'gm_user_id' => $gm->id,
        ]);

        $player = User::factory()->create();
        $result = $session->joinPlayer($player);

        $this->assertTrue($result['success']);
        $this->assertEquals($session->code, $player->fresh()->channel_id);
        $this->assertEquals(1, $session->fresh()->player_count);
    }

    #[Test]
    public function it_sets_and_retrieves_foundry_config()
    {
        $gm = User::factory()->create(['is_gm' => true]);
        $session = Session::create([
            'gm_user_id' => $gm->id,
        ]);

        $session->setFoundryConfig([
            'foundry_url' => 'https://foundry.example.com',
            'reverb_port' => 443,
            'reverb_use_ssl' => true,
        ]);

        $this->assertEquals('https://foundry.example.com', $session->fresh()->foundry_config['foundry_url']);
    }

    #[Test]
    public function it_detects_expired_sessions()
    {
        $gm = User::factory()->create(['is_gm' => true]);
        $session = Session::create([
            'gm_user_id' => $gm->id,
            'expires_at' => now()->subDay(),
        ]);

        $this->assertTrue($session->fresh()->isExpired());
    }

    #[Test]
    public function it_clears_player_channel_on_delete()
    {
        $gm = User::factory()->create(['is_gm' => true]);
        $session = Session::create([
            'gm_user_id' => $gm->id,
        ]);

        $player = User::factory()->create();
        $session->joinPlayer($player);

        $this->assertEquals($session->code, $player->fresh()->channel_id);

        $code = $session->code;
        $session->delete();

        // The player is disconnected from the session: their channel is no longer
        // the session code, and their session-scoped actor binding is cleared.
        // (The User::getChannelIdAttribute accessor then hands them a fresh personal
        // channel by design, so the raw value is non-session rather than strictly null.)
        $this->assertNotEquals($code, User::where('id', $player->id)->value('channel_id'));
        $this->assertNull(User::where('id', $player->id)->value('default_actor_id'));
    }

    #[Test]
    public function deleting_a_session_frees_the_gm_channel_but_keeps_their_actor()
    {
        $gm = User::factory()->create(['is_gm' => true, 'default_actor_id' => 'actor-gm-1']);
        $session = Session::create(['gm_user_id' => $gm->id]);

        // The GM is on their own session channel (mirrors SessionController::create).
        $gm->channel_id = $session->code;
        $gm->save();

        $code = $session->code;
        $session->delete();

        // GM leaves the dead channel, but their character selection is preserved.
        $this->assertNotEquals($code, User::where('id', $gm->id)->value('channel_id'));
        $this->assertSame('actor-gm-1', User::where('id', $gm->id)->value('default_actor_id'));
    }
}
