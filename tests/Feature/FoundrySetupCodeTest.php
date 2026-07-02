<?php

namespace Tests\Feature;

use App\Models\Config;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FoundrySetupCodeTest extends TestCase
{
    use RefreshDatabase;

    private function gmWithKey(): User
    {
        $gm = User::factory()->create(['is_gm' => true]);
        Config::create(['user_id' => $gm->id, 'api_key' => 'secret-token-123']);

        return $gm;
    }

    #[Test]
    public function gm_gets_a_decodable_setup_code_carrying_their_channel_id()
    {
        config(['app.url' => 'https://agent.example.com', 'broadcasting.connections.reverb.key' => 'reverb-key-1']);

        $gm = $this->gmWithKey();

        $response = $this->actingAs($gm)->getJson(route('settings.foundry-setup-code'));

        $response->assertOk()->assertJson(['has_channel' => true]);

        $payload = json_decode(base64_decode($response->json('code')), true);
        $this->assertSame('https://agent.example.com', $payload['agentUrl']);
        // Must be the user's own channel_id — the value the live broadcast channel uses.
        $this->assertSame($gm->channel_id, $payload['channelId']);
        $this->assertSame('secret-token-123', $payload['apiKey']);
        $this->assertSame('reverb-key-1', $payload['reverbKey']);
    }

    #[Test]
    public function non_gm_is_forbidden()
    {
        $player = User::factory()->create(['is_gm' => false]);
        Config::create(['user_id' => $player->id, 'api_key' => 'x']);

        $this->actingAs($player)->getJson(route('settings.foundry-setup-code'))->assertForbidden();
    }

    #[Test]
    public function gm_without_api_key_is_told_to_generate_one()
    {
        $gm = User::factory()->create(['is_gm' => true]);

        $this->actingAs($gm)->getJson(route('settings.foundry-setup-code'))
            ->assertStatus(422)
            ->assertJson(['error' => 'no_api_key']);
    }
}
