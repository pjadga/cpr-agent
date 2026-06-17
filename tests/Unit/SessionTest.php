<?php

namespace Tests\Unit;

use App\Models\Session;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertMatchesRegularExpression('/^[a-z]+-[A-Z]{3}$/', $session->code);
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

        $this->assertNotNull($player->fresh()->channel_id);

        $session->delete();

        $this->assertNull(User::where('id', $player->id)->value('channel_id'));
    }
}
